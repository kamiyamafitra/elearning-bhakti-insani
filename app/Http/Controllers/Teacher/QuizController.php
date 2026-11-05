<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveQuizRequest;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $quizzes = Quiz::query()
            ->where('teacher_id', $user->id)
            ->whereHas('class', fn ($query) => $query->inActiveYear())
            ->with(['subject:id,code,name', 'class:id,name'])
            ->withCount(['questions', 'attempts'])
            ->withAvg(['attempts as average_score' => fn ($query) => $query->whereNotNull('submitted_at')], 'score')
            ->latest('opens_at')
            ->latest('id')
            ->get();

        return view('teacher.quizzes.index', [
            'teacher' => $user->teacherProfile,
            'quizzes' => $quizzes,
            'subjects' => Subject::query()->forTeacher((int) $user->id)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        $teachings = $this->teachingsFor($request->user());

        abort_if($teachings->isEmpty(), 404);

        return view('teacher.quizzes.create', [
            'teachings' => $teachings,
            'quiz' => new Quiz(['duration_minutes' => 30]),
        ]);
    }

    public function store(SaveQuizRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $quiz = Quiz::create([
            ...$this->quizAttributes($validated),
            ...$this->teachingAttributes($request->user(), $validated['teaching']),
            'teacher_id' => $request->user()->id,
            'status' => Quiz::STATUS_DRAFT,
        ]);

        return redirect()->route('teacher.quizzes.show', $quiz)->with('success', 'Draf berhasil dibuat. Tambahkan soal, lalu jadwalkan.');
    }

    public function show(Quiz $quiz): View
    {
        $this->authorize('view', $quiz);

        QuizAttempt::finalizeExpired([$quiz->id]);

        $quiz->load(['subject:id,code,name', 'class:id,name', 'questions.options']);
        $attempts = $quiz->attempts()->withCount('answers')->get()->keyBy('student_id');

        $studentIds = StudentProfile::where('class_id', $quiz->class_id)->pluck('user_id')->merge($attempts->keys())->unique();
        $students = User::with('studentProfile:id,user_id,nis')->whereIn('id', $studentIds)->orderBy('name')->get(['id', 'name']);
        $leaderboard = $this->buildLeaderboard($students, $attempts);

        return view('teacher.quizzes.show', [
            'quiz' => $quiz,
            'leaderboard' => $leaderboard,
            'attempts' => $attempts,
            'averageScore' => $attempts->whereNotNull('submitted_at')->avg('score'),
        ]);
    }

    public function edit(Request $request, Quiz $quiz): View
    {
        $this->authorize('update', $quiz);

        return view('teacher.quizzes.edit', [
            'teachings' => $this->teachingsFor($request->user()),
            'quiz' => $quiz,
            'isLocked' => $quiz->hasAttempts(),
        ]);
    }

    public function update(SaveQuizRequest $request, Quiz $quiz): RedirectResponse
    {
        $this->authorize('update', $quiz);

        $validated = $request->validated();
        $attributes = $this->quizAttributes($validated);

        if (! $quiz->hasAttempts()) {
            $attributes += $this->teachingAttributes($request->user(), $validated['teaching']);
        }

        $quiz->update($attributes);

        return redirect()->route('teacher.quizzes.show', $quiz)->with('success', 'Perubahan berhasil disimpan.');
    }

    public function publish(Quiz $quiz): RedirectResponse
    {
        $this->authorize('update', $quiz);

        if ($quiz->isPublished()) {
            if ($quiz->hasAttempts()) {
                return back()->with('error', 'Tidak dapat dikembalikan ke draf karena siswa sudah mulai mengerjakan.');
            }

            $quiz->update(['status' => Quiz::STATUS_DRAFT]);

            return back()->with('success', 'Jadwal dibatalkan. Kuis tidak akan terbuka untuk siswa.');
        }

        if (! $quiz->questions()->exists()) {
            return back()->with('error', 'Tambahkan minimal satu soal sebelum menjadwalkan.');
        }

        if ($quiz->hasClosed()) {
            return back()->with('error', 'Waktu ditutup sudah lewat. Ubah jadwal terlebih dahulu.');
        }

        $quiz->update(['status' => Quiz::STATUS_PUBLISHED]);

        return back()->with('success', 'Kuis terjadwal dan otomatis terbuka untuk siswa pada '.$quiz->opens_at->format('d-m-Y H:i').'.');
    }

    public function destroy(Quiz $quiz): RedirectResponse
    {
        $this->authorize('delete', $quiz);

        if ($quiz->hasAttempts()) {
            return back()->with('error', 'Tidak dapat dihapus karena sudah ada siswa yang mengerjakan.');
        }

        $imagePaths = $quiz->questions()->whereNotNull('image_path')->pluck('image_path')->all();

        $quiz->delete();
        Storage::disk('local')->delete($imagePaths);

        return redirect()->route('teacher.quizzes.index')->with('success', 'Berhasil dihapus.');
    }

    /**
     * Rank finished students by score, earliest finisher first on a tie; tied scores share a rank.
     * Students still working come next, followed by those who have not started.
     *
     * @param  Collection<int, User>  $students
     * @param  Collection<int, QuizAttempt>  $attempts
     * @return Collection<int, array{student: User, attempt: ?QuizAttempt, rank: ?int}>
     */
    private function buildLeaderboard(Collection $students, Collection $attempts): Collection
    {
        $rows = $students->map(fn (User $student) => ['student' => $student, 'attempt' => $attempts->get($student->id), 'rank' => null]);
        [$finished, $unfinished] = $rows->partition(fn (array $row) => $row['attempt']?->isSubmitted() ?? false);

        $ranked = $finished
            ->sortBy([
                fn (array $first, array $second) => $second['attempt']->score <=> $first['attempt']->score,
                fn (array $first, array $second) => $first['attempt']->submitted_at <=> $second['attempt']->submitted_at,
            ])
            ->values();

        foreach ($ranked as $index => $row) {
            $previous = $ranked[$index - 1] ?? null;
            $row['rank'] = $previous !== null && $previous['attempt']->score === $row['attempt']->score
                ? $previous['rank']
                : $index + 1;
            $ranked[$index] = $row;
        }

        return $ranked->concat($unfinished->sortByDesc(fn (array $row) => $row['attempt'] !== null)->values());
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function quizAttributes(array $validated): array
    {
        return [
            'title' => $validated['title'],
            'instructions' => $validated['instructions'] ?? null,
            'opens_at' => $validated['opens_at'],
            'closes_at' => $validated['closes_at'],
            'duration_minutes' => $validated['duration_minutes'],
            'shuffles' => (bool) ($validated['shuffles'] ?? false),
        ];
    }

    /**
     * Resolve a "subject-class" choice, accepting only pairs this teacher actually teaches.
     *
     * @return array{subject_id: int, class_id: int}
     */
    private function teachingAttributes(User $teacher, string $teaching): array
    {
        [$subjectId, $classId] = array_map('intval', explode('-', $teaching));

        $isTaught = $this->teachingsFor($teacher)->contains(
            fn (TeachingAssignment $assignment) => (int) $assignment->subject_id === $subjectId && (int) $assignment->class_id === $classId,
        );

        if (! $isTaught) {
            throw ValidationException::withMessages(['teaching' => 'Pilih mata pelajaran dan kelas yang Anda ampu.']);
        }

        return ['subject_id' => $subjectId, 'class_id' => $classId];
    }

    /**
     * @return Collection<int, TeachingAssignment>
     */
    private function teachingsFor(User $teacher): Collection
    {
        return TeachingAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->inActiveYear()
            ->with(['subject:id,name', 'class:id,name'])
            ->get()
            ->unique(fn (TeachingAssignment $assignment) => "{$assignment->subject_id}-{$assignment->class_id}")
            ->values();
    }
}
