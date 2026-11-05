<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class);
    }

    public function learningMaterials(): HasMany
    {
        return $this->hasMany(LearningMaterial::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Limit subjects to the ones linked to a class through a schedule or teaching assignment.
     */
    public function scopeForClass(Builder $query, int $classId): Builder
    {
        return $query->where(function (Builder $query) use ($classId) {
            $query->whereHas('schedules', fn (Builder $query) => $query->where('class_id', $classId))
                ->orWhereHas('teachingAssignments', fn (Builder $query) => $query->where('class_id', $classId));
        });
    }

    /**
     * Limit subjects to the ones assigned to a teacher in the active academic year.
     */
    public function scopeForTeacher(Builder $query, int $teacherId): Builder
    {
        return $query->whereHas('teachingAssignments', fn (Builder $query) => $query->where('teacher_id', $teacherId)->inActiveYear());
    }

    public function activityLabel(): string
    {
        return 'mata pelajaran';
    }
}
