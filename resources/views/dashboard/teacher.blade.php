@extends('teacher.layouts.dashboard')

@section('title', 'Dashboard Guru')
@section('page-title', 'Dashboard Guru')
@section('page-description', 'Pantau kelas, tugas, dan progres pembelajaran Anda.')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(36, 87, 197, 0.12); color: #2457C5;">
                    <i class="bi bi-easel fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Kelas Diajar</div>
                    <div class="h4 mb-0">0</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.12); color: #10B981;">
                    <i class="bi bi-journal-bookmark fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Mata Pelajaran</div>
                    <div class="h4 mb-0">0</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(168, 85, 247, 0.12); color: #A855F7;">
                    <i class="bi bi-file-earmark-text fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Tugas Aktif</div>
                    <div class="h4 mb-0">0</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(249, 115, 22, 0.12); color: #F97316;">
                    <i class="bi bi-inbox fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Perlu Dinilai</div>
                    <div class="h4 mb-0">0</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Pengumpulan Terbaru</h5>
            </div>
            <div class="card-body">
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2 mb-0">Belum ada pengumpulan tugas</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Batas Waktu Mendatang</h5>
            </div>
            <div class="card-body">
                <div class="text-center text-muted py-4">
                    <i class="bi bi-calendar-event fs-1"></i>
                    <p class="mt-2 mb-0">Tidak ada tenggat waktu</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
