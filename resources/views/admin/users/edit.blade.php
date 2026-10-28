@extends('admin.layouts.dashboard')

@section('title', 'Ubah Pengguna')
@section('page-title', 'Ubah Pengguna')
@section('page-description', 'Perbarui informasi akun pengguna.')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('admin.users.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
</div>

<div class="card card-dashboard">
    <div class="card-header bg-white border-0">
        <h5 class="mb-0">Form Pengguna</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Surel <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Kata Sandi Baru</label>
                    <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Konfirmasi Kata Sandi Baru</label>
                    <input type="password" name="password_confirmation" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Peran <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        <option value="admin" @selected(old('role', $user->role)==='admin')>Administrator</option>
                        <option value="teacher" @selected(old('role', $user->role)==='teacher')>Guru</option>
                        <option value="student" @selected(old('role', $user->role)==='student')>Siswa</option>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $user->is_active))>
                        <label class="form-check-label" for="is_active">Aktifkan akun</label>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('admin.users.index') }}" class="btn btn-light">Batal</a>
                <button class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endsection
