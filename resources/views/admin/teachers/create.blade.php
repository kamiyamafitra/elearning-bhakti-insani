@extends('admin.layouts.dashboard')

@section('title', 'Tambah Guru')

@section('content')
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Tambah Data Guru</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">Lengkapi data akun dan profil guru baru.</p>
    </div>
    <a href="{{ route('admin.teachers.index') }}" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form class="admin-form" method="POST" action="{{ route('admin.teachers.store') }}">
            @csrf

            <h2 class="admin-form__section-title">Data Akun</h2>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="name">Nama Lengkap</label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" autocomplete="name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" autocomplete="email" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password">Kata Sandi</label>
                    <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password_confirmation">Konfirmasi Kata Sandi</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                </div>
            </div>

            <h2 class="admin-form__section-title">Data Guru</h2>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="nip">NIP</label>
                    <input type="text" id="nip" name="nip" class="form-control" value="{{ old('nip') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="phone">No. Telepon</label>
                    <input type="text" id="phone" name="phone" class="form-control" value="{{ old('phone') }}">
                </div>
                <div class="col-12">
                    <label class="form-label" for="address">Alamat</label>
                    <textarea id="address" name="address" class="form-control" rows="3">{{ old('address') }}</textarea>
                </div>
            </div>

            <div class="admin-form__actions">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="{{ route('admin.teachers.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
