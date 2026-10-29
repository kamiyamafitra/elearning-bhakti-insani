@extends('teacher.layouts.dashboard')

@section('title', 'Buat Tugas')
@section('page-title', 'Buat Tugas')
@section('page-description', 'Buat tugas untuk kelas yang Anda ampu, simpan sebagai draf atau publikasikan.')

@section('content')
<div class="mb-4">
    <a href="{{ route('teacher.subjects.show', ['subject' => $subject, 'tab' => 'assignments']) }}#tugas" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali ke {{ $subject->name }}
    </a>
</div>

<div class="card card-dashboard">
    <div class="card-body">
        @include('teacher.assignments._form', [
            'action' => route('teacher.assignments.store', $subject),
            'method' => 'POST',
            'submitLabel' => 'Simpan Tugas',
        ])
    </div>
</div>
@endsection
