@extends('admin.layouts.dashboard')

@section('title', 'Detail Kelas')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.classes.index') }}" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card card-dashboard h-100 d-flex flex-column">
            <div class="card-body text-center d-flex flex-column justify-content-center flex-grow-1">
                <span class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3 align-self-center" style="width: 60px; height: 60px;">
                    <i class="bi bi-grid-3x3-gap-fill fs-2" aria-hidden="true"></i>
                </span>
                <h5 class="mb-1 text-break">{{ $class->name }}</h5>
                <p class="text-muted mb-0 text-break">{{ $class->code }}</p>
                <span class="badge text-bg-light mt-2 align-self-center">Kelas</span>
            </div>
            <div class="card-body border-top d-grid gap-2">
                <a href="{{ route('admin.classes.edit', $class) }}" class="btn btn-primary w-100">
                    <i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Ubah Data Kelas
                </a>
                <a href="{{ route('admin.classes.assign-students', $class) }}" class="btn btn-outline-primary w-100">
                    <i class="bi bi-person-plus me-1" aria-hidden="true"></i> Tetapkan Siswa
                </a>
                @if ($class->students->isNotEmpty())
                    <form method="POST" action="{{ route('admin.classes.reset-passwords', $class) }}" onsubmit="return confirm('Reset password semua siswa {{ addslashes($class->name) }} menjadi NIS masing-masing? Siswa wajib membuat password baru saat masuk.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="bi bi-key me-1" aria-hidden="true"></i> Reset Password Semua Siswa
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Informasi Kelas</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Nama Kelas</div>
                        <div class="fw-semibold">{{ $class->name }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Kode Kelas</div>
                        <div class="fw-semibold">{{ $class->code }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Tingkat</div>
                        <div class="fw-semibold">{{ $class->level ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Tahun Ajaran</div>
                        <div class="fw-semibold">{{ $class->academicYear?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Jumlah Siswa</div>
                        <div class="fw-semibold">{{ $class->students->count() }} Siswa</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Dibuat</div>
                        <div class="fw-semibold">{{ $class->created_at?->format('d-m-Y') ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card card-dashboard mt-3">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Siswa</h5>
        <span class="badge text-bg-light">{{ $class->students->count() }} Siswa</span>
    </div>
    <div class="card-body">
        @if ($class->students->isEmpty())
            <div class="text-center text-muted py-4">
                <i class="bi bi-person-x fs-1" aria-hidden="true"></i>
                <p class="mt-2 mb-0">Belum ada siswa yang terdaftar di kelas ini.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Siswa</th>
                            <th>NIS</th>
                            <th>NISN</th>
                            <th class="text-end pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($class->students->sortBy(fn ($student) => $student->user?->name) as $student)
                            @php
                                $name = $student->user?->name ?? 'Tanpa Nama';
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                            <span class="small fw-semibold">{{ $student->user?->initials ?? '?' }}</span>
                                        </span>
                                        <div>
                                            <div class="fw-semibold text-break">{{ $name }}</div>
                                            <div class="text-muted small text-break">{{ $student->user?->email ?? '-' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $student->nis ?: '-' }}</td>
                                <td>{{ $student->nisn ?: '-' }}</td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('admin.students.show', $student) }}" class="btn btn-sm btn-outline-primary" aria-label="Lihat {{ $name }}">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
