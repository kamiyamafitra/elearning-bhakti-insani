@extends('teacher.layouts.dashboard')

@section('title', 'Dashboard Guru')
@section('page-title', 'Dashboard Guru')
@section('page-description', 'Pantau kelas, tugas, dan progres pembelajaran Anda.')

@section('content')
<div class="alert alert-primary border-0 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <div class="fw-semibold">Halo, {{ auth()->user()->name }}!</div>
        <div class="small">
            @if ($teacher)
                NIP {{ $teacher->nip ?: '-' }} &middot; {{ $totalClasses }} kelas &middot; {{ $totalSubjects }} mata pelajaran
            @else
                Profil guru Anda belum dilengkapi. Hubungi administrator.
            @endif
        </div>
    </div>
    <a href="{{ route('teacher.subjects.index') }}" class="btn btn-light">
        <i class="bi bi-journal-bookmark me-1" aria-hidden="true"></i> Lihat Mata Pelajaran
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <a href="{{ route('teacher.subjects.index') }}" class="card card-dashboard h-100 text-decoration-none text-reset">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon">
                    <i class="bi bi-easel fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Kelas Diajar</div>
                    <div class="h4 mb-0">{{ $totalClasses }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-xl-3">
        <a href="{{ route('teacher.subjects.index') }}" class="card card-dashboard h-100 text-decoration-none text-reset">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon">
                    <i class="bi bi-journal-bookmark fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Mata Pelajaran</div>
                    <div class="h4 mb-0">{{ $totalSubjects }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon">
                    <i class="bi bi-file-earmark-text fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Tugas Aktif</div>
                    <div class="h4 mb-0">{{ $activeAssignments }}</div>
                    <div class="small text-muted">{{ $assignmentsCreated }} tugas dibuat</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <a href="{{ route('teacher.grades.index') }}" class="card card-dashboard h-100 text-decoration-none text-reset">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon">
                    <i class="bi bi-inbox fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Perlu Dinilai</div>
                    <div class="h4 mb-0">{{ $pendingGrading }}</div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Kelas &amp; Mata Pelajaran</h5>
                <a href="{{ route('teacher.subjects.index') }}" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
            </div>
            <div class="card-body">
                @if ($assignments->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-easel fs-1" aria-hidden="true"></i>
                        <p class="mt-2 mb-0">Belum ada kelas atau mata pelajaran yang ditugaskan kepada Anda.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Mata Pelajaran</th>
                                    <th>Kelas</th>
                                    <th class="text-end">Siswa</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($assignments as $assignment)
                                    <tr>
                                        <td>
                                            <a href="{{ route('teacher.subjects.show', $assignment->subject) }}" class="fw-semibold text-decoration-none">
                                                {{ $assignment->subject?->name ?? '-' }}
                                            </a>
                                            <div class="text-muted small">{{ $assignment->subject?->code ?? '-' }}</div>
                                        </td>
                                        <td>{{ $assignment->class?->name ?? '-' }}</td>
                                        <td class="text-end">{{ $assignment->class?->students_count ?? 0 }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Pengumuman Terbaru</h5>
                <a href="{{ route('teacher.announcements.index') }}" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
            </div>
            <div class="card-body">
                @if ($announcements->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-megaphone fs-1" aria-hidden="true"></i>
                        <p class="mt-2 mb-0">Belum ada pengumuman.</p>
                    </div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($announcements as $announcement)
                            <a href="{{ route('teacher.announcements.show', $announcement) }}" class="link-row">
                                <div class="fw-semibold text-break">{{ $announcement->title }}</div>
                                <div class="text-muted small">{{ \Illuminate\Support\Str::limit($announcement->content, 90) }}</div>
                                <div class="text-muted small mt-1">{{ $announcement->published_at?->format('d-m-Y H:i') ?? $announcement->created_at?->format('d-m-Y H:i') }}</div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card card-dashboard mt-3">
    <div class="card-header bg-white border-0">
        <h5 class="mb-0">Pengumpulan Terbaru</h5>
        <div class="text-muted small">Pengumpulan siswa terbaru dari tugas yang Anda buat.</div>
    </div>
    <div class="card-body">
        @if ($recentSubmissions->isEmpty())
            <div class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-1" aria-hidden="true"></i>
                <p class="mt-2 mb-0">Belum ada pengumpulan tugas.</p>
            </div>
        @else
            <div class="list-group list-group-flush">
                @foreach ($recentSubmissions as $submission)
                    <a href="{{ route('teacher.assignments.show', $submission->assignment) }}"
                       class="link-row">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <div class="min-w-0">
                                <div class="fw-semibold text-break">{{ $submission->student?->name ?? 'Siswa' }}</div>
                                <div class="text-muted small text-break">
                                    {{ $submission->assignment?->title ?? 'Tugas' }}
                                    &middot; {{ $submission->assignment?->class?->name ?? '-' }}
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <span class="badge {{ $submission->statusBadgeClass() }}">{{ $submission->statusLabel() }}</span>
                                <div class="text-muted small mt-1">{{ $submission->submitted_at?->format('d-m-Y H:i') ?? '-' }}</div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>

<div class="card card-dashboard mt-3">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Jadwal Mengajar</h5>
        <span class="badge text-bg-light">{{ $schedules->count() }} Jadwal</span>
    </div>
    <div class="card-body">
        @if ($schedules->isEmpty())
            <div class="text-center text-muted py-4">
                <i class="bi bi-calendar-event fs-1" aria-hidden="true"></i>
                <p class="mt-2 mb-0">Belum ada jadwal mengajar untuk kelas yang Anda ampu.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Hari</th>
                            <th>Waktu</th>
                            <th>Mata Pelajaran</th>
                            <th>Kelas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($schedules as $schedule)
                            <tr>
                                <td>{{ \App\Models\ClassSchedule::DAYS[$schedule->day_of_week] ?? '-' }}</td>
                                <td>{{ substr($schedule->start_time, 0, 5) }}–{{ substr($schedule->end_time, 0, 5) }}</td>
                                <td>{{ $schedule->subject?->name ?? '-' }}</td>
                                <td>{{ $schedule->class?->name ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
