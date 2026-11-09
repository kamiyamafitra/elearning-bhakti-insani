@extends('teacher.layouts.dashboard')

@section('title', 'Penilaian')
@section('page-title', 'Penilaian')
@section('page-description', 'Nilai rapor per kelas dan pengumpulan tugas yang masih perlu dinilai.')

@section('content')
<div class="card card-dashboard mb-3">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Nilai Rapor per Kelas</h5>
        <span class="badge text-bg-light">{{ $assignments->count() }} Penugasan</span>
    </div>
    <div class="card-body">
        @if ($assignments->isEmpty())
            <div class="text-center text-muted py-4">
                <i class="bi bi-easel fs-1" aria-hidden="true"></i>
                <p class="mt-2 mb-0">Belum ada kelas atau mata pelajaran yang ditugaskan kepada Anda.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mata Pelajaran</th>
                            <th>Kelas</th>
                            <th>Tahun Ajaran</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assignments as $assignment)
                            <tr>
                                <td>
                                    <div class="fw-semibold text-break">{{ $assignment->subject?->name ?? '-' }}</div>
                                    <div class="text-muted small">{{ $assignment->subject?->code ?? '-' }}</div>
                                </td>
                                <td>{{ $assignment->class?->name ?? '-' }}</td>
                                <td>{{ $assignment->academicYear?->name ?? '-' }}</td>
                                <td class="text-end">
                                    @if ($assignment->subject && $assignment->class)
                                        <a href="{{ route('teacher.grades.show', [$assignment->subject, $assignment->class]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-journal-check me-1" aria-hidden="true"></i>Nilai Rapor
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card card-dashboard">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Pengumpulan yang Perlu Dinilai</h5>
        <span class="badge {{ $ungradedCount > 0 ? 'text-bg-warning' : 'text-bg-light' }}">{{ $ungradedCount }} Pengumpulan</span>
    </div>
    <div class="card-body">
        @if ($ungradedSubmissions->isEmpty())
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1" aria-hidden="true"></i>
                <p class="mt-2 mb-1">Belum ada pengumpulan tugas yang perlu dinilai.</p>
                <p class="small mb-0">Pengumpulan siswa yang belum dinilai akan tampil di sini, yang paling lama menunggu di atas.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Siswa</th>
                            <th>Tugas</th>
                            <th>Dikumpulkan</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ungradedSubmissions as $submission)
                            <tr>
                                <td class="fw-semibold">{{ $submission->student?->name ?? '-' }}</td>
                                <td>
                                    <div class="text-break">{{ $submission->assignment?->title ?? '-' }}</div>
                                    <div class="text-muted small">{{ $submission->assignment?->subject?->name }} · {{ $submission->assignment?->class?->name }}</div>
                                </td>
                                <td class="small">{{ $submission->submitted_at?->diffForHumans() ?? '-' }}</td>
                                <td class="text-end">
                                    @if ($submission->assignment)
                                        <a href="{{ route('teacher.assignments.show', $submission->assignment) }}" class="btn btn-sm btn-primary">Nilai</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($ungradedCount > $ungradedSubmissions->count())
                <p class="small text-muted mt-2 mb-0">Menampilkan {{ $ungradedSubmissions->count() }} dari {{ $ungradedCount }} pengumpulan yang paling lama menunggu.</p>
            @endif
        @endif
    </div>
</div>
@endsection
