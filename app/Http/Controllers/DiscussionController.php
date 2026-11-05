<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDiscussionReplyRequest;
use App\Http\Requests\StoreDiscussionRequest;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DiscussionController extends Controller
{
    private const int PER_PAGE = 15;

    public function index(Request $request): View
    {
        $user = $request->user();
        $rooms = $this->roomsFor($user);
        $selectedRoom = $rooms->firstWhere('key', $request->query('room'));

        $discussions = Discussion::query()
            ->inRooms($selectedRoom ? collect([$selectedRoom]) : $rooms)
            ->with(['user:id,name,role', 'class:id,name', 'subject:id,code,name'])
            ->withCount('replies')
            ->withExists(['replies as has_best_reply' => fn ($query) => $query->where('is_best', true)])
            ->orderByDesc('is_pinned')
            ->latest('updated_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return $this->page($user, 'discussions.index', [
            'rooms' => $rooms,
            'selectedRoom' => $selectedRoom,
            'discussions' => $discussions,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        return $this->page($user, 'discussions.create', [
            'rooms' => $this->roomsFor($user),
            'selectedRoomKey' => old('room', $request->query('room')),
        ]);
    }

    public function store(StoreDiscussionRequest $request): RedirectResponse
    {
        [$classId, $subjectId] = array_map('intval', explode(':', $request->validated('room')));
        $this->authorize('create', [Discussion::class, $classId, $subjectId]);

        $discussion = Discussion::create([
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'user_id' => $request->user()->id,
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
            'image_path' => $this->storeImage($request),
        ]);

        return redirect()
            ->route("{$request->user()->role}.discussions.show", $discussion)
            ->with('success', 'Diskusi berhasil dibuat.');
    }

    public function show(Request $request, Discussion $discussion): View
    {
        $this->authorize('view', $discussion);

        $discussion->load(['user:id,name,role', 'class:id,name', 'subject:id,code,name']);

        $replies = $discussion->replies()
            ->with('user:id,name,role')
            ->orderByDesc('is_best')
            ->oldest('id')
            ->get();

        return $this->page($request->user(), 'discussions.show', [
            'discussion' => $discussion,
            'replies' => $replies,
            'repliesByParent' => $replies->groupBy(fn (DiscussionReply $reply): int => (int) $reply->parent_id),
        ]);
    }

    public function destroy(Request $request, Discussion $discussion): RedirectResponse
    {
        $this->authorize('delete', $discussion);

        $discussion->delete();

        return redirect()
            ->route("{$request->user()->role}.discussions.index")
            ->with('success', 'Diskusi berhasil dihapus.');
    }

    public function image(Discussion $discussion): BinaryFileResponse
    {
        $this->authorize('view', $discussion);

        return $discussion->imageResponse();
    }

    public function replyImage(Discussion $discussion, DiscussionReply $reply): BinaryFileResponse
    {
        $this->authorize('view', $discussion);
        $this->ensureReplyBelongsTo($discussion, $reply);

        return $reply->imageResponse();
    }

    public function storeReply(StoreDiscussionReplyRequest $request, Discussion $discussion): RedirectResponse
    {
        $this->authorize('reply', $discussion);

        $reply = $discussion->replies()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $request->validated('parent_id'),
            'body' => $request->validated('body'),
            'image_path' => $this->storeImage($request),
        ]);

        return redirect()
            ->to(route("{$request->user()->role}.discussions.show", $discussion)."#reply-{$reply->id}")
            ->with('success', 'Balasan terkirim.');
    }

    public function destroyReply(Discussion $discussion, DiscussionReply $reply): RedirectResponse
    {
        $this->ensureReplyBelongsTo($discussion, $reply);
        $this->authorize('deleteReply', [$discussion, $reply]);

        $reply->delete();

        return back()->with('success', 'Balasan berhasil dihapus.');
    }

    /**
     * Moderation leaves updated_at alone, so it does not reorder the list like new activity would.
     */
    public function togglePin(Discussion $discussion): RedirectResponse
    {
        $this->authorize('moderate', $discussion);

        Discussion::withoutTimestamps(fn () => $discussion->update(['is_pinned' => ! $discussion->is_pinned]));

        return back()->with('success', $discussion->is_pinned ? 'Diskusi disematkan.' : 'Sematan dilepas.');
    }

    public function toggleClosed(Discussion $discussion): RedirectResponse
    {
        $this->authorize('moderate', $discussion);

        Discussion::withoutTimestamps(fn () => $discussion->update(['is_closed' => ! $discussion->is_closed]));

        return back()->with('success', $discussion->is_closed ? 'Diskusi ditutup.' : 'Diskusi dibuka kembali.');
    }

    /**
     * A discussion has one best answer, so marking a reply replaces any earlier choice and marking it again clears it.
     */
    public function toggleBestReply(Discussion $discussion, DiscussionReply $reply): RedirectResponse
    {
        $this->ensureReplyBelongsTo($discussion, $reply);
        $this->authorize('moderate', $discussion);

        DB::transaction(function () use ($discussion, $reply): void {
            $discussion->replies()->update(['is_best' => false]);

            if (! $reply->is_best) {
                $discussion->replies()->whereKey($reply->id)->update(['is_best' => true]);
            }
        });

        return back();
    }

    /**
     * The class and subject pairs this user can discuss in.
     *
     * @return Collection<int, object{key: string, class_id: int, subject_id: int, label: string}>
     */
    private function roomsFor(User $user): Collection
    {
        $classId = $user->studentClassId();

        if ($user->isStudent() && $classId === null) {
            return collect();
        }

        return TeachingAssignment::query()
            ->when(
                $user->isTeacher(),
                fn ($query) => $query->where('teacher_id', $user->id),
                fn ($query) => $query->where('class_id', $classId),
            )
            ->inActiveYear()
            ->with(['class:id,name', 'subject:id,name'])
            ->get()
            ->map(fn (TeachingAssignment $assignment): object => (object) [
                'key' => "{$assignment->class_id}:{$assignment->subject_id}",
                'class_id' => (int) $assignment->class_id,
                'subject_id' => (int) $assignment->subject_id,
                'label' => $user->isTeacher()
                    ? "{$assignment->class->name} · {$assignment->subject->name}"
                    : $assignment->subject->name,
            ])
            ->unique('key')
            ->sortBy('label')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(User $user, string $view, array $data): View
    {
        return view($view, $data + [
            'layout' => "{$user->role}.layouts.dashboard",
            'routePrefix' => "{$user->role}.discussions",
        ]);
    }

    private function storeImage(Request $request): ?string
    {
        return $request->file('image')?->store(Discussion::IMAGE_DIRECTORY, 'local');
    }

    private function ensureReplyBelongsTo(Discussion $discussion, DiscussionReply $reply): void
    {
        abort_unless((int) $reply->discussion_id === (int) $discussion->id, 404);
    }
}
