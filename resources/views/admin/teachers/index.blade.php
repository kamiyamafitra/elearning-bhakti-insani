@extends('admin.layouts.dashboard')

@section('title', 'Pengelolaan Guru')

@section('content')
<div class="directory-page">
    <section class="directory-hero">
        <div class="directory-hero__content">
            <div class="directory-hero__heading">
                <h1 class="directory-hero__title">Data Guru</h1>
                <span class="directory-count-badge">
                    <i class="bi bi-person-workspace" aria-hidden="true"></i>
                    {{ $totalTeachers }} Guru
                </span>
            </div>
            <p class="directory-hero__description">
                Kelola informasi guru dan data kepegawaian {{ $school->fullName() }}.
            </p>
        </div>
        <div class="directory-hero__actions">
            <a href="{{ route('admin.teachers.import') }}" class="btn btn-outline-primary directory-hero__action">
                <i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i>
                Impor Guru
            </a>
            <a href="{{ route('admin.teachers.create') }}" class="btn btn-primary directory-hero__action">
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                Tambah Guru
            </a>
        </div>
    </section>

    <section class="directory-stats" aria-label="Ringkasan data guru">
        <div class="directory-stat">
            <span class="directory-stat__icon">
                <i class="bi bi-person-workspace" aria-hidden="true"></i>
            </span>
            <div class="directory-stat__body">
                <span class="directory-stat__label">Total Guru</span>
                <span class="directory-stat__value">{{ $totalTeachers }}</span>
            </div>
        </div>
        <div class="directory-stat">
            <span class="directory-stat__icon">
                <i class="bi bi-person-check-fill" aria-hidden="true"></i>
            </span>
            <div class="directory-stat__body">
                <span class="directory-stat__label">Guru Aktif</span>
                <span class="directory-stat__value">{{ $activeTeachers }}</span>
            </div>
        </div>
        <div class="directory-stat">
            <span class="directory-stat__icon">
                <i class="bi bi-card-checklist" aria-hidden="true"></i>
            </span>
            <div class="directory-stat__body">
                <span class="directory-stat__label">Memiliki NIP</span>
                <span class="directory-stat__value">{{ $teachersWithNip }}</span>
            </div>
        </div>
    </section>

    <form method="GET" action="{{ route('admin.teachers.index') }}" class="directory-toolbar" role="search">
        <div class="directory-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input
                type="search"
                name="q"
                class="form-control"
                placeholder="Cari nama, email, atau NIP..."
                value="{{ $search }}"
                aria-label="Cari guru berdasarkan nama, email, atau NIP"
            >
        </div>

        @if ($perPage !== $defaultPerPage)
            <input type="hidden" name="per_page" value="{{ $perPage }}">
        @endif

        <button class="btn btn-primary directory-toolbar__button" type="submit">
            <i class="bi bi-funnel me-1" aria-hidden="true"></i>
            Terapkan
        </button>

        @if ($search !== '')
            <a href="{{ route('admin.teachers.index') }}" class="btn btn-light directory-toolbar__reset">
                Reset
            </a>
        @endif
    </form>

    <div class="directory-table-card">
        @if ($teachers->isEmpty())
            <div class="directory-empty">
                <div class="directory-empty__icon">
                    <i class="bi bi-person-x" aria-hidden="true"></i>
                </div>
                <h2 class="directory-empty__title">
                    {{ $search !== '' ? 'Guru tidak ditemukan.' : 'Belum ada data guru.' }}
                </h2>
                <p class="directory-empty__description">
                    @if ($search !== '')
                        Coba gunakan kata kunci lain atau periksa kembali ejaan pencarian Anda.
                    @else
                        Mulai dengan menambahkan data guru pertama melalui tombol Tambah Guru.
                    @endif
                </p>
                @if ($search !== '')
                    <a href="{{ route('admin.teachers.index') }}" class="btn btn-outline-primary">
                        Tampilkan Semua
                    </a>
                @else
                    <a href="{{ route('admin.teachers.create') }}" class="btn btn-primary">
                        Tambah Guru
                    </a>
                @endif
            </div>
        @else
            @include('admin._per-page', ['paginator' => $teachers, 'route' => 'admin.teachers.index', 'noun' => 'guru', 'keep' => ['q' => $search]])

            <div class="table-responsive">
                <table class="directory-table">
                    <thead>
                        <tr>
                            <th scope="col">Guru</th>
                            <th scope="col">NIP</th>
                            <th scope="col">No. Telepon</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teachers as $teacher)
                            @php
                                $name = $teacher->user?->name ?? 'Tanpa Nama';
                            @endphp
                            <tr>
                                <td>
                                    <div class="directory-person">
                                        <span class="directory-avatar directory-avatar--teacher" aria-hidden="true">
                                            {{ $teacher->user?->initials ?? '?' }}
                                        </span>
                                        <div>
                                            <div class="directory-person__name">{{ $name }}</div>
                                            <div class="directory-person__meta">{{ $teacher->user?->email ?? '-' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $teacher->nip ?: '-' }}</td>
                                <td>{{ $teacher->phone ?: '-' }}</td>
                                <td>
                                    @if ($teacher->user?->is_active)
                                        <span class="directory-badge directory-badge--active">Aktif</span>
                                    @else
                                        <span class="directory-badge directory-badge--inactive">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="directory-actions">
                                        <a
                                            href="{{ route('admin.teachers.show', $teacher) }}"
                                            class="directory-action directory-action--view"
                                            title="Lihat {{ $name }}"
                                            aria-label="Lihat {{ $name }}"
                                        >
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </a>
                                        <a
                                            href="{{ route('admin.teachers.edit', $teacher) }}"
                                            class="directory-action directory-action--edit"
                                            title="Ubah {{ $name }}"
                                            aria-label="Ubah {{ $name }}"
                                        >
                                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                        </a>
                                        <form
                                            action="{{ route('admin.teachers.destroy', $teacher) }}"
                                            method="POST"
                                            onsubmit="return confirm('Yakin ingin menghapus data guru ini?');"
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

            @if ($teachers->hasPages())
                <div class="directory-pagination p-3">
                    {{ $teachers->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
