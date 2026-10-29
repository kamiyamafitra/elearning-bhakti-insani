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
                <span class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3 align-self-center" style="width: 72px; height: 72px;">
                    <span class="fs-3">{{ strtoupper(substr($student->user->name, 0, 1)) }}</span>
                </span>
                <h5 class="mb-1 text-break">{{ $student->user->name }}</h5>
                <p class="text-muted mb-0 text-break">{{ $student->user->email }}</p>
                <span class="badge text-bg-light mt-2 align-self-center">Siswa</span>
            </div>
            <div class="card-body border-top">
                <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-primary w-100">
                    <i class="bi bi-pencil-square me-1"></i> Ubah Data Siswa
                </a>
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
