@extends('teacher.layouts.dashboard')

@section('title', "Nilai Rapor {$subject->name} - {$class->name}")
@section('page-title', "Nilai Rapor {$subject->name}")
@section('page-description', "{$class->name} · Tahun ajaran ".($class->academicYear?->name ?? '-'))

@section('content')
@php
    $setting = $subjectRecap['setting'];
    $reports = $students->map(fn ($student) => $subjectRecap['students'][$student['id']]['report']);
    $finals = $reports->pluck('final')->filter(fn ($final) => $final !== null);
    $ungradedTotal = $reports->sum('ungraded');
    $scale = $setting->predicateScale();
@endphp

<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <a href="{{ route('teacher.grades.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali</a>
    <a href="{{ route('teacher.grades.export', [$subject, $class]) }}" class="btn btn-primary"><i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>Unduh Excel</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card card-dashboard h-100">
            <div class="card-header"><h5 class="mb-0">Pengaturan Penilaian</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('teacher.grades.settings', [$subject, $class]) }}" data-report-weights>
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" for="assignment_weight">Bobot Tugas</label>
                            <div class="input-group">
                                <input type="number" min="0" max="100" step="1" class="form-control @error('assignment_weight') is-invalid @enderror" id="assignment_weight" name="assignment_weight" value="{{ old('assignment_weight', $setting->assignment_weight) }}" required>
                                <span class="input-group-text">%</span>
                            </div>
                            @error('assignment_weight')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="quiz_weight">Bobot Kuis</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="quiz_weight" value="{{ 100 - (int) old('assignment_weight', $setting->assignment_weight) }}" readonly tabindex="-1" aria-describedby="quizWeightHelp">
                                <span class="input-group-text">%</span>
                            </div>
                            <div id="quizWeightHelp" class="form-text">Otomatis: 100% dikurangi bobot tugas.</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="passing_score">KKM</label>
                            <input type="number" min="1" max="100" step="1" class="form-control @error('passing_score') is-invalid @enderror" id="passing_score" name="passing_score" value="{{ old('passing_score', $setting->passing_score) }}" required>
                            @error('passing_score')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Simpan</button>
                        </div>
                    </div>
                </form>

                <div class="predicate-scale mt-3" aria-label="Skala predikat">
                    <span>@include('grades._predicate', ['predicate' => 'A']) ≥ {{ number_format($scale['A'], 1, ',', '.') }}</span>
                    <span>@include('grades._predicate', ['predicate' => 'B']) ≥ {{ number_format($scale['B'], 1, ',', '.') }}</span>
                    <span>@include('grades._predicate', ['predicate' => 'C']) ≥ {{ $scale['C'] }}</span>
                    <span>@include('grades._predicate', ['predicate' => 'D']) &lt; {{ $setting->passing_score }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="stat-grid-wrap h-100">
            <div class="stat-grid">
                <div class="card card-dashboard stat-tile">
                    <div class="stat-icon flex-shrink-0"><i class="bi bi-people" aria-hidden="true"></i></div>
                    <div class="min-w-0"><div class="stat-tile__label">Siswa</div><div class="stat-tile__value">{{ $students->count() }}</div></div>
                </div>
                <div class="card card-dashboard stat-tile">
                    <div class="stat-icon flex-shrink-0"><i class="bi bi-graph-up" aria-hidden="true"></i></div>
                    <div class="min-w-0"><div class="stat-tile__label">Rata-rata Kelas</div><div class="stat-tile__value">{{ $finals->isNotEmpty() ? number_format($finals->avg(), 1, ',', '.') : '-' }}</div></div>
                </div>
                <div class="card card-dashboard stat-tile">
                    <div class="stat-icon flex-shrink-0"><i class="bi bi-check2-circle" aria-hidden="true"></i></div>
                    <div class="min-w-0"><div class="stat-tile__label">Tuntas</div><div class="stat-tile__value">{{ $reports->whereStrict('passed', true)->count() }}</div></div>
                </div>
                <div class="card card-dashboard stat-tile">
                    <div class="stat-icon flex-shrink-0"><i class="bi bi-exclamation-circle" aria-hidden="true"></i></div>
                    <div class="min-w-0"><div class="stat-tile__label">Belum Tuntas</div><div class="stat-tile__value">{{ $reports->whereStrict('passed', false)->count() }}</div></div>
                </div>
                <div class="card card-dashboard stat-tile">
                    <div class="stat-icon flex-shrink-0"><i class="bi bi-journal-text" aria-hidden="true"></i></div>
                    <div class="min-w-0"><div class="stat-tile__label">Tugas &amp; Kuis</div><div class="stat-tile__value">{{ $subjectRecap['assignments']->count() }}</div></div>
                </div>
                <div class="card card-dashboard stat-tile">
                    <div class="stat-icon flex-shrink-0"><i class="bi bi-hourglass-split" aria-hidden="true"></i></div>
                    <div class="min-w-0"><div class="stat-tile__label">Belum Dinilai</div><div class="stat-tile__value">{{ $ungradedTotal }}</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

@if ($ungradedTotal > 0)
    <div class="alert alert-warning">
        <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>
        Ada {{ $ungradedTotal }} pengumpulan yang belum dinilai. Nilai rapor akan berubah setelah pengumpulan tersebut dinilai.
    </div>
@endif

<div class="card card-dashboard">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Nilai Rapor</h5>
        <span class="badge text-bg-light">Tugas {{ $setting->assignment_weight }}% · Kuis {{ $setting->quiz_weight }}% · KKM {{ $setting->passing_score }}</span>
    </div>
    <div class="card-body">
        @if ($students->isEmpty())
            <div class="text-center text-muted py-4">
                <i class="bi bi-people fs-1" aria-hidden="true"></i>
                <p class="mt-2 mb-0">Belum ada siswa di kelas ini.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-2">
                    <thead>
                        <tr>
                            <th scope="col">No</th>
                            <th scope="col">Siswa</th>
                            <th scope="col" class="text-center">Rata-rata Tugas</th>
                            <th scope="col" class="text-center">Rata-rata Kuis</th>
                            <th scope="col" class="text-center">Nilai Rapor</th>
                            <th scope="col" class="text-center">Predikat</th>
                            <th scope="col" class="text-center">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            @php($row = $subjectRecap['students'][$student['id']])
                            @php($report = $row['report'])
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $student['name'] }}</div>
                                    <div class="small text-muted">
                                        NIS: {{ $student['nis'] ?: '-' }}
                                        @if ($row['missing'] > 0)
                                            · <span class="text-danger">{{ $row['missing'] }} tidak dikerjakan</span>
                                        @endif
                                        @if ($report['ungraded'] > 0)
                                            · <span class="text-warning">{{ $report['ungraded'] }} belum dinilai</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center">{{ $report['assignment_average'] !== null ? number_format($report['assignment_average'], 1, ',', '.') : '-' }}</td>
                                <td class="text-center">{{ $report['quiz_average'] !== null ? number_format($report['quiz_average'], 1, ',', '.') : '-' }}</td>
                                @include('grades._report-cells', ['report' => $report])
                                <td class="text-center">
                                    @if ($report['passed'] === true)
                                        <span class="badge text-bg-success">Tuntas</span>
                                    @elseif ($report['passed'] === false)
                                        <span class="badge text-bg-danger">Belum tuntas</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="small text-muted mb-0">
            Nilai dalam skala 0–100. Tugas dan kuis yang tidak dikerjakan setelah batas waktu dihitung 0; yang belum lewat batas waktu atau belum dinilai belum dihitung.
            Jika salah satu jenis penilaian belum ada, nilai rapor memakai jenis yang sudah ada saja.
        </p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-report-weights]').forEach((form) => {
        const assignment = form.querySelector('#assignment_weight');
        const quiz = form.querySelector('#quiz_weight');

        assignment.addEventListener('input', () => {
            const value = Math.min(100, Math.max(0, parseInt(assignment.value, 10) || 0));
            quiz.value = 100 - value;
        });
    });
</script>
@endpush
