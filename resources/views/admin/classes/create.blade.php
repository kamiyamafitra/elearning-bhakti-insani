@extends('admin.layouts.dashboard')

@section('title', 'Tambah Kelas')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-3 border-bottom">
    <h1 class="h3">Tambah Kelas</h1>
    <a href="{{ route('admin.classes.index') }}" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.classes.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Tahun Ajaran</label>
                <select name="academic_year_id" class="form-select" required>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}" @selected(old('academic_year_id')==$year->id)>{{ $year->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Nama Kelas</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="Contoh: X TKJ 1" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Kode Kelas</l
