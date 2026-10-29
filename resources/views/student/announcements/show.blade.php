@extends('student.layouts.dashboard')

@section('title', 'Detail Pengumuman')
@section('page-title', 'Detail Pengumuman')
@section('page-description', 'Informasi lengkap pengumuman sekolah.')

@section('content')
<div class="mb-4">
    <a href="{{ route('student.announcements.index') }}" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali ke Pengumuman
    </a>
</div>

<div class="card card-dashboard">
    <div class="card-body">
        <h2 class="h4 mb-2 text-break">{{ $announcement->title }}</h2>
        <div class="text-muted small mb-4">
            {{ $announcement->creator?->name ?? 'Sekolah' }}
            &middot; {{ $announcement->published_at?->format('d-m-Y H:i') ?? $announcement->created_at?->format('d-m-Y H:i') }}
            @if ($announcement->class)
                &middot; Kelas {{ $announcement->class->name }}
            @endif
        </div>
        <div style="white-space: pre-line;">{{ $announcement->content }}</div>
    </div>
</div>
@endsection
