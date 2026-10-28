@extends('admin.layouts.dashboard')

@section('title', 'Ubah Pengumuman')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-3 border-bottom">
    <h1 class="h3">Ubah Pengumuman</h1>
    <a href="{{ route('admin.announcements.index') }}" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.announcements.update', $announcement) }}">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label class="form-label">Judul</label>
                <input type="text" name="title" class="form-control" value="{{ old('title', $announcement->title) }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Isi Pengumuman</label>
                <textarea name="content" class="form-control" rows="6" required>{{ old('content', $announcement->content) }}</textarea>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Visibilitas</label>
                    <select name="visibility" class="form-select" required>
                        <option value="all" @selected(old('visibility', $announcement->visibility)==='all')>Seluruh Sekolah</option>
                        <option value="role" @selected(old('visibility', $announcement->visibility)==='role')>Berdasarkan Peran</option>
                        <option value="class" @selected(old('visibility', $ann
