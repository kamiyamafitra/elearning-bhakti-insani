@extends('student.layouts.dashboard')

@section('title', 'Nilai Saya')
@section('page-title', 'Nilai Saya')
@section('page-description', 'Nilai rapor sementara dan rincian nilai tugas serta kuis per mata pelajaran.')

@section('content')
@if ($subjects->isEmpty())
    <div class="card card-dashboard">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Rekap Nilai</h5>
            <span class="badge text-bg-light">{{ $classroom?->name ?? 'Kelas belum ditetapkan' }}</span>
        </div>
        <div class="card-body">
            <div class="text-center text-muted py-5">
                <i class="bi bi-award fs-1 d-block mb-2" aria-hidden="true"></i>
                <span class="d-block mb-1">Belum ada nilai yang dipublikasikan.</span>
                <span class="small">Nilai akan tampil di sini setelah guru menerbitkan tugas atau kuis.</span>
            </div>
        </div>
    </div>
@else
    @php
        $reports = $subjects->pluck('result.report');
        $finals = $reports->pluck('final')->filter(fn ($final) => $final !== null);
    @endphp

    <div class="stat-grid-wrap mb-3">
        <div class="stat-grid">
            <div class="card card-dashboard stat-tile">
                <div class="stat-icon flex-shrink-0"><i class="bi bi-graph-up" aria-hidden="true"></i></div>
                <div class="min-w-0"><div class="stat-tile__label">Rata-rata Nilai Rapor</div><div class="stat-tile__value">{{ $finals->isNotEmpty() ? number_format($finals->avg(), 1, ',', '.') : '-' }}</div></div>
            </div>
            <div class="card card-dashboard stat-tile">
                <div class="stat-icon flex-shrink-0"><i class="bi bi-check2-circle" aria-hidden="true"></i></div>
                <div class="min-w-0"><div class="stat-tile__label">Mapel Tuntas</div><div class="stat-tile__value">{{ $reports->whereStrict('passed', true)->count() }}</div></div>
            </div>
            <div class="card card-dashboard stat-tile">
                <div class="stat-icon flex-shrink-0"><i class="bi bi-exclamation-circle" aria-hidden="true"></i></div>
                <div class="min-w-0"><div class="stat-tile__label">Belum Tuntas</div><div class="stat-tile__value">{{ $reports->whereStrict('passed', false)->count() }}</div></div>
            </div>
        </div>
    </div>

    @foreach ($subjects as $subjectRecap)
        @php
            $report = $subjectRecap['result']['report'];
            $cells = $subjectRecap['result']['cells'];
            $setting = $subjectRecap['setting'];
        @endphp
        <div class="card card-dashboard mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">{{ $subjectRecap['subject']->name }}</h5>
                <span class="badge text-bg-light">Tugas {{ $setting->assignment_weight }}% · Kuis {{ $setting->quiz_weight }}% · KKM {{ $setting->passing_score }}</span>
            </div>
            <div class="card-body">
                <div class="report-summary mb-3">
                    <div class="report-summary__final">
                        <div class="report-summary__label">Nilai Rapor</div>
                        <div class="report-summary__value {{ $report['passed'] === false ? 'text-danger' : '' }}">
                            {{ $report['final'] !== null ? number_format($report['final'], 1, ',', '.') : '-' }}
                        </div>
                    </div>
                    <div>
                        <div class="report-summary__label">Predikat</div>
                        @include('grades._predicate', ['predicate' => $report['predicate']])
                    </div>
                    <div>
                        <div class="report-summary__label">Keterangan</div>
                        @if ($report['passed'] === true)
                            <span class="badge text-bg-success">Tuntas</span>
                        @elseif ($report['passed'] === false)
                            <span class="badge text-bg-danger">Belum tuntas</span>
                        @else
                            <span class="text-muted small">Belum ada nilai</span>
                        @endif
                    </div>
                    <div>
                        <div class="report-summary__label">Rata-rata Tugas</div>
                        <span class="fw-semibold">{{ $report['assignment_average'] !== null ? number_format($report['assignment_average'], 1, ',', '.') : '-' }}</span>
                    </div>
                    <div>
                        <div class="report-summary__label">Rata-rata Kuis</div>
                        <span class="fw-semibold">{{ $report['quiz_average'] !== null ? number_format($report['quiz_average'], 1, ',', '.') : '-' }}</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Penilaian</th>
                                <th class="text-center">Nilai</th>
                                <th>Umpan Balik</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subjectRecap['columns'] as $column)
                                @php($cell = $cells[$column->key])
                                <tr>
                                    <td class="text-break">{{ $column->title }}</td>
                                    <td class="text-center">
                                        @switch($cell['status'])
                                            @case('graded')
                                                <span class="fw-semibold">{{ is_float($cell['score']) ? number_format($cell['score'], 1, ',', '.') : $cell['score'] }}</span>
                                                <span class="text-muted small">/ {{ $cell['max'] }}</span>
                                                @break
                                            @case('ungraded')
                                                <span class="badge text-bg-warning">Belum dinilai</span>
                                                @break
                                            @case('missing')
                                                <span class="badge text-bg-danger" title="Dihitung 0 di nilai rapor">Tidak dikerjakan</span>
                                                @break
                                            @default
                                                <span class="text-muted small" title="Belum lewat batas waktu">Belum dikerjakan</span>
                                        @endswitch
                                    </td>
                                    <td class="small text-break">{{ $cell['feedback'] ?: '-' }}</td>
                                    <td class="small text-nowrap">{{ $cell['date']?->format('d-m-Y') ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach

    <p class="small text-muted mb-0">
        Nilai rapor bersifat sementara dan dapat berubah selama semester berjalan. Tugas dan kuis yang tidak dikerjakan setelah batas waktu dihitung 0.
    </p>
@endif
@endsection
