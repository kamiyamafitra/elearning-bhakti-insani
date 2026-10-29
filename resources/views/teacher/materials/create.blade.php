@extends('teacher.layouts.dashboard')

@section('title', 'Tambah Materi Pelajaran')
@section('page-title', 'Tambah Materi Pelajaran')
@section('page-description', 'Unggah materi untuk kelas yang Anda ampu.')

@section('content')
<div class="mb-4">
    <a href="{{ route('teacher.subjects.show', ['subject' => $subject, 'tab' => 'materials']) }}#materi" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali ke {{ $subject->name }}
    </a>
</div>

<div class="card card-dashboard">
    <div class="card-body">
        @include('teacher.materials._form', [
            'action' => route('teacher.materials.store', $subject),
            'method' => 'POST',
            'submitLabel' => 'Simpan Materi',
        ])
    </div>
</div>
@endsection
