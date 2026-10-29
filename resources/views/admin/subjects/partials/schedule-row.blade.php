@php
    $schedule = $schedule ?? [];
    $rowId = $schedule['id'] ?? null;
    $classId = $schedule['class_id'] ?? '';
    $dayOfWeek = $schedule['day_of_week'] ?? '';
    $startTime = $schedule['start_time'] ?? '';
    $endTime = $schedule['end_time'] ?? '';
    $teacherId = $schedule['teacher_id'] ?? '';
@endphp

<div class="schedule-row" data-schedule-row>
    <input type="hidden" name="schedules[{{ $index }}][id]" value="{{ $rowId }}">

    <div class="schedule-row__field">
        <label class="form-label">Kelas</label>
        <select name="schedules[{{ $index }}][class_id]" class="form-select">
            <option value="">Pilih Kelas</option>
            @foreach ($classes as $class)
                <option value="{{ $class->id }}" @selected((string) $classId === (string) $class->id)>
                    {{ $class->name }}@if ($class->academicYear) ({{ $class->academicYear->name }})@endif
                </option>
            @endforeach
        </select>
    </div>

    <div class="schedule-row__field schedule-row__field--teacher">
        <label class="form-label">Guru Pengajar</label>
        <select name="schedules[{{ $index }}][teacher_id]" class="form-select">
            <option value="">Pilih Guru Pengajar</option>
            @foreach ($teachers as $teacher)
                <option value="{{ $teacher->id }}" @selected((string) $teacherId === (string) $teacher->id)>
                    {{ $teacher->name }}
                </option>
            @endforeach
        </select>
        @error('schedules.'.$index.'.teacher_id')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div class="schedule-row__field">
        <label class="form-label">Hari</label>
        <select name="schedules[{{ $index }}][day_of_week]" class="form-select">
            <option value="">Pilih Hari</option>
            @foreach (\App\Models\ClassSchedule::DAYS as $value => $label)
                <option value="{{ $value }}" @selected((string) $dayOfWeek === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="schedule-row__field">
        <label class="form-label">Jam Mulai</label>
        <input
            type="time"
            name="schedules[{{ $index }}][start_time]"
            class="form-control"
            value="{{ $startTime }}"
        >
    </div>

    <div class="schedule-row__field">
        <label class="form-label">Jam Selesai</label>
        <input
            type="time"
            name="schedules[{{ $index }}][end_time]"
            class="form-control"
            value="{{ $endTime }}"
        >
    </div>

    <div class="schedule-row__remove">
        <button
            type="button"
            class="btn btn-outline-danger"
            data-remove-schedule
            title="Hapus jadwal ini"
            aria-label="Hapus jadwal ini"
        >
            <i class="bi bi-trash3" aria-hidden="true"></i>
        </button>
    </div>
</div>
