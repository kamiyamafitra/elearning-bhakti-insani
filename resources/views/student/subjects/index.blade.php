@extends('student.layouts.dashboard')

@section('title', 'Mata Pelajaran Saya')

@section('content')
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
                Daftar mata pelajaran yang tersedia untuk kelas Anda beserta guru pengajar dan jadwalnya.
            </p>
        </div>

        @if ($classroom !== null)
            <div class="subjects-hero__meta">
                <i class="bi bi-people" aria-hidden="true"></i>
                <span>{{ $classroom->name }}</span>
                @if ($classroom->academicYear)
                    <span class="subjects-hero__meta-divider" aria-hidden="true">&bull;</span>
                    <span class="subjects-hero__meta-muted">{{ $classroom->academicYear->name }}</span>
                @endif
            </div>
        @endif
    </section>

    @if ($classroom !== null)
        @include('subjects.partials.toolbar', [
            'action' => route('student.subjects.index'),
            'search' => $search,
            'searchPlaceholder' => 'Cari nama atau kode mata pelajaran...',
            'sort' => $sort,
            'sortOptions' => [
                'name_asc' => 'Nama A–Z',
                'name_desc' => 'Nama Z–A',
                'schedule_asc' => 'Hari & Jam',
            ],
        ])
    @endif

    @if ($subjects->isEmpty())
        @if ($classroom === null)
            @include('subjects.partials.empty', [
                'title' => 'Belum ada mata pelajaran',
                'description' => 'Kelas Anda belum ditetapkan oleh administrator, sehingga daftar mata pelajaran belum dapat ditampilkan.',
                'icon' => 'bi-person-x',
            ])
        @elseif ($search)
            @include('subjects.partials.empty', [
                'title' => 'Mata pelajaran tidak ditemukan.',
                'description' => 'Coba gunakan kata kunci lain atau periksa kembali ejaan pencarian Anda.',
                'icon' => 'bi-search',
                'actionUrl' => route('student.subjects.index'),
                'actionLabel' => 'Tampilkan Semua',
            ])
        @else
            @include('subjects.partials.empty', [
                'title' => 'Belum ada mata pelajaran',
                'description' => 'Belum ada mata pelajaran yang terdaftar untuk kelas Anda.',
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
                        'classroom' => $classroom,
                        'assignmentMode' => 'teacher',
                        'assignmentsLabel' => 'Guru Pengajar',
                        'emptyDescription' => 'Belum ada deskripsi mata pelajaran.',
                        'actionUrl' => route('student.subjects.show', $subject),
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
