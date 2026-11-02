@extends('teacher.layouts.dashboard')

@section('title', 'Ubah Kuis')
@section('page-title', 'Ubah Kuis')
@section('page-description', $quiz->title)

@section('content')
<div class="mb-4">
    <a href="{{ route('teacher.quizzes.show', $quiz) }}" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

<div class="card card-dashboard">
    <div class="card-body">
        <form method="POST" action="{{ route('teacher.quizzes.update', $quiz) }}">
            @csrf
            @method('PUT')
            @include('teacher.quizzes._form')
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endsection
