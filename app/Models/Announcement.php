<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory, LogsActivity;

    public const VISIBILITY_ALL = 'all';

    public const VISIBILITY_ROLE = 'role';

    public const VISIBILITY_CLASS = 'class';

    protected $fillable = [
        'title',
        'content',
        'visibility',
        'target_role',
        'class_id',
        'created_by',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
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
            });

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
                $classIds = $user->teachingAssignments()->pluck('class_id')->unique();

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
