@extends('admin.layouts.dashboard')

@section('title', 'Tambah Tahun Ajaran')

@section('content')
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Tambah Tahun Ajaran</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">Tentukan periode tahun ajaran baru beserta tanggal mulai dan selesainya.</p>
    </div>
    <a href="{{ route('admin.academic-years.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form class="admin-form" method="POST" action="{{ route('admin.academic-years.store') }}">
            @csrf

            @include('admin.academic-years.partials.form-fields', ['academicYear' => null])

            <div class="admin-form__actions">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="{{ route('admin.academic-years.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
