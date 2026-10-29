    @extends('admin.layouts.dashboard')
@section('title', 'Dashboard Administrator')

@section('page-title', 'Dashboard Administrator')
@section('page-description', 'Ringkasan data dan aktivitas terkini di lingkungan pembelajaran.')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-2">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(36, 87, 197, 0.12); color: #2457C5;">
                    <i class="bi bi-people fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Pengguna</div>
                    <div class="h4 mb-0">{{ $totalUsers }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-2">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.12); color: #10B981;">
                    <i class="bi bi-person-workspace fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Guru</div>
                    <div class="h4 mb-0">{{ $totalTeachers }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-2">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(168, 85, 247, 0.12); color: #A855F7;">
                    <i class="bi bi-person-badge fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Siswa</div>
                    <div class="h4 mb-0">{{ $totalStudents }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-2">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(249, 115, 22, 0.12); color: #F97316;">
                    <i class="bi bi-grid fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Kelas</div>
                    <div class="h4 mb-0">{{ $totalClasses }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-2">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(14, 165, 233, 0.12); color: #0EA5E9;">
                    <i class="bi bi-journal-bookmark fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Mata Pelajaran</div>
                    <div class="h4 mb-0">{{ $totalSubjects }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-2">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(239, 68, 68, 0.12); color: #EF4444;">
                    <i class="bi bi-file-earmark-text fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Pengumpulan Tertunda</div>
                    <div class="h4 mb-0">0</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Aktivitas Terbaru</h5>
                <span class="text-muted small">Siap untuk diintegrasikan</span>
            </div>
            <div class="card-body">
                <div class="text-center text-muted py-4">
                    <i class="bi bi-activity fs-1"></i>
                    <p class="mt-2 mb-0">Belum ada aktivitas tercatat</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
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
