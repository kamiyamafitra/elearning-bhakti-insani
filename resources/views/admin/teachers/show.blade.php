@extends('admin.layouts.dashboard')

@section('title', 'Detail Guru')

@section('content')
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Detail Data Guru</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">Informasi akun dan profil guru.</p>
    </div>
    <a href="{{ route('admin.teachers.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card card-dashboard h-100">
            <div class="card-body text-center">
                <span class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                    <span class="fs-3">{{ $teacher->user->initials }}</span>
                </span>
                <h5 class="mb-1">{{ $teacher->user->name }}</h5>
                <p class="text-muted mb-2">{{ $teacher->user->email }}</p>
                <span class="badge text-bg-light">Guru</span>
                @if ($teacher->user->is_active)
                    <span class="badge text-bg-success">Aktif</span>
                @else
                    <span class="badge text-bg-secondary">Nonaktif</span>
                @endif
            </div>
            <div class="card-body border-top">
                <a href="{{ route('admin.teachers.edit', $teacher) }}" class="btn btn-primary w-100">
                    <i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Ubah Data Guru
                </a>
                <form method="POST" action="{{ route('admin.teachers.reset-password', $teacher) }}" class="mt-2" onsubmit="return confirm('Reset password {{ addslashes($teacher->user->name) }} menjadi NIP? Guru wajib membuat password baru saat masuk.');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger w-100" @disabled(blank($teacher->nip)) title="{{ blank($teacher->nip) ? 'Guru belum memiliki NIP' : 'Reset password menjadi NIP' }}">
                        <i class="bi bi-key me-1" aria-hidden="true"></i> Reset Password
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.teachers.toggle-active', $teacher) }}" class="mt-2">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-light w-100">
                        <i class="bi {{ $teacher->user->is_active ? 'bi-person-slash' : 'bi-person-check' }} me-1" aria-hidden="true"></i>
                        {{ $teacher->user->is_active ? 'Nonaktifkan Akun' : 'Aktifkan Kembali' }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Informasi Guru</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Nama Lengkap</div>
                        <div class="fw-semibold">{{ $teacher->user->name }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Email</div>
                        <div class="fw-semibold">{{ $teacher->user->email }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">NIP</div>
                        <div class="fw-semibold">{{ $teacher->nip ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">No. Telepon</div>
                        <div class="fw-semibold">{{ $teacher->phone ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Status Akun</div>
                        <div class="fw-semibold">
                            @if ($teacher->user->is_active)
                                <span class="badge text-bg-success">Aktif</span>
                            @else
                                <span class="badge text-bg-secondary">Nonaktif</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Terdaftar Sejak</div>
                        <div class="fw-semibold">{{ $teacher->created_at?->format('d-m-Y') ?? '-' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Alamat</div>
                        <div class="fw-semibold">{{ $teacher->address ?: '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
