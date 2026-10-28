@extends('student.layouts.dashboard')

@section('title', 'Dashboard Siswa')
@section('page-title', 'Dashboard Siswa')
@section('page-description', 'Selamat belajar, akses materi, tugas, dan progres Anda di sini.')

@section('content')
<div class="alert alert-primary border-0 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <div class="fw-semibold">Halo, {{ auth()->user()->name }}!</div>
        <div class="small">Semangat belajar hari ini. Pantau tugas dan kuis yang perlu diselesaikan.</div>
    </div>
    <a href="#" class="btn btn-light disabled">Lihat Kursus Saya</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(36, 87, 197, 0.12); color: #2457C5;">
                    <i class="bi bi-journal-code fs-4"></i>
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
                <div class="stat-icon" style="background: rgba(249, 115, 22, 0.12); color: #F97316;">
                    <i class="bi bi-file-earmark-text fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Tugas Belum Dikerjakan</div>
                    <div class="h4 mb-0">0</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(168, 85, 247, 0.12); color: #A855F7;">
                    <i class="bi bi-patch-question fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Kuis Mendatang</div>
                    <div class="h4 mb-0">0</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.12); color: #10B981;">
                    <i class="bi bi-award fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Nilai Terbaru</div>
                    <div class="h4 mb-0">-</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Kursus Saya</h5>
            </div>
            <div class="card-body">
                <div class="text-center text-muted py-4">
                    <i class="bi bi-journal-code fs-1"></i>
                    <p class="mt-2 mb-0">Belum ada mata pelajaran yang terdaftar</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Pengumuman Terbaru</h5>
            </div>
            <div class="card-body">
                <div class="text-center text-muted py-4">
                    <i class="bi bi-megaphone fs-1"></i>
                    <p class="mt-2 mb-0">Belum ada pengumuman</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
