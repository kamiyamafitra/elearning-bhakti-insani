@extends('admin.layouts.dashboard')

@section('title', 'Tambah Kelas')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center gap-3 pt-2 pb-3 mb-3 border-bottom">
    <div>
        <h1 class="h3 mb-1">Tambah Kelas</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">Lengkapi informasi kelas untuk tahun ajaran yang dipilih.</p>
    </div>
    <a href="{{ route('admin.classes.index') }}" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form class="admin-form" method="POST" action="{{ route('admin.classes.store') }}">
            @csrf

            <h2 class="admin-form__section-title">Informasi Kelas</h2>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="academic_year_id">Tahun Ajaran</label>
                    <select id="academic_year_id" name="academic_year_id" class="form-select" required>
                        @foreach ($academicYears as $year)
                            <option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>{{ $year->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="name">Nama Kelas</label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" placeholder="Contoh: X TKJ 1" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="code">Kode Kelas</label>
                    <input type="text" id="code" name="code" class="form-control" value="{{ old('code') }}" placeholder="Contoh: X-TKJ-1" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="level">Tingkat</label>
                    <input type="text" id="level" name="level" class="form-control" value="{{ old('level') }}" placeholder="Contoh: X">
                </div>
            </div>

            <div class="admin-form__actions">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="{{ route('admin.classes.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
