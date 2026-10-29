@extends('teacher.layouts.dashboard')

@section('title', 'Mata Pelajaran Saya')

@section('content')
@php
    $classOptions = $classes->mapWithKeys(fn ($class) => [
        $class->id => $class->name.($class->academicYear ? ' ('.$class->academicYear->name.')' : ''),
    ])->all();
@endphp

<div class="subjects-page">
    <section class="subjects-hero">
        <div class="subjects-hero__content">
            <div class="subjects-hero__heading">
                <h1 class="subjects-hero__title">Mata Pelajaran Saya</h1>
                <span class="subjects-count-badge">
                    <i class="bi bi-journal-bookmark" aria-hidden="true"></i>
                    {{ $totalSubjects }} Mata Pelajaran
                </span>
            </div>
            <p class="subjects-hero__description">
                Mata pelajaran dan kelas yang ditugaskan kepada Anda beserta jadwal pembelajarannya.
            </p>
        </div>

        <div class="subjects-hero__meta">
            <i class="bi bi-person-badge" aria-hidden="true"></i>
            <span>{{ $teacher?->nip ? 'NIP '.$teacher->nip : auth()->user()->name }}</span>
        </div>
    </section>

    @include('subjects.partials.toolbar', [
        'action' => route('teacher.subjects.index'),
        'search' => $search,
        'searchPlaceholder' => 'Cari nama atau kode mata pelajaran...',
        'sort' => $sort,
        'sortOptions' => [
            'name_asc' => 'Nama A–Z',
            'name_desc' => 'Nama Z–A',
            'schedule_asc' => 'Hari & Jam',
        ],
        'filterName' => 'class_id',
        'filterLabel' => 'Kelas',
        'filterIcon' => 'bi-people',
        'filterValue' => $classId,
        'filterPlaceholder' => 'Semua Kelas',
        'filterOptions' => $classOptions,
    ])

    @if ($subjects->isEmpty())
        @if ($search || $classId)
            @include('subjects.partials.empty', [
                'title' => 'Mata pelajaran tidak ditemukan.',
                'description' => 'Coba gunakan kata kunci lain, pilih kelas yang berbeda, atau periksa kembali ejaan pencarian Anda.',
                'icon' => 'bi-search',
                'actionUrl' => route('teacher.subjects.index'),
                'actionLabel' => 'Tampilkan Semua',
            ])
        @else
            @include('subjects.partials.empty', [
                'title' => 'Belum ada mata pelajaran',
                'description' => 'Belum ada mata pelajaran yang ditugaskan kepada Anda.',
            ])
        @endif
    @else
        <div class="row g-4">
            @foreach ($subjects as $subject)
                <div class="col-12 col-md-6 col-xl-4">
                    @component('subjects.partials.card', [
                        'subject' => $subject,
                        'schedules' => $subject->schedules,
                        'assignments' => $subject->teachingAssignments,
                        'assignmentMode' => 'class',
                        'assignmentsLabel' => 'Kelas yang Diajar',
                        'emptyDescription' => 'Belum ada deskripsi mata pelajaran.',
                        'actionUrl' => route('teacher.subjects.show', $subject),
                        'actionLabel' => 'Buka Mata Pelajaran',
                        'actionIcon' => 'bi-arrow-right-circle',
                        'actionTitle' => 'Buka '.$subject->name,
                        'singleAction' => true,
                    ])
                    @endcomponent
                </div>
            @endforeach
        </div>

        @if ($subjects->hasPages())
            <div class="subjects-pagination">
                {{ $subjects->links('pagination::bootstrap-5') }}
            </div>
        @endif
    @endif
</div>
@endsection
