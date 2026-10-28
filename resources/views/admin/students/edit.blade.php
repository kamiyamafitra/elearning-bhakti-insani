@extends('admin.layouts.dashboard')

@section('title', 'Ubah Siswa')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-3 border-bottom">
    <h1 class="h3">Ubah Siswa</h1>
    <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.students.update', $student) }}">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $student->user->name) }}" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Surel</label>
                    <input type="email
