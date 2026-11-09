@extends('teacher.layouts.dashboard')

@section('title', 'Detail Mata Pelajaran')
@section('page-title', $subject->name)
@section('page-description', 'Materi, tugas, kelas, siswa, dan jadwal untuk mata pelajaran yang Anda ampu.')

@section('content')
<div class="mb-4">
    <a href="{{ route('teacher.subjects.index') }}" class="btn btn-light">
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
                    <div class="text-muted small">Kelas yang Diajar</div>
                    <div class="fw-semibold text-break">{{ $assignments->pluck('class.name')->filter()->implode(', ') ?: '-' }}</div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Tahun Ajaran</div>
                    <div class="fw-semibold">{{ $assignments->pluck('academicYear.name')->filter()->unique()->implode(', ') ?: '-' }}</div>
                </div>
                <div class="mt-auto d-grid gap-2">
                    <a href="{{ route('teacher.quizzes.index') }}" class="btn btn-outline-primary">
                        <i class="bi bi-patch-question me-1" aria-hidden="true"></i> Kuis
                    </a>
                    <a href="{{ route('teacher.grades.index') }}" class="btn btn-outline-primary">
                        <i class="bi bi-award me-1" aria-hidden="true"></i> Penilaian
                    </a>
                    <a href="{{ route('teacher.discussions.index') }}" class="btn btn-outline-primary">
                        <i class="bi bi-chat-dots me-1" aria-hidden="true"></i> Diskusi Kelas
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
                @if ($schedules->isEmpty())
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
                                @foreach ($schedules as $schedule)
                                    <tr>
                                        <td>{{ \App\Models\ClassSchedule::DAYS[$schedule->day_of_week] ?? '-' }}</td>
                                        <td>{{ substr($schedule->start_time, 0, 5) }}–{{ substr($schedule->end_time, 0, 5) }}</td>
                                        <td>{{ $schedule->class?->name ?? '-' }}</td>
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
               href="{{ route('teacher.subjects.show', ['subject' => $subject, 'tab' => 'materials']) }}#materi">
                <i class="bi bi-folder2-open me-1" aria-hidden="true"></i> Materi Pelajaran
                <span class="badge text-bg-light ms-1">{{ $learningMaterials->count() }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'assignments' ? 'active' : '' }}"
               href="{{ route('teacher.subjects.show', ['subject' => $subject, 'tab' => 'assignments']) }}#tugas">
                <i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i> Tugas dan Pengumpulan
                <span class="badge text-bg-light ms-1">{{ $lmsAssignments->count() }}</span>
            </a>
        </li>
    </ul>

    <div class="card card-dashboard rounded-top-0 border-top-0">
        @if ($tab === 'materials')
            <div class="card-header bg-white border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-0">Materi Pelajaran</h5>
                    <div class="text-muted small">Kelola materi untuk kelas yang Anda ampu pada mata pelajaran ini.</div>
                </div>
                <a href="{{ route('teacher.materials.create', $subject) }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1" aria-hidden="true"></i> Tambah Materi
                </a>
            </div>
            <div class="card-body">
                @if ($learningMaterials->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-folder-plus fs-1" aria-hidden="true"></i>
                        <p class="mt-2 mb-0">Belum ada materi untuk mata pelajaran ini.</p>
                        <p class="small">Tambahkan materi pertama Anda agar siswa dapat mengunduhnya.</p>
                    </div>
                @else
                    @foreach ($learningMaterials as $material)
                        <div class="border rounded-3 p-3 mb-3 bg-white">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <div class="min-w-0">
                                    <h6 class="mb-1 text-break">{{ $material->title }}</h6>
                                    <div class="text-muted small">
                                        {{ $material->class?->name ?? '-' }}
                                        &middot;
                                        @if ($material->isPublished())
                                            Dipublikasikan {{ $material->published_at?->format('d-m-Y H:i') ?? '-' }}
                                        @elseif ($material->is_published)
                                            Terjadwal {{ $material->published_at?->format('d-m-Y H:i') ?? '-' }}
                                        @else
                                            Masih draf
                                        @endif
                                    </div>
                                </div>
                                <span class="badge {{ $material->isPublished() ? 'text-bg-success' : ($material->is_published ? 'text-bg-warning' : 'text-bg-secondary') }}">
                                    {{ $material->isPublished() ? 'Dipublikasikan' : ($material->is_published ? 'Terjadwal' : 'Draf') }}
                                </span>
                            </div>

                            <p class="mt-2 mb-2 text-break">{{ \Illuminate\Support\Str::limit($material->description, 220) }}</p>

                            <div class="d-flex flex-wrap align-items-center gap-2">
                                @if ($material->hasFile())
                                    @include('files._preview-button', ['url' => route('teacher.materials.download', $material), 'path' => $material->file_path, 'name' => $material->file_original_name])
                                    <a href="{{ route('teacher.materials.download', $material) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download me-1" aria-hidden="true"></i> {{ $material->file_original_name }}
                                    </a>
                                    <span class="text-muted small">{{ $material->readableFileSize() }}</span>
                                @endif
                                @if ($material->hasExternalUrl())
                                    <a href="{{ $material->external_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i> Sumber Eksternal
                                    </a>
                                @endif
                            </div>

                            <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
                                <a href="{{ route('teacher.materials.edit', $material) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Edit Materi
                                </a>
                                <form method="POST" action="{{ route('teacher.materials.destroy', $material) }}" class="d-inline"
                                      onsubmit="return confirm('Hapus materi ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash me-1" aria-hidden="true"></i> Hapus
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        @else
            <div class="card-header bg-white border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-0">Tugas dan Pengumpulan</h5>
                    <div class="text-muted small">Buat tugas, pantau pengumpulan, dan beri nilai siswa.</div>
                </div>
                <a href="{{ route('teacher.assignments.create', $subject) }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1" aria-hidden="true"></i> Buat Tugas
                </a>
            </div>
            <div class="card-body">
                @if ($lmsAssignments->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-file-earmark-plus fs-1" aria-hidden="true"></i>
                        <p class="mt-2 mb-0">Belum ada tugas untuk mata pelajaran ini.</p>
                        <p class="small">Buat tugas pertama Anda dan publikasikan kepada siswa.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tugas</th>
                                    <th>Kelas</th>
                                    <th>Deadline</th>
                                    <th>Status</th>
                                    <th>Pengumpulan</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lmsAssignments as $lmsAssignment)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-break">{{ $lmsAssignment->title }}</div>
                                            <div class="text-muted small">{{ \Illuminate\Support\Str::limit($lmsAssignment->instructions, 90) }}</div>
                                        </td>
                                        <td>{{ $lmsAssignment->class?->name ?? '-' }}</td>
                                        <td>
                                            <div class="{{ $lmsAssignment->isPastDue() ? 'text-danger fw-semibold' : '' }}">
                                                {{ $lmsAssignment->due_at?->format('d-m-Y H:i') ?? '-' }}
                                            </div>
                                            <div class="text-muted small">Skor maks. {{ $lmsAssignment->max_score }}</div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $lmsAssignment->isPublished() ? 'text-bg-success' : ($lmsAssignment->status === \App\Models\Assignment::STATUS_PUBLISHED ? 'text-bg-warning' : 'text-bg-secondary') }}">
                                                {{ $lmsAssignment->isPublished() ? 'Dipublikasikan' : ($lmsAssignment->status === \App\Models\Assignment::STATUS_PUBLISHED ? 'Terjadwal' : 'Draf') }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="small">{{ $lmsAssignment->submissions_count }} terkumpul</div>
                                            <div class="small text-muted">{{ $lmsAssignment->pending_grading_count }} perlu dinilai</div>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                                <a href="{{ route('teacher.assignments.show', $lmsAssignment) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-inbox me-1" aria-hidden="true"></i> Lihat Pengumpulan
                                                </a>
                                                <a href="{{ route('teacher.assignments.edit', $lmsAssignment) }}" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                                </a>
                                                <form method="POST" action="{{ route('teacher.assignments.destroy', $lmsAssignment) }}"
                                                      onsubmit="return confirm('Hapus tugas ini beserta pengumpulannya?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            </div>
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

<h2 class="h5 mt-4 mb-3" id="kelas">Kelas dan Siswa</h2>

@foreach ($assignments as $assignment)
    <div class="card card-dashboard mt-3">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0 text-break">{{ $assignment->class?->name ?? 'Kelas' }}</h5>
                <div class="text-muted small">{{ $assignment->academicYear?->name ?? '-' }}</div>
            </div>
            <span class="badge text-bg-light">{{ $assignment->class?->students?->count() ?? 0 }} Siswa</span>
        </div>
        <div class="card-body">
            @if (! $assignment->class || $assignment->class->students->isEmpty())
                <div class="text-center text-muted py-4">
                    <i class="bi bi-person-x fs-1" aria-hidden="true"></i>
                    <p class="mt-2 mb-0">Belum ada siswa di kelas ini.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Siswa</th>
                                <th>NIS</th>
                                <th>NISN</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assignment->class->students->sortBy(fn ($student) => $student->user?->name) as $student)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold text-break">{{ $student->user?->name ?? 'Tanpa Nama' }}</div>
                                        <div class="text-muted small">{{ $student->user?->email ?? '-' }}</div>
                                    </td>
                                    <td>{{ $student->nis ?: '-' }}</td>
                                    <td>{{ $student->nisn ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endforeach
@endsection
