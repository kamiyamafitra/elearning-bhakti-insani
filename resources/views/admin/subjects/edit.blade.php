@extends('admin.layouts.dashboard')

@section('title', 'Ubah Mata Pelajaran')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-3 border-bottom">
    <h1 class="h3">Ubah Mata Pelajaran</h1>
    <a href="{{ route('admin.subjects.index') }}" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.subjects.update', $subject) }}">
            @csrf
            @method('PUT')

            <h2 class="h6 text-uppercase fw-bold mb-3" style="color: #64748B;">Informasi Mata Pelajaran</h2>

            <div class="mb-3">
                <label class="form-label">Kode Mata Pelajaran</label>
                <input type="text" name="code" class="form-control" value="{{ old('code', $subject->code) }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Nama Mata Pelajaran</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $subject->name) }}" required>
            </div>
            <div class="mb-4">
                <label class="form-label">Deskripsi</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description', $subject->description) }}</textarea>
            </div>

            @include('admin.subjects.partials.schedule-editor', [
                'classes' => $classes,
                'teachers' => $teachers,
                'subject' => $subject,
            ])

            <button class="btn btn-primary mt-4">Simpan Perubahan</button>
        </form>
    </div>
</div>
@endsection
