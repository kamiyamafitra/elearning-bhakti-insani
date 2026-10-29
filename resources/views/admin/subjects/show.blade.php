@extends('admin.layouts.dashboard')

@section('title', 'Detail Mata Pelajaran')
@section('page-title', 'Detail Mata Pelajaran')
@section('page-description', 'Kelola materi, tugas, dan presensi per mata pelajaran.')

@section('content')
@php
    $teacherNames = $subject->teachingAssignments
        ->pluck('teacher.name')
        ->filter()
        ->unique()
        ->values();

    $assignmentsByClass = $subject->teachingAssignments->keyBy('class_id');
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('admin.subjects.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
    <a href="{{ route('admin.subjects.edit', $subject) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i> Ubah Mata Pelajaran</a>
</div>

<div class="card card-dashboard mb-4">
    <div class="card-header bg-white border-0">
        <h5 class="mb-0">Informasi Mata Pelajaran</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-muted small">Kode Mata Pelajaran</div>
                <div class="fw-semibold">{{ $subject->code }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Nama Mata Pelajaran</div>
                <div class="fw-semibold">{{ $subject->name }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Jumlah Penugasan Mengajar</div>
                <div class="fw-semibold">{{ $subject->teachingAssignments->count() }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Guru Pengajar</div>
                <div class="fw-semibold">
                    @if ($teacherNames->isEmpty())
                        <span class="text-muted">Belum ada guru</span>
                    @else
                        {{ $teacherNames->implode(', ') }}
                    @endif
                </div>
            </div>
            <div class="col-12">
                <div class="text-muted small">Deskripsi</div>
                <div class="fw-semibold">{{ $subject->description ?: '-' }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card card-dashboard mb-4">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Jadwal Kelas</h5>
        <a href="{{ route('admin.subjects.edit', $subject) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-calendar-plus me-1"></i> Atur Jadwal
        </a>
    </div>
    <div class="card-body">
        @if ($subject->schedules->isEmpty())
            <p class="mb-0 text-muted">Belum ada jadwal kelas untuk mata pelajaran ini.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Kelas</th>
                            <th>Hari</th>
                            <th>Waktu</th>
                            <th>Guru Pengajar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($subject->schedules as $schedule)
                            <tr>
                                <td class="ps-3">{{ $schedule->class?->name ?? '-' }}</td>
                                <td>{{ $schedule->day_label }}</td>
                                <td>{{ $schedule->time_range }}</td>
                                <td>{{ $assignmentsByClass->get($schedule->class_id)?->teacher?->name ?? 'Belum ada guru' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<ul class="nav nav-tabs mb-3" id="subjectTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials" type="button" role="tab">Materi Pembelajaran</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="assignments-tab" data-bs-toggle="tab" data-bs-target="#assignments" type="button" role="tab">Tugas</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">Presensi</button>
    </li>
</ul>

<div class="tab-content" id="subjectTabsContent">
    <div class="tab-pane fade show active" id="materials" role="tabpanel">
        <div class="card card-dashboard">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Materi Pembelajaran</h5>
                <span class="text-muted small">Dikelola sesuai penugasan guru</span>
            </div>
            <div class="card-body text-center text-muted py-4">
                <i class="bi bi-book fs-1"></i>
                <p class="mt-2 mb-0">Materi pembelajaran terintegrasi per mata pelajaran. Fitur ini siap untuk pengelolaan berdasarkan penugasan mengajar.</p>
            </div>
        </div>
    </div>
    <div class="tab-pane fade" id="assignments" role="tabpanel">
        <div class="card card-dashboard">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Tugas</h5>
                <span class="text-muted small">Terhubung dengan kelas &amp; guru pengajar</span>
            </div>
            <div class="card-body text-center text-muted py-4">
                <i class="bi bi-file-earmark-text fs-1"></i>
                <p class="mt-2 mb-0">Tugas terintegrasi per mata pelajaran berdasarkan penugasan mengajar.</p>
            </div>
        </div>
    </div>
    <div class="tab-pane fade" id="attendance" role="tabpanel">
        <div class="card card-dashboard">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Presensi</h5>
                <span class="text-muted small">Per kelas dan per pertemuan</span>
            </div>
            <div class="card-body">
                @if ($subject->teachingAssignments->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Guru Pengajar</th>
                                    <th>Kelas</th>
                                    <th>Tahun Ajaran</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($subject->teachingAssignments as $assignment)
                                    <tr>
                                        <td class="ps-3">{{ $assignment->teacher?->name }}</td>
                                        <td>{{ $assignment->class?->name }}</td>
                                        <td>{{ $assignment->academicYear?->name }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="mb-0 text-muted">Belum ada penugasan mengajar untuk mata pelajaran ini.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
