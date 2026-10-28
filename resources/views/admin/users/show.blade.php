@extends('admin.layouts.dashboard')

@section('title', 'Detail Pengguna')
@section('page-title', 'Detail Pengguna')
@section('page-description', 'Informasi lengkap akun pengguna.')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('admin.users.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i> Ubah</a>
</div>

<div class="card card-dashboard">
    <div class="card-header bg-white border-0">
        <h5 class="mb-0">Informasi Pengguna</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="text-muted small">Nama Lengkap</div>
                <div class="fw-semibold">{{ $user->name }}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Surel</div>
                <div class="fw-semibold">{{ $user->email }}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Peran</div>
                <span class="badge text-bg-light text-capitalize">{{ $user->role }}</span>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Status</div>
                @if ($user->is_active)
                    <span class="badge text-bg-success">Aktif</span>
                @else
                    <span class="badge text-bg-secondary">Tidak Aktif</span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
