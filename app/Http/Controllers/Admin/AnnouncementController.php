<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuizQuestionsRequest;
use App\Models\Announcement;
use App\Models\Classes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AnnouncementController extends Controller
{
    private const IMAGE_RULES = ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:'.StoreQuizQuestionsRequest::MAX_IMAGE_KILOBYTES];

    private const IMAGE_MESSAGES = [
        'image.file' => 'Gambar gagal diunggah. Coba lagi.',
        'image.mimes' => 'Gambar harus berformat PNG, JPG, atau WEBP.',
        'image.max' => 'Ukuran gambar maksimal 2 MB.',
    ];

    public function index(Request $request): View
    {
        $request->user()->markAnnouncementsSeen();
        $search = trim((string) $request->input('q', ''));

        $announcements = Announcement::with(['creator:id,name', 'class:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.announcements.index', compact('announcements', 'search'));
    }

    public function create(): View
    {
        $classes = Classes::orderBy('name')->get(['id', 'name']);

        return view('admin.announcements.create', compact('classes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'visibility' => ['required', Rule::in([
                Announcement::VISIBILITY_ALL,
                Announcement::VISIBILITY_ROLE,
                Announcement::VISIBILITY_CLASS,
            ])],
            'target_role' => ['nullable', 'required_if:visibility,role', Rule::in(['teacher', 'student'])],
            'class_id' => ['nullable', 'required_if:visibility,class', 'exists:classes,id'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'image' => self::IMAGE_RULES,
            'remove_image' => ['nullable', 'boolean'],
        ], self::IMAGE_MESSAGES);

        // Saving publishes: right away, or at the chosen time when that is still in the future.
        $publishedAt = $validated['published_at'] ?? now();
        $this->ensureEndsAfterStart($validated['expires_at'] ?? null, $publishedAt);

        Announcement::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'visibility' => $validated['visibility'],
            'target_role' => $validated['visibility'] === Announcement::VISIBILITY_ROLE
                ? ($validated['target_role'] ?? null)
                : null,
            'class_id' => $validated['visibility'] === Announcement::VISIBILITY_CLASS
                ? ($validated['class_id'] ?? null)
                : null,
            'created_by' => Auth::id(),
            'is_published' => true,
            'published_at' => $publishedAt,
            'expires_at' => $validated['expires_at'] ?? null,
            'image_path' => $request->file('image')?->store(Announcement::IMAGE_DIRECTORY, 'local'),
        ]);

        return redirect()->route('admin.announcements.index')->with('success', 'Pengumuman berhasil ditambahkan.');
    }

    public function show(Announcement $announcement): View
    {
        $announcement->load(['creator:id,name', 'class:id,name']);

        return view('admin.announcements.show', compact('announcement'));
    }

    public function image(Announcement $announcement): BinaryFileResponse
    {
        return $announcement->imageResponse();
    }

    public function edit(Announcement $announcement): View
    {
        $classes = Classes::orderBy('name')->get(['id', 'name']);

        return view('admin.announcements.edit', compact('announcement', 'classes'));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'visibility' => ['required', Rule::in([
                Announcement::VISIBILITY_ALL,
                Announcement::VISIBILITY_ROLE,
                Announcement::VISIBILITY_CLASS,
            ])],
            'target_role' => ['nullable', 'required_if:visibility,role', Rule::in(['teacher', 'student'])],
            'class_id' => ['nullable', 'required_if:visibility,class', 'exists:classes,id'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'image' => self::IMAGE_RULES,
            'remove_image' => ['nullable', 'boolean'],
        ], self::IMAGE_MESSAGES);

        $publishedAt = $validated['published_at'] ?? now();
        $this->ensureEndsAfterStart($validated['expires_at'] ?? null, $publishedAt);

        $oldImage = $announcement->image_path;
        $imagePath = match (true) {
            $request->hasFile('image') => $request->file('image')->store(Announcement::IMAGE_DIRECTORY, 'local'),
            $request->boolean('remove_image') => null,
            default => $oldImage,
        };

        $announcement->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'visibility' => $validated['visibility'],
            'target_role' => $validated['visibility'] === Announcement::VISIBILITY_ROLE
                ? ($validated['target_role'] ?? null)
                : null,
            'class_id' => $validated['visibility'] === Announcement::VISIBILITY_CLASS
                ? ($validated['class_id'] ?? null)
                : null,
            'is_published' => true,
            'published_at' => $publishedAt,
            'expires_at' => $validated['expires_at'] ?? null,
            'image_path' => $imagePath,
        ]);

        if ($oldImage && $oldImage !== $imagePath) {
            Storage::disk('local')->delete($oldImage);
        }

        return redirect()->route('admin.announcements.index')->with('success', 'Pengumuman berhasil diperbarui.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return redirect()->route('admin.announcements.index')->with('success', 'Pengumuman berhasil dihapus.');
    }

    /**
     * An announcement has to be visible for some time before it ends.
     */
    private function ensureEndsAfterStart(?string $expiresAt, mixed $publishedAt): void
    {
        if ($expiresAt !== null && Carbon::parse($expiresAt)->lte(Carbon::parse($publishedAt ?? now()))) {
            throw ValidationException::withMessages([
                'expires_at' => 'Tanggal berakhir harus setelah tanggal publikasi.',
            ]);
        }
    }
}
