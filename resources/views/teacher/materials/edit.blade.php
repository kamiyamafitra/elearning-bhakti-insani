@extends('teacher.layouts.dashboard')

@section('title', 'Edit Materi Pelajaran')
@section('page-title', 'Edit Materi Pelajaran')
@section('page-description', 'Perbarui informasi dan berkas materi.')

@section('content')
<div class="mb-4">
    <a href="{{ route('teacher.subjects.show', ['subject' => $subject, 'tab' => 'materials']) }}#materi" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali ke {{ $subject->name }}
    </a>
</div>

<div class="card card-dashboard">
    <div class="card-body">
        @include('teacher.materials._form', [
            'action' => route('teacher.materials.update', $material),
            'method' => 'PUT',
            'submitLabel' => 'Perbarui Materi',
        ])
    </div>
</div>
@endsection
