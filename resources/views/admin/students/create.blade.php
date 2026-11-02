@extends('admin.layouts.dashboard')

@section('title', 'Tambah Siswa')

@section('content')
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Tambah Data Siswa</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">Lengkapi data akun dan profil siswa baru.</p>
    </div>
    <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form class="admin-form" method="POST" action="{{ route('admin.students.store') }}">
            @csrf

            <h2 class="admin-form__section-title">Data Akun</h2>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="name">Nama Lengkap</label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" autocomplete="name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Surel</label>
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

            <h2 class="admin-form__section-title">Data Siswa</h2>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="nisn">NISN</label>
                    <input type="text" id="nisn" name="nisn" class="form-control" value="{{ old('nisn') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="nis">NIS</label>
                    <input type="text" id="nis" name="nis" class="form-control" value="{{ old('nis') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="class_id">Kelas</label>
                    <select id="class_id" name="class_id" class="form-select">
                        <option value="">-- Pilih Kelas --</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected(old('class_id') == $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="gender">Jenis Kelamin</label>
                    <select id="gender" name="gender" class="form-select">
                        <option value="">-- Pilih --</option>
                        <option value="L" @selected(old('gender') === 'L')>Laki-laki</option>
                        <option value="P" @selected(old('gender') === 'P')>Perempuan</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="birth_date">Tanggal Lahir</label>
                    <input type="date" id="birth_date" name="birth_date" class="form-control" value="{{ old('birth_date') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="phone">No. Telepon</label>
                    <input type="text" id="phone" name="phone" class="form-control" value="{{ old('phone') }}">
                </div>
                <div class="col-12">
                    <label class="form-label" for="address">Alamat</label>
                    <textarea id="address" name="address" class="form-control" rows="3">{{ old('address') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="parent_name">Nama Orang Tua/Wali</label>
                    <input type="text" id="parent_name" name="parent_name" class="form-control" value="{{ old('parent_name') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="parent_phone">No. Telepon Orang Tua/Wali</label>
                    <input type="text" id="parent_phone" name="parent_phone" class="form-control" value="{{ old('parent_phone') }}">
                </div>
            </div>

            <div class="admin-form__actions">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
