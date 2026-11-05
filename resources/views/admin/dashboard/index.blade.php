@extends('admin.layouts.dashboard')
@section('title', 'Dashboard Administrator')

@section('page-title', 'Dashboard Administrator')
@section('page-description', 'Ringkasan data dan aktivitas terkini di lingkungan pembelajaran. Semester '.$school->semesterLabel().($activeYear ? ' '.$activeYear->name : '').'.')

@section('content')
@unless ($activeYear)
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="alert">
        <span><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i> Belum ada tahun ajaran aktif. Data kelas dihitung dari semua tahun ajaran.</span>
        <a href="{{ route('admin.academic-years.index') }}" class="btn btn-sm btn-warning">Atur Tahun Ajaran</a>
    </div>
@endunless

@php
    $statCards = [
        ['label' => 'Siswa Aktif', 'value' => $stats['activeStudents'], 'icon' => 'bi-person-badge', 'url' => route('admin.students.index')],
        ['label' => 'Guru Aktif', 'value' => $stats['activeTeachers'], 'icon' => 'bi-person-workspace', 'url' => route('admin.teachers.index')],
        ['label' => $activeYear ? 'Kelas '.$activeYear->name : 'Total Kelas', 'value' => $stats['classes'], 'icon' => 'bi-grid', 'url' => route('admin.classes.index', $activeYear ? ['academic_year_id' => $activeYear->id] : [])],
        ['label' => 'Mata Pelajaran', 'value' => $stats['subjects'], 'icon' => 'bi-journal-bookmark', 'url' => route('admin.subjects.index')],
        ['label' => 'Tugas Berjalan', 'value' => $stats['openAssignments'], 'icon' => 'bi-clipboard-check', 'url' => null],
        ['label' => 'Belum Dinilai', 'value' => $stats['ungradedSubmissions'], 'icon' => 'bi-hourglass-split', 'url' => null],
    ];
@endphp

<div class="row g-3 mb-3">
    @foreach ($statCards as $card)
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card card-dashboard h-100 {{ $card['url'] ? 'position-relative' : '' }}">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon flex-shrink-0">
                        <i class="bi {{ $card['icon'] }} fs-5" aria-hidden="true"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-muted small text-truncate">{{ $card['label'] }}</div>
                        <div class="h4 mb-0">{{ $card['value'] }}</div>
                    </div>
                </div>
                @if ($card['url'])
                    <a href="{{ $card['url'] }}" class="stretched-link" aria-label="Lihat {{ $card['label'] }}"></a>
                @endif
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">Aktivitas Terbaru</h5>
                <div class="d-flex flex-wrap gap-2 small" aria-label="Aktivitas hari ini">
                    <span class="badge text-bg-light">Hari ini:</span>
                    <span class="badge text-bg-light"><i class="bi bi-journal-arrow-up me-1" aria-hidden="true"></i>{{ $today['materials'] }} materi</span>
                    <span class="badge text-bg-light"><i class="bi bi-clipboard-plus me-1" aria-hidden="true"></i>{{ $today['assignments'] }} tugas</span>
                    <span class="badge text-bg-light"><i class="bi bi-send-check me-1" aria-hidden="true"></i>{{ $today['submissions'] }} pengumpulan</span>
                </div>
            </div>
            <div class="card-body pt-0">
                @forelse ($recentActivities as $activity)
                    <div class="d-flex gap-3 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                        <span class="text-{{ $activity['color'] }} fs-5 flex-shrink-0" aria-hidden="true"><i class="bi {{ $activity['icon'] }}"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-break"><span class="fw-semibold">{{ $activity['actor'] }}</span> {{ $activity['description'] }}</div>
                            <div class="small text-muted" title="{{ $activity['time']->format('d-m-Y H:i') }}">{{ $activity['time']->locale('id')->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-activity fs-1" aria-hidden="true"></i>
                        <p class="mt-2 mb-0">Belum ada aktivitas. Aktivitas guru dan siswa akan muncul di sini.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Pengumuman Terbaru</h5>
                <a href="{{ route('admin.announcements.index') }}" class="small">Semua</a>
            </div>
            <div class="card-body pt-0">
                @forelse ($latestAnnouncements as $announcement)
                    <a href="{{ route('admin.announcements.show', $announcement) }}" class="link-row">
                        <div class="fw-semibold text-body text-break">{{ $announcement->title }}</div>
                        <div class="small text-muted">{{ $announcement->published_at?->format('d-m-Y H:i') ?? 'Belum dijadwalkan' }}</div>
                    </a>
                @empty
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-megaphone fs-1" aria-hidden="true"></i>
                        <p class="mt-2 mb-0">Belum ada pengumuman</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Tugas Berakhir 7 Hari ke Depan</h5>
            </div>
            <div class="card-body pt-0">
                @if ($dueSoonAssignments->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-calendar-check fs-1" aria-hidden="true"></i>
                        <p class="mt-2 mb-0">Tidak ada tugas yang berakhir dalam 7 hari ke depan.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Tugas</th>
                                    <th scope="col">Batas Waktu</th>
                                    <th scope="col" class="text-end">Terkumpul</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dueSoonAssignments as $assignment)
                                    @php($classSize = $assignment->class?->students_count ?? 0)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-break">{{ $assignment->title }}</div>
                                            <div class="small text-muted">{{ $assignment->subject?->name }} &middot; {{ $assignment->class?->name }} &middot; {{ $assignment->teacher?->name }}</div>
                                        </td>
                                        <td class="text-nowrap small">
                                            {{ $assignment->due_at->format('d-m-Y H:i') }}
                                            <div class="text-muted">{{ $assignment->due_at->locale('id')->diffForHumans() }}</div>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <span class="badge {{ $classSize > 0 && $assignment->submissions_count >= $classSize ? 'text-bg-success' : 'text-bg-light' }}">
                                                {{ $assignment->submissions_count }} / {{ $classSize }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Penilaian Tertunda per Guru</h5>
            </div>
            <div class="card-body pt-0">
                @forelse ($ungradedByTeacher as $row)
                    <div class="d-flex justify-content-between align-items-center gap-2 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                        @if ($row['teacher']->teacherProfile)
                            <a href="{{ route('admin.teachers.show', $row['teacher']->teacherProfile) }}" class="text-break">{{ $row['teacher']->name }}</a>
                        @else
                            <span class="text-break">{{ $row['teacher']->name }}</span>
                        @endif
                        <span class="badge text-bg-danger">{{ $row['total'] }} belum dinilai</span>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-check2-all fs-1" aria-hidden="true"></i>
                        <p class="mt-2 mb-0">Semua pengumpulan sudah dinilai.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="card card-dashboard">
    <div class="card-header bg-white border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0">Guru Belum Mengunggah Materi</h5>
        <span class="small text-muted">Guru pengampu {{ $activeYear ? 'tahun ajaran '.$activeYear->name : '' }} yang belum memiliki materi di kelasnya</span>
    </div>
    <div class="card-body pt-0">
        @if ($teachersWithoutMaterials->isEmpty())
            <div class="text-center text-muted py-4">
                <i class="bi bi-emoji-smile fs-1" aria-hidden="true"></i>
                <p class="mt-2 mb-0">Semua guru pengampu sudah mengunggah materi.</p>
            </div>
        @else
            <div class="row g-2">
                @foreach ($teachersWithoutMaterials as $row)
                    <div class="col-md-6 col-xl-4">
                        <div class="border rounded p-2 h-100">
                            @if ($row['teacher']->teacherProfile)
                                <a href="{{ route('admin.teachers.show', $row['teacher']->teacherProfile) }}" class="fw-semibold">{{ $row['teacher']->name }}</a>
                            @else
                                <span class="fw-semibold">{{ $row['teacher']->name }}</span>
                            @endif
                            <div class="small text-muted text-break">{{ $row['subjects'] ?: '-' }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
