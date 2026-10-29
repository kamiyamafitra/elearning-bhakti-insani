@extends('teacher.layouts.dashboard')

@section('title', 'Pengumuman')
@section('page-title', 'Pengumuman')
@section('page-description', 'Informasi terbaru dari sekolah untuk guru.')

@section('content')
<div class="card card-dashboard">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Pengumuman</h5>
        <span class="badge text-bg-light">{{ $announcements->total() }} Pengumuman</span>
    </div>
    <div class="card-body">
        @forelse ($announcements as $announcement)
            <a href="{{ route('teacher.announcements.show', $announcement) }}" class="d-block text-decoration-none text-reset border-bottom py-3">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div class="min-w-0">
                        <div class="fw-semibold text-break">{{ $announcement->title }}</div>
                        <div class="text-muted small">{{ \Illuminate\Support\Str::limit($announcement->content, 160) }}</div>
                    </div>
                    <div class="text-end flex-shrink-0 text-muted small">
                        {{ $announcement->published_at?->format('d-m-Y') ?? $announcement->created_at?->format('d-m-Y') }}
                    </div>
                </div>
            </a>
        @empty
            <div class="text-center text-muted py-5">
                <i class="bi bi-megaphone fs-1" aria-hidden="true"></i>
                <p class="mt-2 mb-0">Belum ada pengumuman untuk Anda.</p>
            </div>
        @endforelse
    </div>
</div>

<div class="mt-3">
    {{ $announcements->links() }}
</div>
@endsection
