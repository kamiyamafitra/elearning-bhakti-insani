<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How a teacher turns one class's assignment and quiz results into the report card grade for one subject.
 */
class ReportCardSetting extends Model
{
    use LogsActivity;

    public const int DEFAULT_ASSIGNMENT_WEIGHT = 60;

    public const int DEFAULT_QUIZ_WEIGHT = 40;

    public const int DEFAULT_PASSING_SCORE = 75;

    protected $fillable = [
        'subject_id',
        'class_id',
        'assignment_weight',
        'quiz_weight',
        'passing_score',
    ];

    protected $attributes = [
        'assignment_weight' => self::DEFAULT_ASSIGNMENT_WEIGHT,
        'quiz_weight' => self::DEFAULT_QUIZ_WEIGHT,
        'passing_score' => self::DEFAULT_PASSING_SCORE,
    ];

    protected function casts(): array
    {
        return [
            'assignment_weight' => 'integer',
            'quiz_weight' => 'integer',
            'passing_score' => 'integer',
        ];
    }

    /**
     * The saved setting, or the school defaults when the teacher has not set one yet.
     */
    public static function for(int $subjectId, int $classId): self
    {
        return self::firstOrNew(['subject_id' => $subjectId, 'class_id' => $classId]);
    }

    /**
     * Every subject's setting for one class, keyed by subject id; missing subjects fall back to defaults.
     *
     * @return Collection<int, self>
     */
    public static function forClass(int $classId): Collection
    {
        return self::where('class_id', $classId)->get()->keyBy('subject_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    /**
     * Weighted 0-100 grade. A category with no counted work yet hands its weight to the other one;
     * a category weighted at 0% never counts.
     */
    public function finalScore(?float $assignmentAverage, ?float $quizAverage): ?float
    {
        $parts = array_filter(
            [[$assignmentAverage, $this->assignment_weight], [$quizAverage, $this->quiz_weight]],
            fn (array $part) => $part[0] !== null && $part[1] > 0,
        );

        if ($parts === []) {
            return null;
        }

        $weighted = array_sum(array_map(fn (array $part) => $part[0] * $part[1], $parts));

        return round($weighted / array_sum(array_column($parts, 1)), 1);
    }

    /**
     * Letter grade from the KKM: below it is D, and the range from the KKM to 100 splits evenly into C, B and A.
     */
    public function predicate(float $score): string
    {
        $passing = $this->passing_score;

        if ($score < $passing) {
            return 'D';
        }

        $step = (100 - $passing) / 3;

        return match (true) {
            $score >= $passing + 2 * $step => 'A',
            $score >= $passing + $step => 'B',
            default => 'C',
        };
    }

    /**
     * The lowest score for each letter, for showing the scale next to the table.
     *
     * @return array<string, float>
     */
    public function predicateScale(): array
    {
        $step = (100 - $this->passing_score) / 3;

        return [
            'A' => round($this->passing_score + 2 * $step, 1),
            'B' => round($this->passing_score + $step, 1),
            'C' => (float) $this->passing_score,
        ];
    }

    public function activityLabel(): string
    {
        return 'pengaturan nilai rapor';
    }

    public function activityName(): ?string
    {
        return trim(($this->subject?->name ?? '').' - '.($this->class?->name ?? ''), ' -');
    }
}
