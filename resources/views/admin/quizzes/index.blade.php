@extends('admin.layouts.dashboard')

@section('title', 'Kuis')

@section('content')
<div class="directory-page">
    <section class="directory-hero">
        <div class="directory-hero__content">
            <div class="directory-hero__heading">
                <h1 class="directory-hero__title">Kuis</h1>
                <span class="directory-count-badge">
                    <i class="bi bi-patch-question" aria-hidden="true"></i>
                    {{ $quizzes->total() }} kuis
                </span>
            </div>
            <p class="directory-hero__description">
                Pantauan kuis yang dibuat guru. Nilainya masuk ke Rekap Nilai.
            </p>
        </div>
    </section>

    <form method="GET" action="{{ route('admin.quizzes.index') }}" class="directory-toolbar">
        <div class="directory-filter">
            <label for="class_id" class="directory-filter__label">
                <i class="bi bi-funnel" aria-hidden="true"></i>
                Kelas
            </label>
            <select name="class_id" id="class_id" class="form-select">
                <option value="">Semua</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected($classId === $class->id)>{{ $class->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary directory-toolbar__button" type="submit">Terapkan</button>
    </form>

    <div class="directory-table-card">
        @if ($quizzes->isEmpty())
            <div class="directory-empty">
                <div class="directory-empty__icon"><i class="bi bi-patch-question" aria-hidden="true"></i></div>
                <h2 class="directory-empty__title">Belum ada kuis.</h2>
                <p class="directory-empty__description">Kuis yang dibuat guru akan muncul di sini.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="directory-table">
                    <thead>
                        <tr>
                            <th scope="col">Judul</th>
                            <th scope="col">Mata Pelajaran</th>
                            <th scope="col">Kelas</th>
                            <th scope="col">Guru</th>
                            <th scope="col">Ditutup</th>
                            <th scope="col">Soal</th>
                            <th scope="col">Selesai</th>
                            <th scope="col">Rata-rata</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quizzes as $quiz)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $quiz->title }}</div>
                                </td>
                                <td>{{ $quiz->subject?->name ?? '-' }}</td>
                                <td>{{ $quiz->class?->name ?? '-' }}</td>
                                <td>{{ $quiz->teacher?->name ?? '-' }}</td>
                                <td class="small text-nowrap">{{ $quiz->closes_at->format('d-m-Y H:i') }}</td>
                                <td>{{ $quiz->questions_count }}</td>
                                <td>{{ $quiz->submitted_count }}</td>
                                <td>{{ $quiz->average_score !== null ? number_format($quiz->average_score, 1, ',', '.') : '-' }}</td>
                                <td><span class="badge {{ $quiz->availabilityBadgeClass() }}">{{ $quiz->availabilityLabel() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($quizzes->hasPages())
                <div class="directory-pagination p-3">
                    {{ $quizzes->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
