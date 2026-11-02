@extends('student.layouts.dashboard')

@section('title', 'Detail Mata Pelajaran')
@section('page-title', $subject->name)
@section('page-description', 'Informasi mata pelajaran, guru pengampu, materi, dan tugas kelas Anda.')

@section('content')
@php
    $teachers = $subject->teachingAssignments->pluck('teacher.name')->filter()->unique();
@endphp

<div class="mb-4">
    <a href="{{ route('student.subjects.index') }}" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali ke Mata Pelajaran
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="stat-icon">
                        <i class="bi bi-journal-bookmark fs-4" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2 class="h5 mb-1 text-break">{{ $subject->name }}</h2>
                        <span class="badge text-bg-light">{{ $subject->code }}</span>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Deskripsi</div>
                    <div>{{ $subject->description ?: 'Belum ada deskripsi mata pelajaran.' }}</div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Guru Pengampu</div>
                    <div class="fw-semibold text-break">{{ $teachers->isNotEmpty() ? $teachers->implode(', ') : 'Belum ditentukan' }}</div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Kelas Anda</div>
                    <div class="fw-semibold text-break">{{ $classroom?->name ?? '-' }}</div>
                </div>
                <div class="mt-auto">
                    <a href="{{ route('student.quizzes.index') }}" class="btn btn-outline-primary w-100">
                        <i class="bi bi-patch-question me-1" aria-hidden="true"></i> Lihat Kuis
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Jadwal Mata Pelajaran</h5>
            </div>
            <div class="card-body">
                @if ($subject->schedules->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-calendar-x fs-1" aria-hidden="true"></i>
                        <p class="mt-2 mb-0">Belum ada jadwal untuk mata pelajaran ini.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Hari</th>
                                    <th>Waktu</th>
                                    <th>Kelas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($subject->schedules as $schedule)
                                    <tr>
                                        <td>{{ \App\Models\ClassSchedule::DAYS[$schedule->day_of_week] ?? '-' }}</td>
                                        <td>{{ substr($schedule->start_time, 0, 5) }}–{{ substr($schedule->end_time, 0, 5) }}</td>
                                        <td>{{ $classroom?->name ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="mt-4" id="materi">
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'materials' ? 'active' : '' }}"
               href="{{ route('student.subjects.show', ['subject' => $subject, 'tab' => 'materials']) }}#materi">
                <i class="bi bi-folder2-open me-1" aria-hidden="true"></i> Materi Pelajaran
                <span class="badge text-bg-light ms-1">{{ $learningMaterials->count() }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'assignments' ? 'active' : '' }}"
               href="{{ route('student.subjects.show', ['subject' => $subject, 'tab' => 'assignments']) }}#tugas">
                <i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i> Tugas dan Pengumpulan
                <span class="badge text-bg-light ms-1">{{ $lmsAssignments->count() }}</span>
            </a>
        </li>
    </ul>

    <div class="card card-dashboard rounded-top-0 border-top-0">
        @if ($tab === 'materials')
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Materi Pelajaran</h5>
                <div class="text-muted small">Materi yang dipublikasikan oleh guru pengampu untuk kelas Anda.</div>
            </div>
            <div class="card-body">
                @if ($learningMaterials->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-folder-x fs-1" aria-hidden="true"></i>
                        <p class="mt-2 mb-0">Belum ada materi yang dipublikasikan.</p>
                        <p class="small">Materi akan tampil di sini setelah guru mempublikasikannya.</p>
                    </div>
                @else
                    @foreach ($learningMaterials as $material)
                        <div class="border rounded-3 p-3 mb-3 bg-white">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <div class="min-w-0">
                                    <h6 class="mb-1 text-break">{{ $material->title }}</h6>
                                    <div class="text-muted small">
                                        {{ $material->teacher?->name ?? 'Guru' }}
                                        &middot;
                                        Dipublikasikan {{ $material->published_at?->format('d-m-Y H:i') ?? '-' }}
                                    </div>
                                </div>
                                <span class="badge text-bg-success">Materi</span>
                            </div>

                            <p class="mt-2 mb-2 text-break">{{ \Illuminate\Support\Str::limit($material->description, 220) }}</p>

                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <a href="{{ route('student.materials.show', [$subject, $material]) }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-book me-1" aria-hidden="true"></i> Buka Materi
                                </a>
                                @if ($material->hasFile())
                                    <a href="{{ route('student.materials.download', [$subject, $material]) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download me-1" aria-hidden="true"></i> Unduh Materi
                                    </a>
                                    <span class="text-muted small">{{ $material->readableFileSize() }}</span>
                                @endif
                                @if ($material->hasExternalUrl())
                                    <a href="{{ $material->external_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i> Sumber Eksternal
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        @else
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Tugas dan Pengumpulan</h5>
                <div class="text-muted small">Tugas yang dipublikasikan untuk kelas Anda beserta status pengumpulan.</div>
            </div>
            <div class="card-body">
                @if ($lmsAssignments->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-file-earmark-x fs-1" aria-hidden="true"></i>
                        <p class="mt-2 mb-0">Belum ada tugas yang dipublikasikan.</p>
                        <p class="small">Tugas akan tampil di sini setelah guru mempublikasikannya.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tugas</th>
                                    <th>Guru</th>
                                    <th>Deadline</th>
                                    <th>Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lmsAssignments as $lmsAssignment)
                                    @php($submission = $lmsAssignment->submissions->first())
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-break">{{ $lmsAssignment->title }}</div>
                                            <div class="text-muted small">{{ \Illuminate\Support\Str::limit($lmsAssignment->instructions, 90) }}</div>
                                        </td>
                                        <td>{{ $lmsAssignment->teacher?->name ?? '-' }}</td>
                                        <td>
                                            <div class="{{ $lmsAssignment->isPastDue() && ! $submission?->isGraded() ? 'text-danger fw-semibold' : '' }}">
                                                {{ $lmsAssignment->due_at?->format('d-m-Y H:i') ?? '-' }}
                                            </div>
                                            <div class="text-muted small">Skor maks. {{ $lmsAssignment->max_score }}</div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $lmsAssignment->statusBadgeClassFor($submission) }}">
                                                {{ $lmsAssignment->statusLabelFor($submission) }}
                                            </span>
                                            @if ($submission?->isGraded())
                                                <div class="small text-muted mt-1">Nilai: {{ $submission->score }}</div>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('student.assignments.show', [$subject, $lmsAssignment]) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye me-1" aria-hidden="true"></i>
                                                {{ $submission ? 'Lihat Nilai' : 'Kumpulkan Tugas' }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
