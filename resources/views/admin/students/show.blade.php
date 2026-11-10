@extends('admin.layouts.dashboard')

@section('title', 'Detail Siswa')
@section('page-title', 'Detail Siswa')
@section('page-description', 'Informasi lengkap data siswa.')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.students.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card card-dashboard h-100 d-flex flex-column">
            <div class="card-body text-center d-flex flex-column justify-content-center flex-grow-1">
                <span class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3 align-self-center" style="width: 60px; height: 60px;">
                    <span class="fs-3">{{ strtoupper(substr($student->user->name, 0, 1)) }}</span>
                </span>
                <h5 class="mb-1 text-break">{{ $student->user->name }}</h5>
                <p class="text-muted mb-0 text-break">{{ $student->user->email }}</p>
                <div class="mt-2">
                    <span class="badge text-bg-light">Siswa</span>
                    @if ($student->user->is_active)
                        <span class="badge text-bg-success">Aktif</span>
                    @else
                        <span class="badge text-bg-secondary">Nonaktif</span>
                    @endif
                </div>
            </div>
            <div class="card-body border-top">
                <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-primary w-100">
                    <i class="bi bi-pencil-square me-1"></i> Ubah Data Siswa
                </a>
                <form method="POST" action="{{ route('admin.students.reset-password', $student) }}" class="mt-2" onsubmit="return confirm('Reset password {{ addslashes($student->user->name) }} dengan password sementara acak? Password baru ditampilkan sekali, dan siswa wajib menggantinya saat masuk.');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger w-100" @disabled(blank($student->nis)) title="{{ blank($student->nis) ? 'Siswa belum memiliki NIS' : 'Reset dengan password sementara acak' }}">
                        <i class="bi bi-key me-1" aria-hidden="true"></i> Reset Password
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.students.toggle-active', $student) }}" class="mt-2">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-light w-100">
                        <i class="bi {{ $student->user->is_active ? 'bi-person-slash' : 'bi-person-check' }} me-1" aria-hidden="true"></i>
                        {{ $student->user->is_active ? 'Nonaktifkan Akun' : 'Aktifkan Kembali' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Informasi Siswa</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">NISN</div>
                        <div class="fw-semibold">{{ $student->nisn ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">NIS</div>
                        <div class="fw-semibold">{{ $student->nis ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Kelas</div>
                        <div class="fw-semibold">{{ $student->class?->name ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Jenis Kelamin</div>
                        <div class="fw-semibold">{{ $student->gender === 'L' ? 'Laki-laki' : ($student->gender === 'P' ? 'Perempuan' : '-') }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Tanggal Lahir</div>
                        <div class="fw-semibold">{{ $student->birth_date?->format('d-m-Y') ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">No. Telepon</div>
                        <div class="fw-semibold">{{ $student->phone ?: '-' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Alamat</div>
                        <div class="fw-semibold">{{ $student->address ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Nama Orang Tua/Wali</div>
                        <div class="fw-semibold">{{ $student->parent_name ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">No. Telepon Orang Tua/Wali</div>
                        <div class="fw-semibold">{{ $student->parent_phone ?: '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
