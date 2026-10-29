@extends('admin.layouts.dashboard')

@section('title', 'Ubah Pengumuman')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center gap-3 pt-2 pb-3 mb-3 border-bottom">
    <div>
        <h1 class="h3 mb-1">Ubah Pengumuman</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">Perbarui isi dan sasaran pengumuman.</p>
    </div>
    <a href="{{ route('admin.announcements.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

<div class="card card-dashboard">
    <div class="card-body">
        <form class="admin-form" method="POST" action="{{ route('admin.announcements.update', $announcement) }}">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="title">Judul</label>
                    <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $announcement->title) }}" required>
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="content">Isi Pengumuman</label>
                    <textarea id="content" name="content" class="form-control @error('content') is-invalid @enderror" rows="6" required>{{ old('content', $announcement->content) }}</textarea>
                    @error('content')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="visibility">Visibilitas</label>
                    <select id="visibility" name="visibility" class="form-select @error('visibility') is-invalid @enderror" required>
                        <option value="all" @selected(old('visibility', $announcement->visibility) === 'all')>Seluruh Sekolah</option>
                        <option value="role" @selected(old('visibility', $announcement->visibility) === 'role')>Berdasarkan Peran</option>
                        <option value="class" @selected(old('visibility', $announcement->visibility) === 'class')>Berdasarkan Kelas</option>
                    </select>
                    @error('visibility')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="target_role">Target Peran</label>
                    <select id="target_role" name="target_role" class="form-select @error('target_role') is-invalid @enderror">
                        <option value="">-- Tidak Digunakan --</option>
                        <option value="teacher" @selected(old('target_role', $announcement->target_role) === 'teacher')>Guru</option>
                        <option value="student" @selected(old('target_role', $announcement->target_role) === 'student')>Siswa</option>
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
                            <option value="{{ $class->id }}" @selected((string) old('class_id', $announcement->class_id) === (string) $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                    @error('class_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Wajib dipilih jika visibilitas berdasarkan kelas.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="published_at">Tanggal Publikasi</label>
                    <input type="datetime-local" id="published_at" name="published_at" class="form-control @error('published_at') is-invalid @enderror" value="{{ old('published_at', $announcement->published_at?->format('Y-m-d\TH:i')) }}">
                    @error('published_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-check my-3">
                <input class="form-check-input" type="checkbox" name="is_published" value="1" id="is_published" @checked(old('is_published', $announcement->is_published))>
                <label class="form-check-label" for="is_published">Publikasikan</label>
            </div>

            <div class="admin-form__actions">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Perbarui
                </button>
                <a href="{{ route('admin.announcements.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
