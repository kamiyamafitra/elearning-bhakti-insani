@extends('admin.layouts.dashboard')

@section('title', 'Detail Pengumuman')

@section('content')
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Detail Pengumuman</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">Informasi lengkap pengumuman yang dipublikasikan.</p>
    </div>
    <a href="{{ route('admin.announcements.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card card-dashboard h-100">
            <div class="card-body">
                <h2 class="h4 mb-2 text-break">{{ $announcement->title ?: '(Tanpa judul)' }}</h2>
                <div class="text-muted small mb-3">
                    {{ $announcement->creator?->name ?? 'Administrator' }}
                    &middot; {{ $announcement->created_at?->format('d-m-Y H:i') ?? '-' }}
                </div>
                <div style="white-space: pre-line;">{{ $announcement->content }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Informasi Publikasi</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="text-muted small">Visibilitas</div>
                    <div class="fw-semibold">
                        @if ($announcement->visibility === 'role')
                            Berdasarkan Peran ({{ $announcement->target_role === 'teacher' ? 'Guru' : 'Siswa' }})
                        @elseif ($announcement->visibility === 'class')
                            Kelas {{ $announcement->class?->name ?? '-' }}
                        @else
                            Seluruh Sekolah
                        @endif
                    </div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Status</div>
                    <div class="fw-semibold">{{ $announcement->is_published ? 'Dipublikasikan' : 'Draf' }}</div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Tanggal Publikasi</div>
                    <div class="fw-semibold">{{ $announcement->published_at?->format('d-m-Y H:i') ?? '-' }}</div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Dibuat Oleh</div>
                    <div class="fw-semibold">{{ $announcement->creator?->name ?? 'Administrator' }}</div>
                </div>
                <a href="{{ route('admin.announcements.edit', $announcement) }}" class="btn btn-primary w-100">
                    <i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Ubah Pengumuman
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
