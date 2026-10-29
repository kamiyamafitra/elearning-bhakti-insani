@extends('admin.layouts.dashboard')

@section('title', 'Ubah Kelas')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center gap-3 pt-2 pb-3 mb-3 border-bottom">
    <div>
        <h1 class="h3 mb-1">Ubah Data Kelas</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">Perbarui informasi kelas, kode, tingkat, dan tahun ajaran.</p>
    </div>
    <a href="{{ route('admin.classes.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form class="admin-form" method="POST" action="{{ route('admin.classes.update', $class) }}">
            @csrf
            @method('PUT')

            <h2 class="admin-form__section-title">Informasi Kelas</h2>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="academic_year_id">Tahun Ajaran</label>
                    <select id="academic_year_id" name="academic_year_id" class="form-select @error('academic_year_id') is-invalid @enderror" required>
                        @foreach ($academicYears as $year)
                            <option value="{{ $year->id }}" @selected((string) old('academic_year_id', $class->academic_year_id) === (string) $year->id)>
                                {{ $year->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('academic_year_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="name">Nama Kelas</label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $class->name) }}" placeholder="Contoh: X TKJ 1" maxlength="50" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="code">Kode Kelas</label>
                    <input type="text" id="code" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $class->code) }}" placeholder="Contoh: X-TKJ-1" maxlength="20" required>
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="level">Tingkat</label>
                    <input type="text" id="level" name="level" class="form-control @error('level') is-invalid @enderror" value="{{ old('level', $class->level) }}" placeholder="Contoh: X" maxlength="10">
                    @error('level')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="admin-form__actions">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Simpan Perubahan
                </button>
                <a href="{{ route('admin.classes.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
