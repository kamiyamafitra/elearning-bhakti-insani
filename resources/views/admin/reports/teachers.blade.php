@extends('admin.layouts.dashboard')

@section('title', 'Laporan Aktivitas Guru')

@section('content')
@php
    $exportQuery = array_filter([
        'academic_year_id' => $filters['academicYear']?->id,
        'from' => $filters['from']?->format('Y-m-d'),
        'to' => $filters['to']?->format('Y-m-d'),
        'q' => $filters['search'],
    ]);
    $gradedPercent = $totals['submissions'] > 0 ? round($totals['graded'] / $totals['submissions'] * 100) : null;
@endphp

<div class="directory-page">
    <section class="directory-hero">
        <div class="directory-hero__content">
            <div class="directory-hero__heading">
                <h1 class="directory-hero__title">Laporan Aktivitas Guru</h1>
                <span class="directory-count-badge">
                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                    {{ $filters['academicYear']?->name ?? 'Semua tahun ajaran' }}
                </span>
            </div>
            <p class="directory-hero__description">
                Jumlah materi, tugas, dan penilaian yang dibuat setiap guru per kelas dan mata pelajaran.
            </p>
        </div>
        <a href="{{ route('admin.reports.teachers.export', $exportQuery) }}" class="btn btn-primary directory-hero__action">
            <i class="bi bi-file-earmark-excel" aria-hidden="true"></i>
            Unduh Excel
        </a>
    </section>

    <section class="directory-stats" aria-label="Ringkasan aktivitas guru">
        <div class="directory-stat">
            <span class="directory-stat__icon"><i class="bi bi-person-workspace" aria-hidden="true"></i></span>
            <div class="directory-stat__body">
                <span class="directory-stat__label">Guru Pengampu</span>
                <span class="directory-stat__value">{{ $totals['teachers'] }}</span>
            </div>
        </div>
        <div class="directory-stat">
            <span class="directory-stat__icon"><i class="bi bi-journal-text" aria-hidden="true"></i></span>
            <div class="directory-stat__body">
                <span class="directory-stat__label">Materi / Tugas</span>
                <span class="directory-stat__value">{{ $totals['materials'] }} / {{ $totals['assignments'] }}</span>
            </div>
        </div>
        <div class="directory-stat">
            <span class="directory-stat__icon"><i class="bi bi-check2-square" aria-hidden="true"></i></span>
            <div class="directory-stat__body">
                <span class="directory-stat__label">Pengumpulan Dinilai</span>
                <span class="directory-stat__value">{{ $gradedPercent !== null ? $gradedPercent.'%' : '-' }}</span>
            </div>
        </div>
        <div class="directory-stat">
            <span class="directory-stat__icon"><i class="bi bi-exclamation-circle" aria-hidden="true"></i></span>
            <div class="directory-stat__body">
                <span class="directory-stat__label">Belum Ada Aktivitas</span>
                <span class="directory-stat__value">{{ $totals['inactiveTeachers'] }} guru</span>
            </div>
        </div>
    </section>

    <form method="GET" action="{{ route('admin.reports.teachers') }}" class="directory-toolbar" role="search">
        <div class="directory-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" name="q" class="form-control" placeholder="Cari nama guru..." value="{{ $filters['search'] }}" aria-label="Cari nama guru">
        </div>

        <div class="directory-filter">
            <label for="academic_year_id" class="directory-filter__label">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                Tahun Ajaran
            </label>
            <select name="academic_year_id" id="academic_year_id" class="form-select">
                @foreach ($academicYears as $year)
                    <option value="{{ $year->id }}" @selected($filters['academicYear']?->id === $year->id)>
                        {{ $year->name }}{{ $year->is_active ? ' (aktif)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="directory-filter">
            <label for="from" class="directory-filter__label">Dari</label>
            <input type="date" name="from" id="from" class="form-control @error('from') is-invalid @enderror" value="{{ $filters['from']?->format('Y-m-d') }}">
        </div>

        <div class="directory-filter">
            <label for="to" class="directory-filter__label">Sampai</label>
            <input type="date" name="to" id="to" class="form-control @error('to') is-invalid @enderror" value="{{ $filters['to']?->format('Y-m-d') }}">
        </div>

        <button class="btn btn-primary directory-toolbar__button" type="submit">
            <i class="bi bi-funnel me-1" aria-hidden="true"></i>
            Terapkan
        </button>

        @if ($filters['search'] !== '' || $filters['from'] || $filters['to'])
            <a href="{{ route('admin.reports.teachers', array_filter(['academic_year_id' => $filters['academicYear']?->id])) }}" class="btn btn-light directory-toolbar__reset">Reset</a>
        @endif
    </form>

    @error('to')
        <div class="alert alert-danger small">Tanggal "Sampai" harus sama dengan atau setelah tanggal "Dari".</div>
    @enderror

    <div class="directory-table-card">
        @if ($teachers->isEmpty())
            <div class="directory-empty">
                <div class="directory-empty__icon"><i class="bi bi-clipboard-data" aria-hidden="true"></i></div>
                <h2 class="directory-empty__title">Belum ada data aktivitas guru.</h2>
                <p class="directory-empty__description">
                    Laporan berisi guru yang mengampu kelas di tahun ajaran ini atau sudah membuat materi dan tugas.
                    Atur guru pengampu melalui menu Mata Pelajaran.
                </p>
            </div>
        @else
            <div class="table-responsive">
                <table class="directory-table">
                    <thead>
                        <tr>
                            <th scope="col">Guru</th>
                            <th scope="col" class="text-center">Kelas &amp; Mapel</th>
                            <th scope="col" class="text-center">Materi</th>
                            <th scope="col" class="text-center">Tugas</th>
                            <th scope="col" class="text-center">Dinilai</th>
                            <th scope="col">Aktivitas Terakhir</th>
                            <th scope="col" class="text-end"><span class="visually-hidden">Rincian</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teachers as $index => $teacher)
                            @php($hasActivity = $teacher['materials'] > 0 || $teacher['assignments'] > 0)
                            <tr>
                                <td>
                                    <div class="directory-person">
                                        <span class="directory-avatar directory-avatar--teacher" aria-hidden="true">
                                            {{ strtoupper(substr($teacher['name'], 0, 1)) }}
                                        </span>
                                        <div>
                                            <div class="directory-person__name">
                                                @if ($teacher['teacher']->teacherProfile)
                                                    <a href="{{ route('admin.teachers.show', $teacher['teacher']->teacherProfile) }}" class="text-reset">{{ $teacher['name'] }}</a>
                                                @else
                                                    {{ $teacher['name'] }}
                                                @endif
                                            </div>
                                            <div class="directory-person__meta">NIP: {{ $teacher['nip'] ?: '-' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">{{ $teacher['rows']->count() }}</td>
                                <td class="text-center">
                                    {{ $teacher['materials'] }}
                                    @if ($teacher['materials'] > $teacher['published_materials'])
                                        <div class="small text-muted">{{ $teacher['published_materials'] }} terbit</div>
                                    @endif
                                </td>
                                <td class="text-center">{{ $teacher['assignments'] }}</td>
                                <td class="text-center">
                                    @if ($teacher['submissions'] > 0)
                                        <span class="directory-badge {{ $teacher['graded'] === $teacher['submissions'] ? 'directory-badge--active' : 'directory-badge--inactive' }}">
                                            {{ $teacher['graded'] }} / {{ $teacher['submissions'] }}
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($hasActivity)
                                        <span title="{{ $teacher['last_activity']?->format('d-m-Y H:i') }}">{{ $teacher['last_activity']?->locale('id')->diffForHumans() ?? '-' }}</span>
                                    @else
                                        <span class="directory-badge directory-badge--inactive">Belum ada aktivitas</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#teacher-rows-{{ $index }}" aria-expanded="false" aria-controls="teacher-rows-{{ $index }}">
                                        <i class="bi bi-chevron-down" aria-hidden="true"></i> Rincian
                                    </button>
                                </td>
                            </tr>
                            <tr class="collapse" id="teacher-rows-{{ $index }}">
                                <td colspan="7" class="bg-light">
                                    <table class="table table-sm mb-0 bg-transparent">
                                        <thead>
                                            <tr>
                                                <th scope="col">Kelas</th>
                                                <th scope="col">Mata Pelajaran</th>
                                                <th scope="col" class="text-center">Materi</th>
                                                <th scope="col" class="text-center">Tugas</th>
                                                <th scope="col" class="text-center">Dinilai</th>
                                                <th scope="col">Aktivitas Terakhir</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($teacher['rows'] as $row)
                                                <tr>
                                                    <td>{{ $row['class'] }}</td>
                                                    <td>{{ $row['subject'] }}</td>
                                                    <td class="text-center">{{ $row['materials'] }}</td>
                                                    <td class="text-center">{{ $row['assignments'] }}</td>
                                                    <td class="text-center">{{ $row['submissions'] > 0 ? $row['graded'].' / '.$row['submissions'] : '-' }}</td>
                                                    <td>{{ $row['last_activity']?->format('d-m-Y H:i') ?? '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
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
