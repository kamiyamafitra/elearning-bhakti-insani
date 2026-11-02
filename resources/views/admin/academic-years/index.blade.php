@extends('admin.layouts.dashboard')

@section('title', 'Pengelolaan Tahun Ajaran')

@section('content')
<div class="directory-page">
    <section class="directory-hero">
        <div class="directory-hero__content">
            <div class="directory-hero__heading">
                <h1 class="directory-hero__title">Data Tahun Ajaran</h1>
                <span class="directory-count-badge">
                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                    {{ $totalAcademicYears }} Tahun Ajaran
                </span>
            </div>
            <p class="directory-hero__description">
                Kelola periode tahun ajaran dan tentukan tahun ajaran yang sedang berjalan.
            </p>
        </div>
        <a href="{{ route('admin.academic-years.create') }}" class="btn btn-primary directory-hero__action">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            Tambah Tahun Ajaran
        </a>
    </section>

    <section class="directory-stats" aria-label="Ringkasan data tahun ajaran">
        <div class="directory-stat">
            <span class="directory-stat__icon">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
            </span>
            <div class="directory-stat__body">
                <span class="directory-stat__label">Total Tahun Ajaran</span>
                <span class="directory-stat__value">{{ $totalAcademicYears }}</span>
            </div>
        </div>
        <div class="directory-stat">
            <span class="directory-stat__icon">
                <i class="bi bi-calendar-check-fill" aria-hidden="true"></i>
            </span>
            <div class="directory-stat__body">
                <span class="directory-stat__label">Tahun Ajaran Aktif</span>
                <span class="directory-stat__value">{{ $activeAcademicYear?->name ?? 'Belum ada' }}</span>
            </div>
        </div>
        <div class="directory-stat">
            <span class="directory-stat__icon">
                <i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i>
            </span>
            <div class="directory-stat__body">
                <span class="directory-stat__label">Total Kelas</span>
                <span class="directory-stat__value">{{ $totalClasses }}</span>
            </div>
        </div>
    </section>

    @unless ($activeAcademicYear)
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
            Belum ada tahun ajaran aktif. Aktifkan salah satu tahun ajaran agar data kelas dan pembelajaran berjalan sesuai periode.
        </div>
    @endunless

    <form method="GET" action="{{ route('admin.academic-years.index') }}" class="directory-toolbar" role="search">
        <div class="directory-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input
                type="search"
                name="q"
                class="form-control"
                placeholder="Cari tahun ajaran..."
                value="{{ $search }}"
                aria-label="Cari tahun ajaran"
            >
        </div>

        <button class="btn btn-primary directory-toolbar__button" type="submit">
            <i class="bi bi-search me-1" aria-hidden="true"></i>
            Cari
        </button>

        @if ($search !== '')
            <a href="{{ route('admin.academic-years.index') }}" class="btn btn-light directory-toolbar__reset">
                Reset
            </a>
        @endif
    </form>

    <div class="directory-table-card">
        @if ($academicYears->isEmpty())
            <div class="directory-empty">
                <div class="directory-empty__icon">
                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                </div>
                <h2 class="directory-empty__title">
                    {{ $search !== '' ? 'Tahun ajaran tidak ditemukan.' : 'Belum ada data tahun ajaran.' }}
                </h2>
                <p class="directory-empty__description">
                    @if ($search !== '')
                        Coba gunakan kata kunci lain.
                    @else
                        Mulai dengan menambahkan tahun ajaran pertama melalui tombol Tambah Tahun Ajaran.
                    @endif
                </p>
                @if ($search !== '')
                    <a href="{{ route('admin.academic-years.index') }}" class="btn btn-outline-primary">
                        Tampilkan Semua
                    </a>
                @else
                    <a href="{{ route('admin.academic-years.create') }}" class="btn btn-primary">
                        Tambah Tahun Ajaran
                    </a>
                @endif
            </div>
        @else
            <div class="table-responsive">
                <table class="directory-table">
                    <thead>
                        <tr>
                            <th scope="col">Tahun Ajaran</th>
                            <th scope="col">Periode</th>
                            <th scope="col">Status</th>
                            <th scope="col">Jumlah Kelas</th>
                            <th scope="col" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($academicYears as $academicYear)
                            <tr>
                                <td>
                                    <div class="directory-person">
                                        <span class="directory-avatar directory-avatar--class" aria-hidden="true">
                                            <i class="bi bi-calendar3"></i>
                                        </span>
                                        <div>
                                            <div class="directory-person__name">{{ $academicYear->name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    {{ $academicYear->start_date->translatedFormat('d M Y') }}
                                    &ndash;
                                    {{ $academicYear->end_date->translatedFormat('d M Y') }}
                                </td>
                                <td>
                                    @if ($academicYear->is_active)
                                        <span class="directory-badge directory-badge--active">Aktif</span>
                                    @else
                                        <span class="directory-badge directory-badge--inactive">Nonaktif</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="directory-badge directory-badge--count">
                                        <i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i>
                                        {{ $academicYear->classes_count }} Kelas
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="directory-actions">
                                        @unless ($academicYear->is_active)
                                            <form
                                                action="{{ route('admin.academic-years.activate', $academicYear) }}"
                                                method="POST"
                                                onsubmit="return confirm('Jadikan {{ $academicYear->name }} sebagai tahun ajaran aktif?');"
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    type="submit"
                                                    class="directory-action directory-action--view"
                                                    title="Aktifkan {{ $academicYear->name }}"
                                                    aria-label="Aktifkan {{ $academicYear->name }}"
                                                >
                                                    <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @endunless
                                        <a
                                            href="{{ route('admin.academic-years.copy-classes', $academicYear) }}"
                                            class="directory-action directory-action--view"
                                            title="Salin kelas ke {{ $academicYear->name }}"
                                            aria-label="Salin kelas ke {{ $academicYear->name }}"
                                        >
                                            <i class="bi bi-copy" aria-hidden="true"></i>
                                        </a>
                                        <a
                                            href="{{ route('admin.academic-years.edit', $academicYear) }}"
                                            class="directory-action directory-action--edit"
                                            title="Ubah {{ $academicYear->name }}"
                                            aria-label="Ubah {{ $academicYear->name }}"
                                        >
                                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                        </a>
                                        <form
                                            action="{{ route('admin.academic-years.destroy', $academicYear) }}"
                                            method="POST"
                                            onsubmit="return confirm('Yakin ingin menghapus tahun ajaran ini?');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="directory-action directory-action--delete"
                                                title="Hapus {{ $academicYear->name }}"
                                                aria-label="Hapus {{ $academicYear->name }}"
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

            @if ($academicYears->hasPages())
                <div class="directory-pagination p-3">
                    {{ $academicYears->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
