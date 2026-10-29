@extends('admin.layouts.dashboard')

@section('title', 'Pengelolaan Kelas')

@section('content')
<div class="directory-page">
    <section class="directory-hero">
        <div class="directory-hero__content">
            <div class="directory-hero__heading">
                <h1 class="directory-hero__title">Data Kelas</h1>
                <span class="directory-count-badge">
                    <i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i>
                    {{ $totalClasses }} Kelas
                </span>
            </div>
            <p class="directory-hero__description">
                Kelola rombongan belajar, tingkat, kode kelas, dan tahun ajaran.
            </p>
        </div>
        <a href="{{ route('admin.classes.create') }}" class="btn btn-primary directory-hero__action">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            Tambah Kelas
        </a>
    </section>

    <section class="directory-stats" aria-label="Ringkasan data kelas">
        <div class="directory-stat">
            <span class="directory-stat__icon">
                <i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i>
            </span>
            <div class="directory-stat__body">
                <span class="directory-stat__label">Total Kelas</span>
                <span class="directory-stat__value">{{ $totalClasses }}</span>
            </div>
        </div>
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
                <span class="directory-stat__label">Kelas Terisi</span>
                <span class="directory-stat__value">{{ $classesWithStudents }}</span>
            </div>
        </div>
    </section>

    <form method="GET" action="{{ route('admin.classes.index') }}" class="directory-toolbar" role="search">
        <div class="directory-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input
                type="search"
                name="q"
                class="form-control"
                placeholder="Cari nama atau kode kelas..."
                value="{{ $search }}"
                aria-label="Cari kelas berdasarkan nama atau kode"
            >
        </div>

        <div class="directory-filter">
            <label for="level" class="directory-filter__label">
                <i class="bi bi-layers" aria-hidden="true"></i>
                Tingkat
            </label>
            <select name="level" id="level" class="form-select">
                <option value="">Semua Tingkat</option>
                @foreach ($levels as $levelOption)
                    <option value="{{ $levelOption }}" @selected($level === $levelOption)>{{ $levelOption }}</option>
                @endforeach
            </select>
        </div>

        <div class="directory-filter">
            <label for="academic_year_id" class="directory-filter__label">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                Tahun Ajaran
            </label>
            <select name="academic_year_id" id="academic_year_id" class="form-select">
                <option value="">Semua Tahun</option>
                @foreach ($academicYears as $academicYear)
                    <option value="{{ $academicYear->id }}" @selected((string) $academicYearId === (string) $academicYear->id)>
                        {{ $academicYear->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <button class="btn btn-primary directory-toolbar__button" type="submit">
            <i class="bi bi-funnel me-1" aria-hidden="true"></i>
            Terapkan
        </button>

        @if ($search !== '' || $level !== '' || $academicYearId)
            <a href="{{ route('admin.classes.index') }}" class="btn btn-light directory-toolbar__reset">
                Reset
            </a>
        @endif
    </form>

    <div class="directory-table-card">
        @if ($classes->isEmpty())
            <div class="directory-empty">
                <div class="directory-empty__icon">
                    <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
                </div>
                <h2 class="directory-empty__title">
                    {{ $search !== '' || $level !== '' || $academicYearId ? 'Kelas tidak ditemukan.' : 'Belum ada data kelas.' }}
                </h2>
                <p class="directory-empty__description">
                    @if ($search !== '' || $level !== '' || $academicYearId)
                        Coba gunakan kata kunci lain atau pilih tingkat dan tahun ajaran yang berbeda.
                    @else
                        Mulai dengan menambahkan kelas pertama melalui tombol Tambah Kelas.
                    @endif
                </p>
                @if ($search !== '' || $level !== '' || $academicYearId)
                    <a href="{{ route('admin.classes.index') }}" class="btn btn-outline-primary">
                        Tampilkan Semua
                    </a>
                @else
                    <a href="{{ route('admin.classes.create') }}" class="btn btn-primary">
                        Tambah Kelas
                    </a>
                @endif
            </div>
        @else
            <div class="table-responsive">
                <table class="directory-table">
                    <thead>
                        <tr>
                            <th scope="col">Kelas</th>
                            <th scope="col">Tingkat</th>
                            <th scope="col">Tahun Ajaran</th>
                            <th scope="col">Jumlah Siswa</th>
                            <th scope="col" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($classes as $class)
                            <tr>
                                <td>
                                    <div class="directory-person">
                                        <span class="directory-avatar directory-avatar--class" aria-hidden="true">
                                            <i class="bi bi-grid-3x3-gap-fill"></i>
                                        </span>
                                        <div>
                                            <div class="directory-person__name">{{ $class->name }}</div>
                                            <div class="directory-person__meta">Kode: {{ $class->code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($class->level)
                                        <span class="directory-badge directory-badge--level">{{ $class->level }}</span>
                                    @else
                                        <span class="directory-badge directory-badge--muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $class->academicYear?->name ?? '-' }}</td>
                                <td>
                                    <span class="directory-badge directory-badge--count">
                                        <i class="bi bi-people-fill" aria-hidden="true"></i>
                                        {{ $class->students_count }} Siswa
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="directory-actions">
                                        <a
                                            href="{{ route('admin.classes.show', $class) }}"
                                            class="directory-action directory-action--view"
                                            title="Lihat {{ $class->name }}"
                                            aria-label="Lihat {{ $class->name }}"
                                        >
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </a>
                                        <a
                                            href="{{ route('admin.classes.edit', $class) }}"
                                            class="directory-action directory-action--edit"
                                            title="Ubah {{ $class->name }}"
                                            aria-label="Ubah {{ $class->name }}"
                                        >
                                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                        </a>
                                        <form
                                            action="{{ route('admin.classes.destroy', $class) }}"
                                            method="POST"
                                            onsubmit="return confirm('Yakin ingin menghapus kelas ini?');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="directory-action directory-action--delete"
                                                title="Hapus {{ $class->name }}"
                                                aria-label="Hapus {{ $class->name }}"
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

            @if ($classes->hasPages())
                <div class="directory-pagination p-3">
                    {{ $classes->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
