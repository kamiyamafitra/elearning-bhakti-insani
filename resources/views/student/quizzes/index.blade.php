@extends('student.layouts.dashboard')

@section('title', 'Kuis & Ujian')
@section('page-title', 'Kuis & Ujian')
@section('page-description', 'Daftar kuis dan ujian yang tersedia untuk mata pelajaran kelas Anda.')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-4">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(168, 85, 247, 0.12); color: #A855F7;">
                    <i class="bi bi-patch-question fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Kuis Tersedia</div>
                    <div class="h4 mb-0">0</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(36, 87, 197, 0.12); color: #2457C5;">
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
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.12); color: #10B981;">
                    <i class="bi bi-check2-circle fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Sudah Dikerjakan</div>
                    <div class="h4 mb-0">0</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card card-dashboard">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Kuis &amp; Ujian</h5>
        <span class="badge text-bg-light">{{ $subjects->count() }} Mata Pelajaran</span>
    </div>
    <div class="card-body">
        @if ($subjects->isNotEmpty())
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach ($subjects as $subject)
                    <span class="badge text-bg-light">{{ $subject->code }} &middot; {{ $subject->name }}</span>
                @endforeach
            </div>
        @endif

        <div class="text-center text-muted py-5">
            <i class="bi bi-patch-question fs-1" aria-hidden="true"></i>
            <p class="mt-2 mb-1">Belum ada kuis atau ujian yang tersedia.</p>
            <p class="small mb-0">Kuis dan ujian dari guru pengampu akan tampil di halaman ini setelah dipublikasikan.</p>
        </div>
    </div>
</div>
@endsection
