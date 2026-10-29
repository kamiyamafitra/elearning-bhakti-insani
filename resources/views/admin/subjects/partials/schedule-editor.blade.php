@php
    $assignmentTeachers = isset($subject)
        ? $subject->teachingAssignments
            ->sortBy('id')
            ->keyBy('class_id')
            ->map(fn ($assignment) => $assignment->teacher_id)
            ->all()
        : [];

    $defaultScheduleRows = isset($subject)
        ? $subject->schedules->map(fn ($schedule) => [
            'id' => $schedule->id,
            'class_id' => $schedule->class_id,
            'day_of_week' => $schedule->day_of_week,
            'start_time' => \App\Models\ClassSchedule::formatTime($schedule->start_time),
            'end_time' => \App\Models\ClassSchedule::formatTime($schedule->end_time),
            'teacher_id' => $assignmentTeachers[$schedule->class_id] ?? null,
        ])->all()
        : [];

    $scheduleRows = old('schedules', $defaultScheduleRows);

    if (empty($scheduleRows)) {
        $scheduleRows = [['id' => null, 'class_id' => '', 'day_of_week' => '', 'start_time' => '', 'end_time' => '', 'teacher_id' => '']];
    }
@endphp

<div class="schedule-editor">
    <div class="schedule-editor__heading">
        <div>
            <h2 class="schedule-editor__title">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                Jadwal Kelas
            </h2>
            <p class="schedule-editor__description">
                Tambahkan satu atau beberapa sesi jadwal untuk mata pelajaran ini, lalu pilih guru pengajar pada setiap baris kelas. Kosongkan daftar jika belum ada jadwal.
            </p>
        </div>
        @unless ($classes->isEmpty())
            <button type="button" class="btn btn-outline-primary schedule-editor__add" data-add-schedule>
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                Tambah Jadwal
            </button>
        @endunless
    </div>

    @if ($classes->isEmpty())
        <div class="alert alert-warning mb-0">
            Belum ada kelas yang tersedia. Tambahkan kelas terlebih dahulu melalui menu Kelas.
        </div>
    @else
        <div class="schedule-editor__rows" data-schedule-rows data-next-index="{{ count($scheduleRows) }}">
            @foreach ($scheduleRows as $index => $scheduleRow)
                @include('admin.subjects.partials.schedule-row', [
                    'index' => $index,
                    'schedule' => $scheduleRow,
                    'classes' => $classes,
                    'teachers' => $teachers,
                ])
            @endforeach
        </div>
    @endif
</div>

<template id="schedule-row-template">
    @include('admin.subjects.partials.schedule-row', [
        'index' => '__INDEX__',
        'schedule' => [],
        'classes' => $classes,
        'teachers' => $teachers,
    ])
</template>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rows = document.querySelector('[data-schedule-rows]');
        const template = document.getElementById('schedule-row-template');
        const addButton = document.querySelector('[data-add-schedule]');

        if (!rows || !template || !addButton) {
            return;
        }

        let nextIndex = Number(rows.dataset.nextIndex || 0);

        addButton.addEventListener('click', function () {
            rows.insertAdjacentHTML(
                'beforeend',
                template.innerHTML.replaceAll('__INDEX__', String(nextIndex))
            );

            nextIndex += 1;

            const addedRow = rows.lastElementChild;
            addedRow?.querySelector('select')?.focus();
        });

        rows.addEventListener('click', function (event) {
            const removeButton = event.target.closest('[data-remove-schedule]');

            if (!removeButton) {
                return;
            }

            removeButton.closest('[data-schedule-row]')?.remove();
        });
    });
</script>
