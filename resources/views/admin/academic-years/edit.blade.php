@extends('admin.layouts.dashboard')

@section('title', 'Ubah Tahun Ajaran')

@section('content')
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Ubah Tahun Ajaran</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">Perbarui periode dan status tahun ajaran {{ $academicYear->name }}.</p>
    </div>
    <a href="{{ route('admin.academic-years.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form class="admin-form" method="POST" action="{{ route('admin.academic-years.update', $academicYear) }}">
            @csrf
            @method('PUT')

            @include('admin.academic-years.partials.form-fields', ['academicYear' => $academicYear])

            <div class="admin-form__actions">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Simpan Perubahan
                </button>
                <a href="{{ route('admin.academic-years.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
