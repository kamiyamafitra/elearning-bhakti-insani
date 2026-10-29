@extends('teacher.layouts.dashboard')

@section('title', 'Diskusi Kelas')
@section('page-title', 'Diskusi Kelas')
@section('page-description', 'Diskusi pada kelas dan mata pelajaran yang Anda ampu.')

@section('content')
<div class="card card-dashboard">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Diskusi Kelas</h5>
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
            <i class="bi bi-chat-dots fs-1" aria-hidden="true"></i>
            <p class="mt-2 mb-1">Belum ada diskusi kelas.</p>
            <p class="small mb-0">Diskusi untuk mata pelajaran Anda akan tampil di halaman ini setelah dibuat.</p>
        </div>
    </div>
</div>
@endsection
