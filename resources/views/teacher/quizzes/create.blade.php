@extends('teacher.layouts.dashboard')

@section('title', 'Buat Kuis')
@section('page-title', 'Buat Kuis')
@section('page-description', 'Atur jadwal dan durasi, lalu tambahkan soal pada langkah berikutnya.')

@section('content')
<div class="mb-4">
    <a href="{{ route('teacher.quizzes.index') }}" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

<div class="card card-dashboard">
    <div class="card-body">
        <form method="POST" action="{{ route('teacher.quizzes.store') }}">
            @csrf
            @include('teacher.quizzes._form')
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Simpan dan Tambah Soal</button>
            </div>
        </form>
    </div>
</div>
@endsection
