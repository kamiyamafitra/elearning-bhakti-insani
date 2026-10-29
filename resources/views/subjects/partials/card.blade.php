@php
    $schedules = $schedules ?? $subject->schedules;
    $assignments = $assignments ?? $subject->teachingAssignments;
    $assignmentMode = $assignmentMode ?? 'teacher';
    $assignmentsLabel = $assignmentsLabel ?? 'Guru Pengajar';
    $scheduleLabel = $scheduleLabel ?? 'Jadwal Kelas';
    $showScheduleClass = $showScheduleClass ?? true;
    $showAssignmentClass = $showAssignmentClass ?? true;
    $classroom = $classroom ?? null;
    $descriptionLimit = $descriptionLimit ?? 120;
    $emptyDescription = $emptyDescription ?? '-';
    $actionUrl = $actionUrl ?? null;
    $actionLabel = $actionLabel ?? 'Buka Mata Pelajaran';
    $actionIcon = $actionIcon ?? 'bi-arrow-right-circle';
    $actionTitle = $actionTitle ?? $actionLabel;
    $singleAction = $singleAction ?? false;
    $actions = $actions ?? '';
@endphp

<article class="subject-card">
    <header class="subject-card__header">
        <div class="subject-card__icon">
            <i class="bi bi-journal-bookmark-fill" aria-hidden="true"></i>
        </div>
        <div class="subject-card__heading">
            <h2 class="subject-card__title">{{ $subject->name }}</h2>
            <span class="subject-card__code">
                <i class="bi bi-hash" aria-hidden="true"></i>{{ $subject->code }}
            </span>
        </div>
    </header>

    <div class="subject-card__body">
        <dl class="subject-card__info">
            <div class="subject-card__row">
                <dt class="subject-card__label">
                    <i class="bi bi-upc-scan" aria-hidden="true"></i>
                    Kode Mata Pelajaran
                </dt>
                <dd class="subject-card__value">{{ $subject->code }}</dd>
            </div>
            <div class="subject-card__row">
                <dt class="subject-card__label">
                    <i class="bi bi-book" aria-hidden="true"></i>
                    Nama Mata Pelajaran
                </dt>
                <dd class="subject-card__value">{{ $subject->name }}</dd>
            </div>
            <div class="subject-card__row">
                <dt class="subject-card__label">
                    <i class="bi bi-text-paragraph" aria-hidden="true"></i>
                    Deskripsi
                </dt>
                <dd
                    class="subject-card__value subject-card__value--description"
                    @if ($subject->description) title="{{ $subject->description }}" @endif
                >
                    {{ $subject->description ? Str::limit($subject->description, $descriptionLimit) : $emptyDescription }}
                </dd>
            </div>

            @if ($classroom !== null)
                <div class="subject-card__row">
                    <dt class="subject-card__label">
                        <i class="bi bi-people" aria-hidden="true"></i>
                        Kelas Anda
                    </dt>
                    <dd class="subject-card__value">{{ $classroom->name }}</dd>
                </div>
            @endif
        </dl>

        <div class="subject-card__schedule">
            <div class="subject-card__schedule-heading">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                {{ $scheduleLabel }}
            </div>

            @forelse ($schedules as $schedule)
                <dl class="subject-card__info subject-card__info--schedule">
                    @if ($showScheduleClass)
                        <div class="subject-card__row">
                            <dt class="subject-card__label">
                                <i class="bi bi-people" aria-hidden="true"></i>
                                Kelas
                            </dt>
                            <dd class="subject-card__value">{{ $schedule->class?->name ?? '-' }}</dd>
                        </div>
                    @endif
                    <div class="subject-card__row">
                        <dt class="subject-card__label">
                            <i class="bi bi-calendar-week" aria-hidden="true"></i>
                            Hari
                        </dt>
                        <dd class="subject-card__value">{{ $schedule->day_label }}</dd>
                    </div>
                    <div class="subject-card__row">
                        <dt class="subject-card__label">
                            <i class="bi bi-clock" aria-hidden="true"></i>
                            Waktu
                        </dt>
                        <dd class="subject-card__value">{{ $schedule->time_range }}</dd>
                    </div>
                </dl>
            @empty
                <div class="subject-card__schedule-empty">
                    <i class="bi bi-calendar-x" aria-hidden="true"></i>
                    Belum ada jadwal
                </div>
            @endforelse
        </div>

        @if ($assignmentMode !== 'none')
            <div class="subject-card__teachers">
                <div class="subject-card__schedule-heading">
                    <i
                        class="bi {{ $assignmentMode === 'class' ? 'bi-people' : 'bi-person-video3' }}"
                        aria-hidden="true"
                    ></i>
                    {{ $assignmentsLabel }}
                </div>

                @forelse ($assignments as $assignment)
                    <div class="subject-card__teacher">
                        @if ($assignmentMode === 'class')
                            <span class="subject-card__teacher-name">
                                <i class="bi bi-people" aria-hidden="true"></i>
                                {{ $assignment->class?->name ?? '-' }}
                            </span>
                            <span class="subject-card__teacher-class">
                                {{ $assignment->class?->students_count ?? 0 }} siswa
                            </span>
                        @else
                            <span class="subject-card__teacher-name">
                                <i class="bi bi-person-circle" aria-hidden="true"></i>
                                {{ $assignment->teacher?->name ?? 'Belum ada guru' }}
                            </span>
                            @if ($showAssignmentClass)
                                <span class="subject-card__teacher-class">
                                    <i class="bi bi-people" aria-hidden="true"></i>
                                    {{ $assignment->class?->name ?? '-' }}
                                </span>
                            @endif
                        @endif
                    </div>
                @empty
                    <div class="subject-card__schedule-empty">
                        <i class="bi bi-person-x" aria-hidden="true"></i>
                        {{ $assignmentMode === 'class' ? 'Belum ada kelas' : 'Belum ada guru' }}
                    </div>
                @endforelse
            </div>
        @endif
    </div>

    <footer class="subject-card__actions{{ $singleAction ? ' subject-card__actions--single' : '' }}">
        @if ($actionUrl !== null)
            <a
                href="{{ $actionUrl }}"
                class="subject-card__action subject-card__action--view"
                title="{{ $actionTitle }}"
            >
                <i class="bi {{ $actionIcon }}" aria-hidden="true"></i>
                {{ $actionLabel }}
            </a>
        @endif

        {{ $actions }}
    </footer>
</article>
