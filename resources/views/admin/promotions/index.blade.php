@extends('admin.layouts.dashboard')

@section('title', 'Kenaikan Kelas')

@section('content')
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Kenaikan Kelas</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">Pindahkan siswa ke kelas di tahun ajaran baru: naik kelas, tinggal kelas, atau lulus.</p>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <h2 class="admin-form__section-title">1. Pilih kelas asal dan tahun ajaran tujuan</h2>
        <form method="GET" action="{{ route('admin.promotions.index') }}" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label" for="source_class_id">Kelas Asal</label>
                <select id="source_class_id" name="source_class_id" class="form-select" required>
                    <option value="">-- Pilih kelas --</option>
                    @foreach ($sourceClasses->groupBy(fn ($class) => $class->academicYear?->name) as $yearName => $classes)
                        <optgroup label="{{ $yearName }}">
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}" @selected($sourceClass?->id === $class->id)>
                                    {{ $class->name }} ({{ $class->students_count }} siswa)
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="target_academic_year_id">Tahun Ajaran Tujuan</label>
                <select id="target_academic_year_id" name="target_academic_year_id" class="form-select" required>
                    <option value="">-- Pilih tahun ajaran --</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}" @selected($targetYear?->id === $year->id)>
                            {{ $year->name }}{{ $year->is_active ? ' (aktif)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-people me-1" aria-hidden="true"></i> Tampilkan Siswa
                </button>
            </div>
        </form>

        @if ($academicYears->count() < 2)
            <div class="alert alert-info small mt-3 mb-0">
                Kenaikan kelas membutuhkan tahun ajaran baru.
                <a href="{{ route('admin.academic-years.create') }}">Tambah tahun ajaran</a> terlebih dahulu.
            </div>
        @endif

        @if ($isSameYear)
            <div class="alert alert-warning small mt-3 mb-0">
                Tahun ajaran tujuan harus berbeda dari tahun ajaran kelas asal ({{ $sourceClass->academicYear?->name }}).
            </div>
        @endif
    </div>
</div>

@isset($students)
    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="admin-form__section-title">
                2. Proses siswa {{ $sourceClass->name }} ({{ $sourceClass->academicYear?->name }}) ke {{ $targetYear->name }}
            </h2>

            @if ($students->isEmpty())
                <div class="directory-empty">
                    <div class="directory-empty__icon">
                        <i class="bi bi-person-check" aria-hidden="true"></i>
                    </div>
                    <h3 class="directory-empty__title">Tidak ada siswa di kelas ini.</h3>
                    <p class="directory-empty__description">Semua siswa mungkin sudah diproses. Pilih kelas lain untuk melanjutkan.</p>
                </div>
            @else
                @if ($targetClasses->isEmpty())
                    <div class="alert alert-warning small">
                        Belum ada kelas di tahun ajaran {{ $targetYear->name }}, jadi siswa hanya bisa diluluskan.
                        <a href="{{ route('admin.academic-years.copy-classes', $targetYear) }}">Salin kelas dari tahun sebelumnya</a>
                        atau <a href="{{ route('admin.classes.create') }}">tambah kelas</a> terlebih dahulu.
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.promotions.store') }}" class="admin-form" onsubmit="return confirm('Proses kenaikan kelas untuk siswa yang dipilih? Siswa yang lulus akan dinonaktifkan.');">
                    @csrf
                    <input type="hidden" name="source_class_id" value="{{ $sourceClass->id }}">
                    <input type="hidden" name="target_academic_year_id" value="{{ $targetYear->id }}">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="promote_class_id">Kelas tujuan untuk siswa yang <strong>naik kelas</strong></label>
                            <select id="promote_class_id" name="promote_class_id" class="form-select @error('promote_class_id') is-invalid @enderror">
                                <option value="">-- Pilih kelas --</option>
                                @foreach ($targetClasses as $class)
                                    <option value="{{ $class->id }}" @selected((string) old('promote_class_id', $suggestedPromoteClassId) === (string) $class->id)>{{ $class->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="repeat_class_id">Kelas tujuan untuk siswa yang <strong>tinggal kelas</strong></label>
                            <select id="repeat_class_id" name="repeat_class_id" class="form-select @error('repeat_class_id') is-invalid @enderror">
                                <option value="">-- Pilih kelas --</option>
                                @foreach ($targetClasses as $class)
                                    <option value="{{ $class->id }}" @selected((string) old('repeat_class_id', $suggestedRepeatClassId) === (string) $class->id)>{{ $class->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="small" style="color: var(--sb-text-secondary);">Tandai semua:</span>
                        <button type="button" class="btn btn-sm btn-outline-success" data-promotion-set-all="naik">Naik</button>
                        <button type="button" class="btn btn-sm btn-outline-warning" data-promotion-set-all="tinggal">Tinggal Kelas</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-promotion-set-all="lulus">Lulus</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th scope="col">Siswa</th>
                                    <th scope="col">NIS</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($students as $student)
                                    @php($selectedAction = old("actions.{$student->id}", $defaultAction))
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $student->user?->name }}</div>
                                            <div class="small text-muted">{{ $student->user?->email }}</div>
                                        </td>
                                        <td>{{ $student->nis ?: '-' }}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group" aria-label="Status {{ $student->user?->name }}">
                                                @foreach (['naik' => ['Naik', 'success'], 'tinggal' => ['Tinggal', 'warning'], 'lulus' => ['Lulus', 'secondary']] as $action => [$actionLabel, $actionColor])
                                                    <input type="radio" class="btn-check" name="actions[{{ $student->id }}]" id="action-{{ $student->id }}-{{ $action }}" value="{{ $action }}" autocomplete="off" @checked($selectedAction === $action)>
                                                    <label class="btn btn-outline-{{ $actionColor }}" for="action-{{ $student->id }}-{{ $action }}">{{ $actionLabel }}</label>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($isFinalLevel)
                        <p class="small text-muted">Kelas ini tingkat akhir, jadi semua siswa ditandai Lulus secara bawaan.</p>
                    @endif

                    <div class="admin-form__actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Proses Kenaikan Kelas
                        </button>
                    </div>
                </form>

                <script>
                    document.querySelectorAll('[data-promotion-set-all]').forEach((button) => {
                        button.addEventListener('click', () => {
                            document.querySelectorAll(`input[name^="actions["][value="${button.dataset.promotionSetAll}"]`)
                                .forEach((radio) => { radio.checked = true; });
                        });
                    });
                </script>
            @endif
        </div>
    </div>
@endisset
@endsection
