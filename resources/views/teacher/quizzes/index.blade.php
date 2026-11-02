@extends('teacher.layouts.dashboard')

@section('title', 'Kuis')
@section('page-title', 'Kuis')
@section('page-description', 'Kuis pada mata pelajaran yang Anda ampu.')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-4">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon">
                    <i class="bi bi-patch-question fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Kuis Dibuat</div>
                    <div class="h4 mb-0">{{ $quizzes->count() }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon">
                    <i class="bi bi-journal-bookmark fs-4"></i>
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
                    <div class="text-muted small">Terjadwal atau Dibuka</div>
                    <div class="h4 mb-0">{{ $quizzes->filter(fn ($quiz) => $quiz->isPublished() && ! $quiz->hasClosed())->count() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card card-dashboard">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center gap-2">
        <h5 class="mb-0">Kuis</h5>
        @if ($subjects->isNotEmpty())
            <a href="{{ route('teacher.quizzes.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Buat Kuis
            </a>
        @endif
    </div>
    <div class="card-body">
        @if ($quizzes->isEmpty())
            <div class="text-center text-muted py-5">
                <i class="bi bi-patch-question fs-1" aria-hidden="true"></i>
                <p class="mt-2 mb-1">Belum ada kuis yang dibuat.</p>
                <p class="small mb-0">Buat kuis, tambahkan soal pilihan ganda, lalu jadwalkan. Kuis terbuka dan tertutup otomatis sesuai waktunya.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Judul</th>
                            <th scope="col">Mata Pelajaran</th>
                            <th scope="col">Kelas</th>
                            <th scope="col">Waktu</th>
                            <th scope="col" class="text-center">Soal</th>
                            <th scope="col" class="text-center">Dikerjakan</th>
                            <th scope="col" class="text-center">Rata-rata</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quizzes as $quiz)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $quiz->title }}</span>
                                </td>
                                <td>{{ $quiz->subject?->name ?? '-' }}</td>
                                <td>{{ $quiz->class?->name ?? '-' }}</td>
                                <td class="small text-nowrap">
                                    {{ $quiz->opens_at->format('d-m-Y H:i') }}<br>
                                    <span class="text-muted">s.d. {{ $quiz->closes_at->format('d-m-Y H:i') }}</span>
                                </td>
                                <td class="text-center">{{ $quiz->questions_count }}</td>
                                <td class="text-center">{{ $quiz->attempts_count }}</td>
                                <td class="text-center">{{ $quiz->average_score !== null ? number_format($quiz->average_score, 1, ',', '.') : '-' }}</td>
                                <td><span class="badge {{ $quiz->availabilityBadgeClass() }}">{{ $quiz->availabilityLabel() }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('teacher.quizzes.show', $quiz) }}" class="btn btn-sm btn-primary">Buka</a>
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
