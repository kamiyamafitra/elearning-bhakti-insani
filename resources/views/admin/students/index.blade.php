@extends('admin.layouts.dashboard')

@section('title', 'Pengelolaan Siswa')

@section('content')
<div class="directory-page">
    <section class="directory-hero">
        <div class="directory-hero__content">
            <div class="directory-hero__heading">
                <h1 class="directory-hero__title">Data Siswa</h1>
                <span class="directory-count-badge">
                    <i class="bi bi-people-fill" aria-hidden="true"></i>
                    {{ $totalStudents }} Siswa
                </span>
            </div>
            <p class="directory-hero__description">
                Kelola informasi siswa, data identitas, dan penempatan kelas dalam satu tempat.
            </p>
        </div>
        <div class="directory-hero__actions">
            <a href="{{ route('admin.students.import') }}" class="btn btn-outline-primary directory-hero__action">
                <i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i>
                Impor Siswa
            </a>
            <a href="{{ route('admin.students.create') }}" class="btn btn-primary directory-hero__action">
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                Tambah Siswa
            </a>
        </div>
    </section>

    <section class="directory-stats" aria-label="Ringkasan data siswa">
        <div class="directory-stat">
            <span class="directory-stat__icon">
                <i class="bi bi-people-fill" aria-hidden="true"></i>
            </span>
            <div class="directory-stat__body">
                <span class="directory-stat__label">Total Siswa</span>
                <span class="directory-stat__value">{{ $totalStudents }}</span>
            </div>
        </div>
        <div class="directory-stat">
            <span class="directory-stat__icon">
                <i class="bi bi-person-check-fill" aria-hidden="true"></i>
            </span>
            <div class="directory-stat__body">
                <span class="directory-stat__label">Sudah Dapat Kelas</span>
                <span class="directory-stat__value">{{ $studentsWithClass }}</span>
            </div>
        </div>
        <div class="directory-stat">
            <span class="directory-stat__icon">
                <i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i>
            </span>
            <div class="directory-stat__body">
                <span class="directory-stat__label">Kelas Terisi</span>
                <span class="directory-stat__value">{{ $classesWithStudents }}</span>
            </div>
        </div>
    </section>

    <form method="GET" action="{{ route('admin.students.index') }}" class="directory-toolbar" role="search">
        <div class="directory-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input
                type="search"
                name="q"
                class="form-control"
                placeholder="Cari nama, email, NIS, atau NISN..."
                value="{{ $search }}"
                aria-label="Cari siswa berdasarkan nama, email, NIS, atau NISN"
            >
        </div>

        <div class="directory-filter">
            <label for="class_id" class="directory-filter__label">
                <i class="bi bi-people" aria-hidden="true"></i>
                Kelas
            </label>
            <select name="class_id" id="class_id" class="form-select">
                <option value="">Semua Kelas</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected((string) $classId === (string) $class->id)>
                        {{ $class->name }}@if ($class->academicYear) ({{ $class->academicYear->name }})@endif
                    </option>
                @endforeach
            </select>
        </div>

        <button class="btn btn-primary directory-toolbar__button" type="submit">
            <i class="bi bi-funnel me-1" aria-hidden="true"></i>
            Terapkan
        </button>

        @if ($search !== '' || $classId)
            <a href="{{ route('admin.students.index') }}" class="btn btn-light directory-toolbar__reset">
                Reset
            </a>
        @endif
    </form>

    <div class="directory-table-card">
        @if ($students->isEmpty())
            <div class="directory-empty">
                <div class="directory-empty__icon">
                    <i class="bi bi-person-x" aria-hidden="true"></i>
                </div>
                <h2 class="directory-empty__title">
                    {{ $search !== '' || $classId ? 'Siswa tidak ditemukan.' : 'Belum ada data siswa.' }}
                </h2>
                <p class="directory-empty__description">
                    @if ($search !== '' || $classId)
                        Coba gunakan kata kunci lain atau pilih kelas yang berbeda.
                    @else
                        Mulai dengan menambahkan data siswa pertama melalui tombol Tambah Siswa.
                    @endif
                </p>
                @if ($search !== '' || $classId)
                    <a href="{{ route('admin.students.index') }}" class="btn btn-outline-primary">
                        Tampilkan Semua
                    </a>
                @else
                    <a href="{{ route('admin.students.create') }}" class="btn btn-primary">
                        Tambah Siswa
                    </a>
                @endif
            </div>
        @else
            <div class="table-responsive">
                <table class="directory-table">
                    <thead>
                        <tr>
                            <th scope="col">Siswa</th>
                            <th scope="col">NIS</th>
                            <th scope="col">NISN</th>
                            <th scope="col">Kelas</th>
                            <th scope="col" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            @php
                                $name = $student->user?->name ?? 'Tanpa Nama';
                            @endphp
                            <tr>
                                <td>
                                    <div class="directory-person">
                                        <span class="directory-avatar" aria-hidden="true">
                                            {{ $student->user?->initials ?? '?' }}
                                        </span>
                                        <div>
                                            <div class="directory-person__name">{{ $name }}</div>
                                            <div class="directory-person__meta">{{ $student->user?->email ?? '-' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $student->nis ?: '-' }}</td>
                                <td>{{ $student->nisn ?: '-' }}</td>
                                <td>
                                    @if ($student->class)
                                        <span class="directory-badge">
                                            <i class="bi bi-people-fill" aria-hidden="true"></i>
                                            {{ $student->class->name }}
                                        </span>
                                    @else
                                        <span class="directory-badge directory-badge--muted">Belum ada kelas</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="directory-actions">
                                        <a
                                            href="{{ route('admin.students.show', $student) }}"
                                            class="directory-action directory-action--view"
                                            title="Lihat {{ $name }}"
                                            aria-label="Lihat {{ $name }}"
                                        >
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </a>
                                        <a
                                            href="{{ route('admin.students.edit', $student) }}"
                                            class="directory-action directory-action--edit"
                                            title="Ubah {{ $name }}"
                                            aria-label="Ubah {{ $name }}"
                                        >
                                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                        </a>
                                        <form
                                            action="{{ route('admin.students.destroy', $student) }}"
                                            method="POST"
                                            onsubmit="return confirm('Yakin ingin menghapus data siswa ini?');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="directory-action directory-action--delete"
                                                title="Hapus {{ $name }}"
                                                aria-label="Hapus {{ $name }}"
                                            >
                                                <i class="bi bi-trash3" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($students->hasPages())
                <div class="directory-pagination p-3">
                    {{ $students->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
