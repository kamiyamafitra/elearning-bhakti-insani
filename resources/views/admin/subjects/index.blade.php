@extends('admin.layouts.dashboard')

@section('title', 'Pengelolaan Mata Pelajaran')

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
                <h1 class="subjects-hero__title">Mata Pelajaran</h1>
                <span class="subjects-count-badge">
                    <i class="bi bi-journal-bookmark" aria-hidden="true"></i>
                    {{ $totalSubjects }} Mata Pelajaran
                </span>
            </div>
            <p class="subjects-hero__description">
                Kelola informasi mata pelajaran, jadwal kelas, kode mata pelajaran, dan deskripsi pembelajaran.
            </p>
        </div>
        <a href="{{ route('admin.subjects.create') }}" class="btn btn-primary subjects-hero__action">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            Tambah Mata Pelajaran
        </a>
    </section>

    @include('subjects.partials.toolbar', [
        'action' => route('admin.subjects.index'),
        'search' => $search,
        'searchPlaceholder' => 'Cari nama atau kode mata pelajaran...',
        'sort' => $sort,
        'sortOptions' => [
            'name_asc' => 'Nama A–Z',
            'name_desc' => 'Nama Z–A',
            'class_asc' => 'Kelas A–Z',
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
        @include('subjects.partials.empty', [
            'title' => 'Mata pelajaran tidak ditemukan.',
            'description' => $search || $classId
                ? 'Coba gunakan kata kunci lain, pilih kelas yang berbeda, atau periksa kembali ejaan pencarian Anda.'
                : 'Belum ada mata pelajaran yang ditambahkan. Mulai dengan menambahkan mata pelajaran pertama.',
            'actionUrl' => $search || $classId
                ? route('admin.subjects.index')
                : route('admin.subjects.create'),
            'actionLabel' => $search || $classId ? 'Tampilkan Semua' : 'Tambah Mata Pelajaran',
            'actionClass' => $search || $classId ? 'btn btn-outline-primary' : 'btn btn-primary',
        ])
    @else
        <div class="row g-4">
            @foreach ($subjects as $subject)
                @php
                    $displaySchedules = $classId
                        ? $subject->schedules->where('class_id', $classId)
                        : $subject->schedules;

                    $displayAssignments = $classId
                        ? $subject->teachingAssignments->where('class_id', $classId)
                        : $subject->teachingAssignments;
                @endphp

                <div class="col-12 col-md-6 col-xl-4">
                    @component('subjects.partials.card', [
                        'subject' => $subject,
                        'schedules' => $displaySchedules,
                        'assignments' => $displayAssignments,
                        'assignmentMode' => 'teacher',
                        'assignmentsLabel' => 'Guru Pengajar',
                        'actionUrl' => route('admin.subjects.show', $subject),
                        'actionLabel' => 'Lihat',
                        'actionIcon' => 'bi-eye',
                        'actionTitle' => 'Lihat detail '.$subject->name,
                    ])
                        @slot('actions')
                            <a
                                href="{{ route('admin.subjects.edit', $subject) }}"
                                class="subject-card__action subject-card__action--edit"
                                title="Ubah {{ $subject->name }}"
                            >
                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                Ubah
                            </a>
                            <form
                                action="{{ route('admin.subjects.destroy', $subject) }}"
                                method="POST"
                                class="subject-card__delete-form"
                                onsubmit="return confirm('Yakin ingin menghapus mata pelajaran ini?');"
                            >
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="subject-card__action subject-card__action--delete"
                                    title="Hapus {{ $subject->name }}"
                                >
                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                    Hapus
                                </button>
                            </form>
                        @endslot
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
