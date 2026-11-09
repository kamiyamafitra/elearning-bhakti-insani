@extends('admin.layouts.dashboard')

@section('title', 'Profil Saya')
@section('page-title', 'Profil Saya')
@section('page-description', 'Kelola informasi akun administrator Anda.')

@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card card-dashboard">
            <div class="card-body text-center">
                <span class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                    <span class="fs-3 fw-semibold">{{ $user->initials }}</span>
                </span>
                <h5 class="mb-1 text-break">{{ $user->name }}</h5>
                <p class="text-muted mb-1 text-break">{{ $user->email }}</p>
                <span class="badge text-bg-light mb-4">Administrator</span>

                <ul class="list-group list-group-flush text-start">
                    <li class="list-group-item px-0 d-flex justify-content-between gap-3">
                        <span class="text-muted small">Username</span>
                        <span class="fw-semibold">{{ $user->username }}</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between gap-3">
                        <span class="text-muted small">Status</span>
                        <span class="badge text-bg-success">Aktif</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card card-dashboard mb-3">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Ubah Profil</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.profile.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Nama Lengkap</label>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Simpan Perubahan
                        </button>
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>

        @include('auth.partials.password-form')
    </div>
</div>
@endsection
