@extends('admin.layouts.dashboard')

@section('title', 'Salin Kelas')

@section('content')
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Salin Kelas ke {{ $academicYear->name }}</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">Buat kelas tahun ajaran ini dengan menyalin daftar kelas dari tahun ajaran lain.</p>
    </div>
    <a href="{{ route('admin.academic-years.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        @if ($sourceYears->isEmpty())
            <div class="directory-empty">
                <div class="directory-empty__icon">
                    <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
                </div>
                <h2 class="directory-empty__title">Belum ada kelas yang bisa disalin.</h2>
                <p class="directory-empty__description">Tahun ajaran lain belum memiliki kelas.</p>
                <a href="{{ route('admin.classes.create') }}" class="btn btn-primary">Tambah Kelas</a>
            </div>
        @else
            <form method="GET" action="{{ route('admin.academic-years.copy-classes', $academicYear) }}" class="row g-3 align-items-end mb-4">
                <div class="col-md-6">
                    <label class="form-label" for="source_academic_year_id">Salin dari tahun ajaran</label>
                    <select id="source_academic_year_id" name="source_academic_year_id" class="form-select" onchange="this.form.submit()">
                        @foreach ($sourceYears as $year)
                            <option value="{{ $year->id }}" @selected($sourceYear?->id === $year->id)>{{ $year->name }} ({{ $year->classes_count }} kelas)</option>
                        @endforeach
                    </select>
                </div>
                <noscript>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary">Tampilkan</button>
                    </div>
                </noscript>
            </form>

            @if ($sourceYear)
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Kelas</th>
                                <th scope="col">Tingkat</th>
                                <th scope="col">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sourceClasses as $class)
                                <tr>
                                    <td>{{ $class->name }}</td>
                                    <td>{{ $class->level ?: '-' }}</td>
                                    <td>
                                        @if ($class->already_exists)
                                            <span class="badge text-bg-light">Sudah ada, dilewati</span>
                                        @else
                                            <span class="badge text-bg-success">Akan disalin</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="small" style="color: var(--sb-text-secondary);">
                    Nama dan tingkat kelas disalin apa adanya, kode kelas diberi akhiran tahun ajaran (misalnya X-TKJ-1-2627).
                    Siswa, jadwal, dan guru pengampu tidak ikut disalin. Siswa dipindahkan melalui menu Kenaikan Kelas.
                </p>

                <form method="POST" action="{{ route('admin.academic-years.copy-classes.store', $academicYear) }}">
                    @csrf
                    <input type="hidden" name="source_academic_year_id" value="{{ $sourceYear->id }}">
                    <div class="admin-form__actions">
                        <button type="submit" class="btn btn-primary" @disabled($sourceClasses->every(fn ($class) => $class->already_exists))>
                            <i class="bi bi-copy me-1" aria-hidden="true"></i> Salin {{ $sourceClasses->reject(fn ($class) => $class->already_exists)->count() }} Kelas
                        </button>
                        <a href="{{ route('admin.academic-years.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            @endif
        @endif
    </div>
</div>
@endsection
