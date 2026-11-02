<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\QuizFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    /** @use HasFactory<QuizFactory> */
    use HasFactory, LogsActivity;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const MAX_SCORE = 100;

    protected $fillable = [
        'subject_id',
        'class_id',
        'teacher_id',
        'title',
        'instructions',
        'opens_at',
        'closes_at',
        'duration_minutes',
        'shuffles',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'duration_minutes' => 'integer',
            'shuffles' => 'boolean',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position')->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * Quizzes a student can reach: open right now, or already attempted so the score stays visible.
     */
    public function scopeVisibleToStudent(Builder $query, User $student): Builder
    {
        return $query->published()
            ->where('class_id', $student->studentClassId())
            ->where(function (Builder $query) use ($student): void {
                $query->where(fn (Builder $query) => $query->where('opens_at', '<=', now())->where('closes_at', '>=', now()))
                    ->orWhereHas('attempts', fn (Builder $query) => $query->where('student_id', $student->id));
            });
    }

    /**
     * The teacher marked this quiz ready. Whether it is live follows from the clock, so opening and closing need no background job.
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isOpen(): bool
    {
        return $this->isPublished() && now()->between($this->opens_at, $this->closes_at);
    }

    public function hasClosed(): bool
    {
        return $this->closes_at->isPast();
    }

    /**
     * Questions, the class, and publishing are locked once a student has started, so every attempt is graded alike.
     */
    public function hasAttempts(): bool
    {
        return $this->attempts()->exists();
    }

    public function availabilityLabel(): string
    {
        return match (true) {
            ! $this->isPublished(), $this->hasClosed() => 'Draf',
            $this->isOpen() => 'Dibuka',
            default => 'Terjadwal',
        };
    }

    public function availabilityBadgeClass(): string
    {
        return match (true) {
            ! $this->isPublished(), $this->hasClosed() => 'text-bg-secondary',
            $this->isOpen() => 'text-bg-success',
            default => 'text-bg-primary',
        };
    }

    public function activityLabel(): string
    {
        return 'kuis';
    }
}
