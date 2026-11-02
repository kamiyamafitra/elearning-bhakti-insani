<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    use HasFactory, LogsActivity;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'subject_id',
        'class_id',
        'teacher_id',
        'title',
        'instructions',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime',
        'attachment_size',
        'due_at',
        'max_score',
        'status',
        'allows_text_response',
        'allows_resubmission',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'attachment_size' => 'integer',
            'due_at' => 'datetime',
            'max_score' => 'integer',
            'allows_text_response' => 'boolean',
            'allows_resubmission' => 'boolean',
            'published_at' => 'datetime',
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

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /**
     * Limit assignments to the ones that have been published.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where(function (Builder $query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && ($this->published_at === null || $this->published_at->lte(now()));
    }

    public function isPastDue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast();
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    public function statusFor(?AssignmentSubmission $submission): string
    {
        if ($submission === null) {
            return AssignmentSubmission::STATUS_NOT_SUBMITTED;
        }

        return $submission->statusKey();
    }

    public function statusLabelFor(?AssignmentSubmission $submission): string
    {
        return AssignmentSubmission::labelFor($this->statusFor($submission));
    }

    public function statusBadgeClassFor(?AssignmentSubmission $submission): string
    {
        return AssignmentSubmission::badgeClassFor($this->statusFor($submission));
    }

    public function readableAttachmentSize(): string
    {
        $bytes = (int) $this->attachment_size;

        if ($bytes <= 0) {
            return '-';
        }

        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1, ',', '.').' MB';
        }

        return number_format($bytes / 1024, 0, ',', '.').' KB';
    }

    public function activityLabel(): string
    {
        return 'tugas';
    }
}
