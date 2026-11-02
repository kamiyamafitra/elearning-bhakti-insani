@extends('student.layouts.dashboard')

@section('title', 'Kuis')
@section('page-title', 'Kuis')
@section('page-description', 'Daftar kuis yang tersedia untuk mata pelajaran kelas Anda.')

@section('content')
@php
    $availableCount = $quizzes->filter(fn ($quiz) => $quiz->isOpen() && ! $attempts->get($quiz->id)?->isSubmitted())->count();
@endphp

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-4">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon">
                    <i class="bi bi-patch-question fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Kuis Tersedia</div>
                    <div class="h4 mb-0">{{ $availableCount }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon">
                    <i class="bi bi-journal-code fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Mata Pelajaran</div>
                    <div class="h4 mb-0">{{ $subjects->count() }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon">
                    <i class="bi bi-check2-circle fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Sudah Dikerjakan</div>
                    <div class="h4 mb-0">{{ $attempts->filter->isSubmitted()->count() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card card-dashboard">
    <div class="card-header bg-white border-0">
        <h5 class="mb-0">Kuis</h5>
    </div>
    <div class="card-body">
        @if ($quizzes->isEmpty())
            <div class="text-center text-muted py-5">
                <i class="bi bi-patch-question fs-1" aria-hidden="true"></i>
                <p class="mt-2 mb-1">Belum ada kuis yang tersedia.</p>
                <p class="small mb-0">Kuis dari guru pengampu akan tampil di halaman ini saat waktunya dibuka.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Judul</th>
                            <th scope="col">Mata Pelajaran</th>
                            <th scope="col">Ditutup</th>
                            <th scope="col">Durasi</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Nilai</th>
                            <th scope="col" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quizzes as $quiz)
                            @php
                                $attempt = $attempts->get($quiz->id);
                            @endphp
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $quiz->title }}</span>
                                    <div class="small text-muted">{{ $quiz->questions_count }} soal</div>
                                </td>
                                <td>{{ $quiz->subject?->name ?? '-' }}</td>
                                <td class="small text-nowrap">{{ $quiz->closes_at->format('d-m-Y H:i') }}</td>
                                <td class="text-nowrap">{{ $quiz->duration_minutes }} menit</td>
                                <td>
                                    @if ($attempt?->isSubmitted())
                                        <span class="badge text-bg-success">Selesai</span>
                                    @elseif ($attempt)
                                        <span class="badge text-bg-warning">Sedang dikerjakan</span>
                                    @else
                                        <span class="badge {{ $quiz->availabilityBadgeClass() }}">{{ $quiz->availabilityLabel() }}</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">{{ $attempt?->isSubmitted() ? number_format($attempt->score, 1, ',', '.') : '-' }}</td>
                                <td class="text-end text-nowrap">
                                    @php
                                        $canWork = $attempt ? ! $attempt->isSubmitted() : $quiz->isOpen();
                                        $actionLabel = match (true) {
                                            $attempt?->isSubmitted() => 'Lihat Nilai',
                                            $attempt !== null => 'Lanjutkan',
                                            $quiz->isOpen() => 'Kerjakan',
                                            default => 'Detail',
                                        };
                                    @endphp
                                    <a href="{{ route('student.quizzes.show', $quiz) }}" class="btn btn-sm {{ $canWork ? 'btn-primary' : 'btn-light' }}">{{ $actionLabel }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
