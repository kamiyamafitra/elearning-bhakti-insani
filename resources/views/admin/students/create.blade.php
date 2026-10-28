@extends('admin.layouts.dashboard')

@section('title', 'Tambah Siswa')
@section('page-title', 'Tambah Siswa')
@section('page-description', 'Tambahkan data siswa baru beserta akun pengguna.')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('admin.students.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
</div>

<div class="card card-dashboard">
    <div class="card-header bg-white border-0">
        <h5 class="mb-0">Form Siswa</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.students.store') }}">
            @csrf
            <h6 class="text-muted mb-3">Data Akun</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Surel <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Kata Sandi <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Konfirmasi Kata Sandi <span class="text-danger">*</span></label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
            </div>
            <hr class="my-4">
            <h6 class="text-muted mb-3">Data Siswa</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">NISN</label>
                    <input type="text" name="nisn" class="form-control" value="{{ old('nisn') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">NIS</label>
                    <input type="text" name="nis" class="form-control" value="{{ old('nis') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Kelas</label>
                    <select name="class_id" class="form-select">
                        <option value="">-- Pilih Kelas --</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected(old('class_id')==$class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Jenis Kelamin</label>
                    <select name="gender" class="form-select">
                        <option value="">-- Pilih --</option>
                        <option value="L" @selected(old('gender')==='L')>Laki-laki</option>
                        <option value="P" @selected(old('gender')==='P')>Perempuan</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanggal Lahir</label>
                    <input type="date" name="birth_date" class="form-control" value="{{ old('birth_date') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">No. Telepon</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Alamat</label>
                    <textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nama Orang Tua/Wali</label>
                    <input type="
