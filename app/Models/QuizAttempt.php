<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuizAttempt extends Model
{
    /**
     * Extra seconds for the final submission to arrive after the countdown reaches zero.
     */
    public const SUBMIT_GRACE_SECONDS = 30;

    protected $fillable = [
        'quiz_id',
        'student_id',
        'question_order',
        'current_step',
        'started_at',
        'deadline_at',
        'submitted_at',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'question_order' => 'array',
            'current_step' => 'integer',
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score' => 'float',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    /**
     * Attempts whose time ran out without being submitted, for example because the browser was closed.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNull('submitted_at')->where('deadline_at', '<=', now());
    }

    /**
     * Grade every expired attempt in the given quizzes using the answers saved so far.
     *
     * @param  iterable<int>  $quizIds
     */
    public static function finalizeExpired(iterable $quizIds): void
    {
        static::query()
            ->whereIn('quiz_id', $quizIds)
            ->expired()
            ->get()
            ->each(fn (QuizAttempt $attempt) => $attempt->finalize());
    }

    /**
     * Fix the order of questions and options for one student.
     *
     * @return list<array{question: int, options: list<int>}>
     */
    public static function buildQuestionOrder(Quiz $quiz): array
    {
        $questions = $quiz->questions()->with('options:id,quiz_question_id,position')->get(['id', 'quiz_id', 'position']);

        return $questions
            ->when($quiz->shuffles, fn (Collection $questions) => $questions->shuffle())
            ->map(fn (QuizQuestion $question) => [
                'question' => $question->id,
                'options' => $question->options
                    ->when($quiz->shuffles, fn (Collection $options) => $options->shuffle())
                    ->pluck('id')
                    ->all(),
            ])
            ->values()
            ->all();
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isActive(): bool
    {
        return ! $this->isSubmitted() && $this->deadline_at->isFuture();
    }

    public function acceptsFinalSubmission(): bool
    {
        return ! $this->isSubmitted() && now()->lte($this->deadline_at->copy()->addSeconds(self::SUBMIT_GRACE_SECONDS));
    }

    public function remainingSeconds(): int
    {
        return max(0, (int) now()->diffInSeconds($this->deadline_at, false));
    }

    /**
     * The one question the student may see and answer; earlier ones are closed for good.
     *
     * @return array{question: int, options: list<int>}
     */
    public function currentEntry(): array
    {
        return $this->question_order[$this->current_step];
    }

    public function isOnLastQuestion(): bool
    {
        return $this->current_step >= count($this->question_order) - 1;
    }

    /**
     * Correct answers stay hidden until the quiz closes, so nobody can pass them to classmates still working.
     */
    public function canReviewAnswers(Quiz $quiz): bool
    {
        return $this->isSubmitted() && $quiz->hasClosed();
    }

    public function includesQuestion(int $questionId): bool
    {
        return collect($this->question_order)->contains(fn (array $entry) => $entry['question'] === $questionId);
    }

    public function isCurrentQuestion(int $questionId): bool
    {
        return $this->currentEntry()['question'] === $questionId;
    }

    public function isCurrentOption(int $questionId, int $optionId): bool
    {
        return $this->isCurrentQuestion($questionId) && in_array($optionId, $this->currentEntry()['options'], true);
    }

    /**
     * Move forward one question. Only the request still on the given step counts, so a double click cannot skip a question.
     */
    public function advanceFrom(int $step): void
    {
        if ($step === $this->current_step && ! $this->isOnLastQuestion()) {
            static::query()->whereKey($this->id)->where('current_step', $step)->increment('current_step');
        }
    }

    public function saveAnswer(int $questionId, int $optionId): void
    {
        $this->answers()->updateOrCreate(
            ['quiz_question_id' => $questionId],
            ['quiz_option_id' => $optionId],
        );
    }

    /**
     * Grade the attempt on a 0-100 scale, with every question worth the same. Safe to call twice: only the first call counts.
     */
    public function finalize(): void
    {
        DB::transaction(function (): void {
            $attempt = static::query()->lockForUpdate()->findOrFail($this->id);

            if ($attempt->isSubmitted()) {
                return;
            }

            $totalQuestions = count($attempt->question_order);
            $correctAnswers = $attempt->answers()
                ->whereHas('option', fn (Builder $query) => $query->where('is_correct', true))
                ->count();

            $attempt->update([
                'submitted_at' => now()->min($attempt->deadline_at),
                'score' => $totalQuestions > 0 ? round($correctAnswers / $totalQuestions * Quiz::MAX_SCORE, 2) : 0,
            ]);
        });

        $this->refresh();
    }
}
