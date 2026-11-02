<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\StoresLmsFiles;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveQuizQuestionRequest;
use App\Http\Requests\StoreQuizQuestionsRequest;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuizQuestionController extends Controller
{
    use StoresLmsFiles;

    private const IMAGE_DIRECTORY = 'lms/quiz-questions';

    public function create(Quiz $quiz): View|RedirectResponse
    {
        $this->authorize('update', $quiz);

        if ($quiz->hasAttempts()) {
            return $this->lockedResponse($quiz);
        }

        return view('teacher.quizzes.questions-create', [
            'quiz' => $quiz,
            'maxQuestions' => StoreQuizQuestionsRequest::MAX_QUESTIONS,
            'maxUploadBytes' => ini_parse_quantity((string) ini_get('post_max_size')),
        ]);
    }

    public function store(StoreQuizQuestionsRequest $request, Quiz $quiz): RedirectResponse
    {
        $this->authorize('update', $quiz);

        if ($quiz->hasAttempts()) {
            return $this->lockedResponse($quiz);
        }

        $questions = collect($request->validated('questions'))
            ->map(fn (array $question) => [
                ...$question,
                'image_path' => isset($question['image']) ? $question['image']->store(self::IMAGE_DIRECTORY, 'local') : null,
            ]);

        DB::transaction(function () use ($questions, $quiz): void {
            $position = (int) $quiz->questions()->max('position');

            foreach ($questions as $attributes) {
                $question = $quiz->questions()->create([
                    'question' => $attributes['question'],
                    'image_path' => $attributes['image_path'],
                    'position' => ++$position,
                ]);

                $this->saveOptions($question, $attributes['options'], (int) $attributes['correct_option']);
            }
        });

        return redirect()->route('teacher.quizzes.show', $quiz)->with('success', $questions->count().' soal berhasil ditambahkan.');
    }

    public function edit(Quiz $quiz, QuizQuestion $question): View|RedirectResponse
    {
        $this->authorize('update', $quiz);
        abort_unless((int) $question->quiz_id === (int) $quiz->id, 404);

        if ($quiz->hasAttempts()) {
            return $this->lockedResponse($quiz);
        }

        $options = $question->options;

        return view('teacher.quizzes.question', [
            'quiz' => $quiz,
            'question' => $question,
            'optionTexts' => $options->pluck('text')->all(),
            'correctOption' => $options->search(fn ($option) => $option->is_correct),
        ]);
    }

    public function update(SaveQuizQuestionRequest $request, Quiz $quiz, QuizQuestion $question): RedirectResponse
    {
        $this->authorize('update', $quiz);
        abort_unless((int) $question->quiz_id === (int) $quiz->id, 404);

        if ($quiz->hasAttempts()) {
            return $this->lockedResponse($quiz);
        }

        $previousImagePath = $question->image_path;
        $imagePath = match (true) {
            $request->hasFile('image') => $request->file('image')->store(self::IMAGE_DIRECTORY, 'local'),
            $request->boolean('remove_image') => null,
            default => $previousImagePath,
        };

        DB::transaction(function () use ($request, $question, $imagePath): void {
            $question->update(['image_path' => $imagePath] + $request->safe()->only(['question']));
            $question->options()->delete();

            $this->saveOptions($question, $request->validated('options'), $request->integer('correct_option'));
        });

        if ($previousImagePath !== $imagePath) {
            $this->deleteLmsFile($previousImagePath);
        }

        return redirect()->route('teacher.quizzes.show', $quiz)->with('success', 'Soal berhasil diperbarui.');
    }

    public function destroy(Quiz $quiz, QuizQuestion $question): RedirectResponse
    {
        $this->authorize('update', $quiz);
        abort_unless((int) $question->quiz_id === (int) $quiz->id, 404);

        if ($quiz->hasAttempts()) {
            return $this->lockedResponse($quiz);
        }

        if ($quiz->isPublished() && $quiz->questions()->count() === 1) {
            return back()->with('error', 'Soal terakhir tidak dapat dihapus selama kuis sudah dijadwalkan.');
        }

        $question->delete();
        $this->deleteLmsFile($question->image_path);

        return back()->with('success', 'Soal berhasil dihapus.');
    }

    public function image(Quiz $quiz, QuizQuestion $question): BinaryFileResponse
    {
        $this->authorize('view', $quiz);
        abort_unless((int) $question->quiz_id === (int) $quiz->id, 404);

        return $question->imageResponse();
    }

    /**
     * @param  array<int, ?string>  $options
     */
    private function saveOptions(QuizQuestion $question, array $options, int $correctOption): void
    {
        $position = 0;

        foreach (array_filter($options, 'filled') as $index => $text) {
            $question->options()->create([
                'text' => $text,
                'is_correct' => $index === $correctOption,
                'position' => ++$position,
            ]);
        }
    }

    private function lockedResponse(Quiz $quiz): RedirectResponse
    {
        return redirect()->route('teacher.quizzes.show', $quiz)->with('error', 'Soal tidak dapat diubah karena siswa sudah mulai mengerjakan.');
    }
}
