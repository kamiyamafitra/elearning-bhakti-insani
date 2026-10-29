@extends('teacher.layouts.dashboard')

@section('title', 'Penilaian')
@section('page-title', 'Penilaian')
@section('page-description', 'Pengumpulan tugas dan penilaian pada mata pelajaran yang Anda ampu.')

@section('content')
<div class="card card-dashboard mb-3">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Kelas &amp; Mata Pelajaran</h5>
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
        <span class="badge text-bg-light">0 Pengumpulan</span>
    </div>
    <div class="card-body">
        <div class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-1" aria-hidden="true"></i>
            <p class="mt-2 mb-1">Belum ada pengumpulan tugas yang perlu dinilai.</p>
            <p class="small mb-0">Pengumpulan siswa dan formulir penilaian akan tampil di halaman ini setelah tugas dipublikasikan.</p>
        </div>
    </div>
</div>
@endsection
