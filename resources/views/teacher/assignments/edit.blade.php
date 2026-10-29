@extends('teacher.layouts.dashboard')

@section('title', 'Edit Tugas')
@section('page-title', 'Edit Tugas')
@section('page-description', 'Perbarui instruksi, deadline, lampiran, dan status publikasi tugas.')

@section('content')
<div class="mb-4">
    <a href="{{ route('teacher.assignments.show', $assignment) }}" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali ke Detail Tugas
    </a>
</div>

<div class="card card-dashboard">
    <div class="card-body">
        @include('teacher.assignments._form', [
            'action' => route('teacher.assignments.update', $assignment),
            'method' => 'PUT',
            'submitLabel' => 'Perbarui Tugas',
        ])
    </div>
</div>
@endsection
