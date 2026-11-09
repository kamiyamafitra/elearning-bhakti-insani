@extends('student.layouts.dashboard')

@section('title', 'Detail Materi')
@section('page-title', $material->title)
@section('page-description', 'Materi pelajaran untuk kelas Anda.')

@section('content')
<div class="mb-4">
    <a href="{{ route('student.subjects.show', ['subject' => $subject, 'tab' => 'materials']) }}#materi" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali ke {{ $subject->name }}
    </a>
</div>

<div class="card card-dashboard">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="min-w-0">
                <h2 class="h4 mb-2 text-break">{{ $material->title }}</h2>
                <div class="text-muted">
                    {{ $material->subject?->name ?? $subject->name }}
                    &middot; {{ $material->class?->name ?? '-' }}
                    &middot; {{ $material->teacher?->name ?? 'Guru' }}
                </div>
            </div>
            <div class="text-end">
                <div class="text-muted small">Dipublikasikan</div>
                <div class="fw-semibold">{{ $material->published_at?->format('d-m-Y H:i') ?? '-' }}</div>
            </div>
        </div>

        <hr>

        <h3 class="h6">Deskripsi Materi</h3>
        <div class="text-break" style="white-space: pre-line;">{{ $material->description }}</div>

        <div class="d-flex flex-wrap align-items-center gap-2 mt-4">
            @if ($material->hasFile())
                @include('files._preview-button', ['url' => route('student.materials.download', [$subject, $material]), 'path' => $material->file_path, 'name' => $material->file_original_name, 'class' => 'btn btn-primary'])
                <a href="{{ route('student.materials.download', [$subject, $material]) }}" class="btn {{ \App\Http\Controllers\Controller::previewTypeFor($material->file_path) ? 'btn-outline-primary' : 'btn-primary' }}">
                    <i class="bi bi-download me-1" aria-hidden="true"></i> Unduh Materi
                </a>
                <span class="text-muted small">{{ $material->file_original_name }} &middot; {{ $material->readableFileSize() }}</span>
            @else
                <span class="text-muted small">Materi ini tidak memiliki berkas unduhan.</span>
            @endif

            @if ($material->hasExternalUrl())
                <a href="{{ $material->external_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary">
                    <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i> Buka Sumber Eksternal
                </a>
            @endif
        </div>
    </div>
</div>
@endsection
