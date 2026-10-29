<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    use HasFactory;

    public const STATUS_NOT_SUBMITTED = 'not_submitted';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_LATE = 'late';

    public const STATUS_GRADED = 'graded';

    protected $fillable = [
        'assignment_id',
        'student_id',
        'file_path',
        'file_original_name',
        'file_mime',
        'file_size',
        'text_response',
        'submitted_at',
        'score',
        'feedback',
        'graded_at',
        'graded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'submitted_at' => 'datetime',
            'score' => 'integer',
            'graded_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function isGraded(): bool
    {
        return $this->graded_at !== null || $this->score !== null;
    }

    public function statusKey(): string
    {
        if ($this->isGraded()) {
            return self::STATUS_GRADED;
        }

        $dueAt = $this->assignment?->due_at;

        if ($dueAt !== null && $this->submitted_at->gt($dueAt)) {
            return self::STATUS_LATE;
        }

        return self::STATUS_SUBMITTED;
    }

    public function statusLabel(): string
    {
        return self::labelFor($this->statusKey());
    }

    public function statusBadgeClass(): string
    {
        return self::badgeClassFor($this->statusKey());
    }

    public function hasFile(): bool
    {
        return filled($this->file_path);
    }

    public function readableFileSize(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes <= 0) {
            return '-';
        }

        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1, ',', '.').' MB';
        }

        return number_format($bytes / 1024, 0, ',', '.').' KB';
    }

    public static function labelFor(string $status): string
    {
        return match ($status) {
            self::STATUS_SUBMITTED => 'Dikumpulkan',
            self::STATUS_LATE => 'Terlambat',
            self::STATUS_GRADED => 'Sudah Dinilai',
            default => 'Belum Dikumpulkan',
        };
    }

    public static function badgeClassFor(string $status): string
    {
        return match ($status) {
            self::STATUS_SUBMITTED => 'text-bg-primary',
            self::STATUS_LATE => 'text-bg-warning',
            self::STATUS_GRADED => 'text-bg-success',
            default => 'text-bg-secondary',
        };
    }
}
