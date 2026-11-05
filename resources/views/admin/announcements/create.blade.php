@extends('admin.layouts.dashboard')

@section('title', 'Tambah Pengumuman')

@section('content')
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Tambah Pengumuman</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">Bagikan informasi untuk seluruh sekolah, peran tertentu, atau kelas tertentu.</p>
    </div>
    <a href="{{ route('admin.announcements.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

<div class="card card-dashboard">
    <div class="card-body">
        <form class="admin-form" method="POST" action="{{ route('admin.announcements.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="title">Judul</label>
                    <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="content">Isi Pengumuman</label>
                    <textarea id="content" name="content" class="form-control @error('content') is-invalid @enderror" rows="6" required>{{ old('content') }}</textarea>
                    @error('content')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    @include('discussions._image-field')
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="visibility">Visibilitas</label>
                    <select id="visibility" name="visibility" class="form-select @error('visibility') is-invalid @enderror" required>
                        <option value="all" @selected(old('visibility', 'all') === 'all')>Seluruh Sekolah</option>
                        <option value="role" @selected(old('visibility') === 'role')>Berdasarkan Peran</option>
                        <option value="class" @selected(old('visibility') === 'class')>Berdasarkan Kelas</option>
                    </select>
                    @error('visibility')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="target_role">Target Peran</label>
                    <select id="target_role" name="target_role" class="form-select @error('target_role') is-invalid @enderror">
                        <option value="">-- Tidak Digunakan --</option>
                        <option value="teacher" @selected(old('target_role') === 'teacher')>Guru</option>
                        <option value="student" @selected(old('target_role') === 'student')>Siswa</option>
                    </select>
                    @error('target_role')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Wajib dipilih jika visibilitas berdasarkan peran.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="class_id">Target Kelas</label>
                    <select id="class_id" name="class_id" class="form-select @error('class_id') is-invalid @enderror">
                        <option value="">-- Tidak Digunakan --</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected((string) old('class_id') === (string) $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                    @error('class_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Wajib dipilih jika visibilitas berdasarkan kelas.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="published_at">Tanggal Publikasi</label>
                    <input type="datetime-local" id="published_at" name="published_at" class="form-control @error('published_at') is-invalid @enderror" value="{{ old('published_at') }}">
                    @error('published_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Kosongkan agar langsung tayang saat disimpan, atau isi waktu nanti untuk menjadwalkan.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="expires_at">Tanggal Berakhir <span class="text-muted fw-normal">(opsional)</span></label>
                    <input type="datetime-local" id="expires_at" name="expires_at" class="form-control @error('expires_at') is-invalid @enderror" value="{{ old('expires_at') }}">
                    @error('expires_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Pengumuman otomatis disembunyikan setelah waktu ini, lalu dihapus permanen 6 bulan kemudian. Kosongkan agar tampil terus.</div>
                </div>
            </div>

            <div class="admin-form__actions mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Simpan
                </button>
                <a href="{{ route('admin.announcements.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
