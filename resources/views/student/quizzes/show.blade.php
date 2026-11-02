@extends('student.layouts.dashboard')

@section('title', $quiz->title)
@section('page-title', $quiz->title)
@section('page-description', "Kuis {$quiz->subject?->name}")

@section('content')
<div class="mb-4">
    <a href="{{ route('student.quizzes.index') }}" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

<div class="card card-dashboard">
    <div class="card-body">
        @if ($attempt?->isSubmitted())
            <div class="text-center py-4">
                <div class="text-muted small">Nilai Anda</div>
                <div class="display-5 fw-semibold">{{ number_format($attempt->score, 1, ',', '.') }}</div>
                <div class="text-muted small">Dari {{ \App\Models\Quiz::MAX_SCORE }}. Dikumpulkan {{ $attempt->submitted_at->format('d-m-Y H:i') }}.</div>
                @unless ($review)
                    <p class="small text-muted mt-3 mb-0">Jawaban benar dan salah dapat dilihat setelah kuis ditutup pada {{ $quiz->closes_at->format('d-m-Y H:i') }}.</p>
                @endunless
            </div>
        @else
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="text-muted small">Waktu</div>
                    <div class="fw-semibold">{{ $quiz->opens_at->format('d-m-Y H:i') }} s.d. {{ $quiz->closes_at->format('d-m-Y H:i') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Durasi</div>
                    <div class="fw-semibold">{{ $quiz->duration_minutes }} menit</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Jumlah Soal</div>
                    <div class="fw-semibold">{{ $quiz->questions_count }}</div>
                </div>
            </div>

            @if ($quiz->instructions)
                <p class="text-break" style="white-space: pre-line;">{{ $quiz->instructions }}</p>
            @endif

            @if ($attempt)
                <a href="{{ route('student.quizzes.attempt', $quiz) }}" class="btn btn-primary">Lanjutkan Mengerjakan</a>
            @elseif ($quiz->isOpen())
                <div class="alert alert-warning">
                    Kesempatan hanya satu kali. Waktu mulai berjalan saat Anda menekan tombol dan tidak berhenti jika halaman ditutup. Soal dikerjakan berurutan, dan Anda tidak dapat kembali ke soal sebelumnya.
                </div>
                <form method="POST" action="{{ route('student.quizzes.start', $quiz) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Mulai Mengerjakan</button>
                </form>
            @endif
        @endif
    </div>
</div>

@if ($review)
    @include('student.quizzes._review')
@endif
@endsection
