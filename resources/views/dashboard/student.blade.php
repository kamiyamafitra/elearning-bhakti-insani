@extends('student.layouts.dashboard')

@section('title', 'Dashboard Siswa')
@section('page-title', 'Dashboard Siswa')
@section('page-description', 'Selamat belajar, akses materi, tugas, dan progres Anda di sini.')

@section('content')
<div class="alert alert-primary border-0 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <div class="fw-semibold">Halo, {{ auth()->user()->name }}!</div>
        <div class="small">
            @if ($classroom)
                Kelas {{ $classroom->name }} &middot; {{ $classroom->academicYear?->name ?? 'Tahun ajaran aktif' }}
            @else
                Kelas Anda belum ditetapkan. Hubungi administrator untuk melengkapi data.
            @endif
        </div>
    </div>
    <a href="{{ route('student.subjects.index') }}" class="btn btn-light">
        <i class="bi bi-journal-code me-1" aria-hidden="true"></i> Lihat Kursus Saya
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon">
                    <i class="bi bi-journal-code fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Mata Pelajaran</div>
                    <div class="h4 mb-0">{{ $totalSubjects }}</div>
                    <div class="small text-muted">{{ $availableAssignments }} tugas tersedia</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon">
                    <i class="bi bi-file-earmark-text fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Tugas Belum Dikerjakan</div>
                    <div class="h4 mb-0">{{ $pendingAssignments }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon">
                    <i class="bi bi-clock-history fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Deadline 7 Hari</div>
                    <div class="h4 mb-0">{{ $upcomingDeadlines }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon">
                    <i class="bi bi-award fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Tugas Dinilai</div>
                    <div class="h4 mb-0">{{ $gradedAssignments }}</div>
                    <div class="small text-muted">Nilai terbaru: {{ $latestGrade ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Kursus Saya</h5>
                <a href="{{ route('student.subjects.index') }}" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
            </div>
            <div class="card-body">
                @if ($subjects->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-journal-code fs-1" aria-hidden="true"></i>
                        <p class="mt-2 mb-0">Belum ada mata pelajaran yang terdaftar untuk kelas Anda.</p>
                    </div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($subjects->take(5) as $subject)
                            @php
                                $teachers = $subject->teachingAssignments->pluck('teacher.name')->filter()->unique();
                                $firstSchedule = $subject->schedules->first();
                            @endphp
                            <a href="{{ route('student.subjects.show', $subject) }}" class="list-group-item list-group-item-action px-0 py-3">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-break">{{ $subject->name }}</div>
                                        <div class="text-muted small">
                                            {{ $subject->code }}
                                            @if ($teachers->isNotEmpty())
                                                &middot; {{ $teachers->implode(', ') }}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-end flex-shrink-0">
                                        @if ($firstSchedule)
                                            <span class="badge text-bg-light">{{ \App\Models\ClassSchedule::DAYS[$firstSchedule->day_of_week] ?? '' }}</span>
                                            <div class="text-muted small mt-1">{{ substr($firstSchedule->start_time, 0, 5) }}–{{ substr($firstSchedule->end_time, 0, 5) }}</div>
                                        @else
                                            <span class="badge text-bg-light">Tanpa jadwal</span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Pengumuman Terbaru</h5>
                <a href="{{ route('student.announcements.index') }}" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
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
                            <a href="{{ route('student.announcements.show', $announcement) }}" class="list-group-item list-group-item-action px-0">
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

@if ($student)
    <div class="card card-dashboard mt-3">
        <div class="card-header bg-white border-0">
            <h5 class="mb-0">Informasi Siswa</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Nama</div>
                    <div class="fw-semibold text-break">{{ auth()->user()->name }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">NIS</div>
                    <div class="fw-semibold">{{ $student->nis ?: '-' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">NISN</div>
                    <div class="fw-semibold">{{ $student->nisn ?: '-' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Kelas</div>
                    <div class="fw-semibold text-break">{{ $classroom?->name ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
