<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ClassSchedule;
use Illuminate\Database\Eloquent\Builder;

trait FiltersSubjectIndex
{
    /**
     * Limit the subject query to a name or code search.
     */
    protected function applySubjectSearch(Builder $query, ?string $search): void
    {
        $query->when($search, function (Builder $query, string $search): void {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        });
    }

    /**
     * Apply the shared subject card ordering.
     */
    protected function applySubjectSorting(
        Builder $query,
        string $sort,
        ?int $classId = null,
        ?int $teacherId = null,
    ): void {
        match ($sort) {
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'schedule_asc' => $this->orderByFirstSchedule($query, $classId, $teacherId),
            default => $query->orderBy('name')->orderBy('id'),
        };
    }

    /**
     * Order subjects by their first schedule, keeping unscheduled subjects last.
     */
    private function orderByFirstSchedule(Builder $query, ?int $classId, ?int $teacherId): void
    {
        $day = $this->firstScheduledColumnSubquery('day_of_week', $classId, $teacherId);
        $time = $this->firstScheduledColumnSubquery('start_time', $classId, $teacherId);

        $query->orderByRaw('('.$day->toSql().') IS NULL', $day->getBindings())
            ->orderBy($day)
            ->orderBy($time)
            ->orderBy('name');
    }

    /**
     * Build a subquery that selects the first scheduled column for a subject.
     */
    private function firstScheduledColumnSubquery(string $column, ?int $classId, ?int $teacherId): Builder
    {
        return ClassSchedule::query()
            ->whereColumn('class_schedules.subject_id', 'subjects.id')
            ->when($classId !== null, fn (Builder $query) => $query->where('class_schedules.class_id', $classId))
            ->when($teacherId !== null, fn (Builder $query) => $query->whereIn(
                'class_schedules.class_id',
                fn ($query) => $query
                    ->select('class_id')
                    ->from('teaching_assignments')
                    ->where('teacher_id', $teacherId),
            ))
            ->select('class_schedules.'.$column)
            ->orderBy('class_schedules.day_of_week')
            ->orderBy('class_schedules.start_time')
            ->limit(1);
    }
}
