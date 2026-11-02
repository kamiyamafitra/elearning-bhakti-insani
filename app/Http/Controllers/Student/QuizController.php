<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuizController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('studentProfile.class');
        $classId = $user->studentClassId();

        $quizzes = Quiz::visibleToStudent($user)
            ->with('subject:id,code,name')
            ->withCount('questions')
            ->latest('opens_at')
            ->latest('id')
            ->get();

        QuizAttempt::finalizeExpired($quizzes->pluck('id'));

        $subjects = Subject::query()
            ->when(
                $classId !== null,
                fn ($query) => $query->forClass($classId),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('student.quizzes.index', [
            'student' => $user->studentProfile,
            'classroom' => $user->studentProfile?->class,
            'subjects' => $subjects,
            'quizzes' => $quizzes,
            'attempts' => QuizAttempt::where('student_id', $user->id)->whereIn('quiz_id', $quizzes->pluck('id'))->get()->keyBy('quiz_id'),
        ]);
    }

    public function show(Request $request, Quiz $quiz): View
    {
        $this->authorize('view', $quiz);

        QuizAttempt::finalizeExpired([$quiz->id]);
        $quiz->load('subject:id,code,name')->loadCount('questions');

        $attempt = $this->attemptOf($request->user(), $quiz);

        return view('student.quizzes.show', [
            'quiz' => $quiz,
            'attempt' => $attempt,
            'review' => $attempt?->canReviewAnswers($quiz) ? $this->buildReview($attempt) : null,
        ]);
    }

    public function start(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorize('view', $quiz);

        $attempt = $this->attemptOf($request->user(), $quiz);

        if ($attempt === null) {
            if (! $quiz->isOpen()) {
                return redirect()->route('student.quizzes.show', $quiz)->with('error', 'Waktu pengerjaan sedang tidak dibuka.');
            }

            $quiz->attempts()->createOrFirst(['student_id' => $request->user()->id], [
                'question_order' => QuizAttempt::buildQuestionOrder($quiz),
                'started_at' => now(),
                'deadline_at' => now()->addMinutes($quiz->duration_minutes)->min($quiz->closes_at),
            ]);
        }

        return redirect()->route('student.quizzes.attempt', $quiz);
    }

    public function attempt(Request $request, Quiz $quiz): Response|RedirectResponse
    {
        $this->authorize('view', $quiz);

        $attempt = $this->attemptOf($request->user(), $quiz);

        if ($attempt === null || ! $attempt->isActive()) {
            return redirect()->route('student.quizzes.show', $quiz);
        }

        $entry = $attempt->currentEntry();
        $question = QuizQuestion::with('options:id,quiz_question_id,text')->findOrFail($entry['question'], ['id', 'question', 'image_path']);
        $savedAnswers = $attempt->answers()->pluck('quiz_option_id', 'quiz_question_id');

        return response()
            ->view('student.quizzes.attempt', [
                'quiz' => $quiz,
                'attempt' => $attempt,
                'question' => $question,
                'options' => $question->options->sortBy(fn ($option) => array_search($option->id, $entry['options'], true)),
                'selectedOptionId' => $savedAnswers->get($question->id),
                'answeredCount' => $savedAnswers->count(),
                'totalQuestions' => count($attempt->question_order),
            ])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Images are only available for the student's current question or their review, so later questions stay private.
     */
    public function questionImage(Request $request, Quiz $quiz, QuizQuestion $question): BinaryFileResponse
    {
        $this->authorize('view', $quiz);

        $attempt = $this->attemptOf($request->user(), $quiz);

        $isAnswering = $attempt?->isActive() && $attempt->isCurrentQuestion($question->id);
        $isReviewing = $attempt?->canReviewAnswers($quiz) && $attempt->includesQuestion($question->id);

        abort_unless($isAnswering || $isReviewing, 404);

        return $question->imageResponse();
    }

    /**
     * Autosave one answer, so nothing is lost if the browser closes before submitting.
     */
    public function saveAnswer(Request $request, Quiz $quiz): JsonResponse
    {
        $this->authorize('view', $quiz);

        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'option_id' => ['required', 'integer'],
        ]);

        $attempt = $this->attemptOf($request->user(), $quiz);

        abort_unless($attempt?->isActive(), 409, 'Waktu pengerjaan sudah habis.');
        abort_unless($attempt->isCurrentOption($validated['question_id'], $validated['option_id']), 422);

        $attempt->saveAnswer($validated['question_id'], $validated['option_id']);

        return response()->json(['saved' => true]);
    }

    /**
     * Save the current answer and move on; there is deliberately no way back.
     */
    public function next(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorize('view', $quiz);

        $validated = $request->validate([
            'step' => ['required', 'integer'],
            'answers' => ['nullable', 'array'],
            'answers.*' => ['integer'],
        ]);

        $attempt = $this->attemptOf($request->user(), $quiz);

        abort_if($attempt === null, 404);

        if (! $attempt->isActive()) {
            $attempt->finalize();

            return redirect()->route('student.quizzes.show', $quiz);
        }

        if ((int) $validated['step'] === $attempt->current_step) {
            $this->saveCurrentAnswer($attempt, $validated['answers'] ?? []);
            $attempt->advanceFrom((int) $validated['step']);
        }

        return redirect()->route('student.quizzes.attempt', $quiz);
    }

    public function submit(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorize('view', $quiz);

        $validated = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*' => ['integer'],
        ]);

        $attempt = $this->attemptOf($request->user(), $quiz);

        abort_if($attempt === null, 404);

        if ($attempt->acceptsFinalSubmission()) {
            $this->saveCurrentAnswer($attempt, $validated['answers'] ?? []);
        }

        $attempt->finalize();

        return redirect()->route('student.quizzes.show', $quiz)->with('success', 'Jawaban berhasil dikumpulkan.');
    }

    /**
     * @param  array<int|string, int|string>  $answers
     */
    private function saveCurrentAnswer(QuizAttempt $attempt, array $answers): void
    {
        foreach ($answers as $questionId => $optionId) {
            if ($attempt->isCurrentOption((int) $questionId, (int) $optionId)) {
                $attempt->saveAnswer((int) $questionId, (int) $optionId);
            }
        }
    }

    /**
     * List the questions in the order this student saw them, with the answer they gave.
     *
     * @return Collection<int, array{question: QuizQuestion, options: Collection<int, QuizOption>, selected_option_id: ?int, status: string}>
     */
    private function buildReview(QuizAttempt $attempt): Collection
    {
        $questions = QuizQuestion::with('options')
            ->whereIn('id', collect($attempt->question_order)->pluck('question'))
            ->get()
            ->keyBy('id');
        $answers = $attempt->answers()->pluck('quiz_option_id', 'quiz_question_id');

        return collect($attempt->question_order)
            ->filter(fn (array $entry) => $questions->has($entry['question']))
            ->values()
            ->map(function (array $entry) use ($questions, $answers): array {
                $question = $questions[$entry['question']];
                $selectedOptionId = $answers->get($question->id);

                return [
                    'question' => $question,
                    'options' => $question->options->sortBy(fn (QuizOption $option) => array_search($option->id, $entry['options'], true))->values(),
                    'selected_option_id' => $selectedOptionId,
                    'status' => match (true) {
                        $selectedOptionId === null => 'unanswered',
                        (bool) $question->options->firstWhere('id', $selectedOptionId)?->is_correct => 'correct',
                        default => 'wrong',
                    },
                ];
            });
    }

    private function attemptOf(User $student, Quiz $quiz): ?QuizAttempt
    {
        return $quiz->attempts()->where('student_id', $student->id)->first();
    }
}
