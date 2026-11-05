<?php

namespace App\Models;

use App\Models\Concerns\HasPrivateImage;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory, HasPrivateImage, LogsActivity, Prunable;

    public const IMAGE_DIRECTORY = 'announcement-images';

    /**
     * Ended announcements are deleted, image included, this many months after their end time.
     */
    public const DELETE_AFTER_END_MONTHS = 6;

    public const VISIBILITY_ALL = 'all';

    public const VISIBILITY_ROLE = 'role';

    public const VISIBILITY_CLASS = 'class';

    protected $fillable = [
        'title',
        'content',
        'image_path',
        'visibility',
        'target_role',
        'class_id',
        'created_by',
        'is_published',
        'published_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Visible announcements published since the user last opened the announcement list,
     * leaving out the ones they posted themselves.
     */
    public function scopeUnreadBy(Builder $query, User $user): Builder
    {
        return $query->visibleTo($user)
            ->where(fn (Builder $query) => $query->whereNull('created_by')->orWhere('created_by', '!=', $user->id))
            ->when($user->announcements_seen_at, fn (Builder $query, $seenAt) => $query->where(
                fn (Builder $query) => $query->where('published_at', '>', $seenAt)
                    ->orWhere(fn (Builder $query) => $query->whereNull('published_at')->where('created_at', '>', $seenAt))
            ));
    }

    /**
     * Picked up by the daily model:prune run. Deleting one model at a time removes each image file too.
     */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now()->subMonths(self::DELETE_AFTER_END_MONTHS));
    }

    /**
     * Status label for the admin pages.
     */
    public function statusLabel(): string
    {
        return match (true) {
            ! $this->is_published => 'Diturunkan',
            $this->isExpired() => 'Berakhir',
            $this->published_at?->isFuture() ?? false => 'Terjadwal',
            default => 'Dipublikasikan',
        };
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Hide announcements whose end time has passed.
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    /**
     * Limit announcements to the ones the given user is allowed to read.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where('is_published', true)
            ->where(function (Builder $query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->notExpired();

        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user) {
            $query->where('visibility', self::VISIBILITY_ALL)
                ->orWhere(function (Builder $query) use ($user) {
                    $query->where('visibility', self::VISIBILITY_ROLE)
                        ->where('target_role', $user->role);
                });

            if ($user->isStudent()) {
                $classId = $user->studentProfile?->class_id;

                if ($classId) {
                    $query->orWhere(function (Builder $query) use ($classId) {
                        $query->where('visibility', self::VISIBILITY_CLASS)
                            ->where('class_id', $classId);
                    });
                }
            }

            if ($user->isTeacher()) {
                $classIds = $user->teachingAssignments()->inActiveYear()->pluck('class_id')->unique();

                if ($classIds->isNotEmpty()) {
                    $query->orWhere(function (Builder $query) use ($classIds) {
                        $query->where('visibility', self::VISIBILITY_CLASS)
                            ->whereIn('class_id', $classIds);
                    });
                }
            }
        });
    }

    public function activityLabel(): string
    {
        return 'pengumuman';
    }
}
