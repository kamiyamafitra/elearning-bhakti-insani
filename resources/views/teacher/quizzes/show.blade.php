@extends('teacher.layouts.dashboard')

@section('title', $quiz->title)
@section('page-title', $quiz->title)
@section('page-description', "Kuis {$quiz->subject?->name} kelas {$quiz->class?->name}")

@section('content')
@php
    $isLocked = $attempts->isNotEmpty();
    $scorePerQuestion = $quiz->questions->isNotEmpty() ? \App\Models\Quiz::MAX_SCORE / $quiz->questions->count() : null;
@endphp

<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="{{ route('teacher.quizzes.index') }}" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
    <a href="{{ route('teacher.quizzes.edit', $quiz) }}" class="btn btn-light">
        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Ubah Pengaturan
    </a>
    @unless ($quiz->hasClosed())
        <form method="POST" action="{{ route('teacher.quizzes.publish', $quiz) }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn {{ $quiz->isPublished() ? 'btn-light' : 'btn-primary' }}" @disabled($quiz->isPublished() && $isLocked)>
                {{ $quiz->isPublished() ? 'Batalkan Jadwal' : 'Jadwalkan' }}
            </button>
        </form>
    @endunless
    @unless ($isLocked)
        <form method="POST" action="{{ route('teacher.quizzes.destroy', $quiz) }}" onsubmit="return confirm('Hapus beserta semua soalnya?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">Hapus</button>
        </form>
    @endunless
</div>

@if ($quiz->hasClosed())
    <div class="alert alert-info" role="alert">
        Waktu kuis sudah berakhir, jadi siswa tidak dapat mengerjakannya lagi. Nilai tetap tersimpan. Ubah jadwal untuk membukanya kembali.
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card card-dashboard h-100">
            <div class="card-body">
                <div class="text-muted small">Status</div>
                <span class="badge {{ $quiz->availabilityBadgeClass() }}">{{ $quiz->availabilityLabel() }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card card-dashboard h-100">
            <div class="card-body">
                <div class="text-muted small">Waktu</div>
                <div class="small fw-semibold">{{ $quiz->opens_at->format('d-m-Y H:i') }} s.d. {{ $quiz->closes_at->format('d-m-Y H:i') }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card card-dashboard h-100">
            <div class="card-body">
                <div class="text-muted small">Durasi</div>
                <div class="fw-semibold">{{ $quiz->duration_minutes }} menit{{ $quiz->shuffles ? ', diacak' : '' }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card card-dashboard h-100">
            <div class="card-body">
                <div class="text-muted small">Rata-rata Nilai</div>
                <div class="fw-semibold">{{ $averageScore !== null ? number_format($averageScore, 1, ',', '.') : '-' }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card card-dashboard mb-3">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center gap-2">
        <h5 class="mb-0">Soal ({{ $quiz->questions->count() }}{{ $scorePerQuestion ? ', masing-masing bernilai '.rtrim(rtrim(number_format($scorePerQuestion, 2, ',', '.'), '0'), ',') : '' }})</h5>
        @unless ($isLocked)
            <a href="{{ route('teacher.quizzes.questions.create', $quiz) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Tambah Soal
            </a>
        @endunless
    </div>
    <div class="card-body">
        @if ($isLocked)
            <p class="small text-muted">Soal terkunci karena siswa sudah mulai mengerjakan.</p>
        @endif

        @forelse ($quiz->questions as $question)
            <div class="border-bottom pb-3 mb-3">
                <div class="d-flex justify-content-between gap-3">
                    <div class="fw-semibold text-break">{{ $loop->iteration }}. {{ $question->question }}</div>
                </div>
                @if ($question->image_path)
                    <img src="{{ route('teacher.quizzes.questions.image', [$quiz, $question]) }}" alt="Gambar soal {{ $loop->iteration }}" class="img-fluid rounded mt-2" style="max-height: 200px;" loading="lazy">
                @endif
                <ul class="list-unstyled small mt-2 mb-2">
                    @foreach ($question->options as $option)
                        <li class="text-break {{ $option->is_correct ? 'text-success fw-semibold' : '' }}">
                            {{ chr(65 + $loop->index) }}. {{ $option->text }}
                            @if ($option->is_correct)
                                <i class="bi bi-check-circle-fill" aria-label="Jawaban benar"></i>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @unless ($isLocked)
                    <a href="{{ route('teacher.quizzes.questions.edit', [$quiz, $question]) }}" class="btn btn-sm btn-light">Ubah</a>
                    <form method="POST" action="{{ route('teacher.quizzes.questions.destroy', [$quiz, $question]) }}" class="d-inline" onsubmit="return confirm('Hapus soal ini?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                    </form>
                @endunless
            </div>
        @empty
            <p class="text-muted text-center py-4 mb-0">Belum ada soal. Tambahkan minimal satu soal sebelum menjadwalkan.</p>
        @endforelse
    </div>
</div>

<div class="card card-dashboard">
    <div class="card-header bg-white border-0">
        <h5 class="mb-0">Peringkat Siswa</h5>
    </div>
    <div class="card-body">
        @if ($leaderboard->isEmpty())
            <p class="text-muted text-center py-4 mb-0">Belum ada siswa di kelas ini.</p>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col" class="text-center">Peringkat</th>
                            <th scope="col">Nama Siswa</th>
                            <th scope="col">NIS</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-center">Dijawab</th>
                            <th scope="col" class="text-center">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($leaderboard as $row)
                            @php($attempt = $row['attempt'])
                            <tr>
                                <td class="text-center fw-semibold">
                                    @if ($row['rank'] === 1)
                                        <i class="bi bi-trophy-fill text-warning me-1" aria-hidden="true"></i>
                                    @endif
                                    {{ $row['rank'] ?? '-' }}
                                </td>
                                <td>{{ $row['student']->name }}</td>
                                <td>{{ $row['student']->studentProfile?->nis ?? '-' }}</td>
                                <td>
                                    @if ($attempt?->isSubmitted())
                                        <span class="badge text-bg-success">Selesai</span>
                                    @elseif ($attempt)
                                        <span class="badge text-bg-warning">Mengerjakan</span>
                                    @else
                                        <span class="badge text-bg-light">Belum mengerjakan</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $attempt ? $attempt->answers_count.'/'.$quiz->questions->count() : '-' }}</td>
                                <td class="text-center fw-semibold">{{ $attempt?->isSubmitted() ? number_format($attempt->score, 1, ',', '.') : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
