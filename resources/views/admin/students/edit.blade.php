@extends('admin.layouts.dashboard')

@section('title', 'Ubah Siswa')

@section('content')
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Ubah Data Siswa</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">Perbarui data akun, identitas, dan profil siswa.</p>
    </div>
    <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form class="admin-form" method="POST" action="{{ route('admin.students.update', $student) }}">
            @csrf
            @method('PUT')

            <h2 class="admin-form__section-title">Data Akun</h2>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="name">Nama Lengkap</label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $student->user->name) }}" autocomplete="name" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $student->user->email) }}" autocomplete="email" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password">Kata Sandi Baru</label>
                    <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Kosongkan jika tidak ingin mengubah kata sandi.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password_confirmation">Konfirmasi Kata Sandi Baru</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password">
                </div>
            </div>

            <h2 class="admin-form__section-title">Data Siswa</h2>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="nisn">NISN</label>
                    <input type="text" id="nisn" name="nisn" class="form-control @error('nisn') is-invalid @enderror" value="{{ old('nisn', $student->nisn) }}" inputmode="numeric">
                    @error('nisn')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="nis">NIS</label>
                    <input type="text" id="nis" name="nis" class="form-control @error('nis') is-invalid @enderror" value="{{ old('nis', $student->nis) }}" inputmode="numeric">
                    @error('nis')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="class_id">Kelas</label>
                    <select id="class_id" name="class_id" class="form-select @error('class_id') is-invalid @enderror">
                        <option value="">-- Pilih Kelas --</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected((string) old('class_id', $student->class_id) === (string) $class->id)>
                                {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('class_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="gender">Jenis Kelamin</label>
                    <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror">
                        <option value="">-- Pilih --</option>
                        <option value="L" @selected(old('gender', $student->gender) === 'L')>Laki-laki</option>
                        <option value="P" @selected(old('gender', $student->gender) === 'P')>Perempuan</option>
                    </select>
                    @error('gender')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="birth_date">Tanggal Lahir</label>
                    <input type="date" id="birth_date" name="birth_date" class="form-control @error('birth_date') is-invalid @enderror" value="{{ old('birth_date', $student->birth_date?->format('Y-m-d')) }}">
                    @error('birth_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="phone">No. Telepon</label>
                    <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $student->phone) }}" inputmode="tel">
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="address">Alamat</label>
                    <textarea id="address" name="address" class="form-control @error('address') is-invalid @enderror" rows="3">{{ old('address', $student->address) }}</textarea>
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <h2 class="admin-form__section-title">Data Orang Tua/Wali</h2>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="parent_name">Nama Orang Tua/Wali</label>
                    <input type="text" id="parent_name" name="parent_name" class="form-control @error('parent_name') is-invalid @enderror" value="{{ old('parent_name', $student->parent_name) }}">
                    @error('parent_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="parent_phone">No. Telepon Orang Tua/Wali</label>
                    <input type="text" id="parent_phone" name="parent_phone" class="form-control @error('parent_phone') is-invalid @enderror" value="{{ old('parent_phone', $student->parent_phone) }}" inputmode="tel">
                    @error('parent_phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="admin-form__actions">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Perbarui
                </button>
                <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
