@extends('admin.layouts.dashboard')

@section('title', 'Tambah Pengumuman')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-3 border-bottom">
    <h1 class="h3">Tambah Pengumuman</h1>
    <a href="{{ route('admin.announcements.index') }}" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.announcements.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Judul</label>
                <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Isi Pengumuman</label>
                <textarea name="content" class="form-control" rows="6" required>{{ old('content') }}</textarea>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Visibilitas</label>
                    <select name="visibility" class="form-select" required>
                        <option value="all" @selected(old('visibility')==='all')>Seluruh Sekolah</option>
                        <option value="role" @selected(old('visibility')==='role')>Berdasarkan Peran</option>
                        <option value="class" @selected(old('visibility')==='class')>Berdasarkan Kelas</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Target Peran (jika berdasarkan peran)</label>
                    <select name="target_role" class="form-select">
                        <option value="">-- Tidak Digunakan --</option>
                        <option value="teacher" @selected(old('target_role')==='teacher')>Guru</option>
                        <option value="student" @selected(old('target_role')==='student')>Siswa</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tanggal Publikasi</label>
                    <input type="datetime-local" name="published_at" class="form-control" value="{{ old('published_at') }}">
                </div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="is_published" value="1" id="is_published" @checked(old('is_published'))>
                <label class="form-check-label" for="is_published">Publikasikan</label>
            </div>
            <button class="btn btn-primary">Simpan</button>
        </form>
    </div>
</div>
@endsection
