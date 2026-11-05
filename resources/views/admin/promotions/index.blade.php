@extends('admin.layouts.dashboard')

@section('title', 'Kenaikan Kelas')

@section('content')
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Kenaikan Kelas</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">Naikkan semua siswa ke tahun ajaran berikutnya sekaligus.</p>
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

@if ($sourceYear === null)
    <div class="alert alert-info">
        Belum ada tahun ajaran. <a href="{{ route('admin.academic-years.create') }}">Tambah tahun ajaran</a> terlebih dahulu.
    </div>
@else
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.promotions.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label" for="source_academic_year_id">Dari Tahun Ajaran</label>
                    <select id="source_academic_year_id" name="source_academic_year_id" class="form-select" onchange="this.form.submit()">
                        @foreach ($academicYears as $year)
                            <option value="{{ $year->id }}" @selected($sourceYear->id === $year->id)>
                                {{ $year->name }}{{ $year->is_active ? ' (aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-7">
                    <div class="form-label">Ke Tahun Ajaran</div>
                    <div class="fw-semibold">
                        {{ $targetYearName }}
                        @if ($targetYear === null)
                            <span class="badge text-bg-primary">Dibuat otomatis</span>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if ($plans->isEmpty())
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="directory-empty">
                    <div class="directory-empty__icon">
                        <i class="bi bi-person-check" aria-hidden="true"></i>
                    </div>
                    <h3 class="directory-empty__title">Tidak ada siswa untuk diproses.</h3>
                    <p class="directory-empty__description">Semua siswa di tahun ajaran {{ $sourceYear->name }} sudah diproses.</p>
                </div>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('admin.promotions.store') }}" id="promotionForm">
            @csrf
            <input type="hidden" name="source_academic_year_id" value="{{ $sourceYear->id }}">

            @foreach ($plans as $plan)
                @php($class = $plan['class'])
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-4">
                                <div class="fw-semibold">{{ $class->name }}</div>
                                <div class="small text-muted">{{ $plan['students']->count() }} siswa</div>
                            </div>
                            <div class="col-md-8">
                                @if ($plan['isFinal'])
                                    <span class="badge text-bg-secondary">Lulus</span>
                                    <span class="small text-muted">Akun siswa dinonaktifkan.</span>
                                @else
                                    <label class="form-label small mb-1" for="target-{{ $class->id }}">
                                        Naik ke
                                        @if ($plan['targetIsNew'])
                                            <span class="badge text-bg-primary">Dibuat otomatis</span>
                                        @endif
                                    </label>
                                    <input type="text" id="target-{{ $class->id }}" name="targets[{{ $class->id }}]" maxlength="50" class="form-control @error("targets.{$class->id}") is-invalid @enderror" value="{{ old("targets.{$class->id}", $plan['targetName']) }}">
                                @endif
                            </div>
                        </div>

                        <details class="mt-3">
                            <summary class="small">Ubah status siswa tertentu</summary>
                            <div class="table-responsive mt-2">
                                <table class="table table-sm align-middle mb-0">
                                    <tbody>
                                        @foreach ($plan['students'] as $student)
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">{{ $student->user?->name }}</div>
                                                    <div class="small text-muted">{{ $student->nis ?: '-' }}</div>
                                                </td>
                                                <td style="width: 170px;">
                                                    <select name="actions[{{ $student->id }}]" class="form-select form-select-sm" data-default-action="{{ $plan['defaultAction'] }}" aria-label="Status {{ $student->user?->name }}">
                                                        @unless ($plan['isFinal'])
                                                            <option value="naik" @selected(old("actions.{$student->id}", $plan['defaultAction']) === 'naik')>Naik</option>
                                                        @endunless
                                                        <option value="tinggal" @selected(old("actions.{$student->id}", $plan['defaultAction']) === 'tinggal')>Tinggal Kelas</option>
                                                        <option value="lulus" @selected(old("actions.{$student->id}", $plan['defaultAction']) === 'lulus')>Lulus</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    </div>
                </div>
            @endforeach

            <div class="admin-form__actions">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Proses Kenaikan Kelas
                </button>
            </div>
            <p class="small text-muted mt-2 mb-0">
                Kelas, guru pengajar, dan jadwal disalin otomatis ke {{ $targetYearName }}. Semua kelas mulai dari kosong:
                materi, tugas, kuis, dan diskusi lama diarsipkan, dan pengumuman yang sudah tayang diturunkan.
                Nilai lama tetap bisa dilihat admin di Rekap Nilai.
            </p>
        </form>

        <script>
            document.getElementById('promotionForm').addEventListener('submit', (event) => {
                if (!confirm('Proses kenaikan kelas untuk semua siswa? Siswa yang lulus akan dinonaktifkan.')) {
                    event.preventDefault();

                    return;
                }

                // Only exceptions are sent, so a large school stays within PHP's input limit.
                document.querySelectorAll('[data-default-action]').forEach((select) => {
                    select.disabled = select.value === select.dataset.defaultAction;
                });
            });
        </script>
    @endif
@endif
@endsection
