@extends('admin.layouts.dashboard')

@section('title', 'Rekap Nilai')

@section('content')
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Rekap Nilai</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">Nilai tugas setiap siswa per kelas dan mata pelajaran.</p>
    </div>
    @if ($class)
        <a href="{{ route('admin.grades.export', ['class_id' => $class->id]) }}" class="btn btn-primary">
            <i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i> Unduh Excel
        </a>
    @endif
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.grades.index') }}" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label" for="class_id">Kelas</label>
                <select id="class_id" name="class_id" class="form-select" required onchange="this.form.subject_id && (this.form.subject_id.value = ''); this.form.submit()">
                    <option value="">-- Pilih kelas --</option>
                    @foreach ($classes->groupBy(fn ($classOption) => $classOption->academicYear?->name) as $yearName => $yearClasses)
                        <optgroup label="{{ $yearName }}">
                            @foreach ($yearClasses as $classOption)
                                <option value="{{ $classOption->id }}" @selected($class?->id === $classOption->id)>{{ $classOption->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            @if ($class)
                <div class="col-md-5">
                    <label class="form-label" for="subject_id">Mata Pelajaran</label>
                    <select id="subject_id" name="subject_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Ringkasan semua mata pelajaran</option>
                        @foreach ($subjects as $subjectOption)
                            <option value="{{ $subjectOption['subject']->id }}" @selected($selectedSubject && $selectedSubject['subject']->id === $subjectOption['subject']->id)>
                                {{ $subjectOption['subject']->name }} ({{ $subjectOption['assignments']->count() }} penilaian)
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">Tampilkan</button>
            </div>
        </form>
    </div>
</div>

@if (! $class)
    <div class="directory-empty">
        <div class="directory-empty__icon"><i class="bi bi-graph-up" aria-hidden="true"></i></div>
        <h2 class="directory-empty__title">Pilih kelas untuk melihat rekap nilai.</h2>
        <p class="directory-empty__description">Kelas dari tahun ajaran sebelumnya tetap dapat dibuka, termasuk siswa yang sudah naik kelas atau lulus.</p>
    </div>
@elseif ($students->isEmpty())
    <div class="directory-empty">
        <div class="directory-empty__icon"><i class="bi bi-people" aria-hidden="true"></i></div>
        <h2 class="directory-empty__title">Belum ada siswa di kelas {{ $class->name }}.</h2>
    </div>
@else
    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="admin-form__section-title">
                {{ $selectedSubject ? $selectedSubject['subject']->name.' — ' : 'Ringkasan — ' }}{{ $class->name }} ({{ $class->academicYear?->name }})
            </h2>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-2">
                    <thead>
                        <tr>
                            <th scope="col">No</th>
                            <th scope="col">Siswa</th>
                            @if ($selectedSubject)
                                @foreach ($selectedSubject['assignments'] as $assignment)
                                    <th scope="col" class="text-center small" title="Batas: {{ $assignment->due_at?->format('d-m-Y H:i') ?? '-' }}">
                                        {{ Str::limit($assignment->title, 24) }}
                                        <div class="fw-normal text-muted">maks {{ $assignment->max_score }}</div>
                                    </th>
                                @endforeach
                            @else
                                @foreach ($subjects as $subjectRecap)
                                    <th scope="col" class="text-center small">
                                        <a href="{{ route('admin.grades.index', ['class_id' => $class->id, 'subject_id' => $subjectRecap['subject']->id]) }}" class="text-reset">{{ $subjectRecap['subject']->name }}</a>
                                    </th>
                                @endforeach
                            @endif
                            <th scope="col" class="text-center">Rata-rata</th>
                            <th scope="col" class="text-center small">Tidak Mengumpulkan</th>
                            @if ($selectedSubject)
                                <th scope="col" class="text-center">Nilai Rapor</th>
                                <th scope="col" class="text-center small">Predikat</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            @php($row = $selectedSubject ? $selectedSubject['students'][$student['id']] : $student)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $student['name'] }}</div>
                                    <div class="small text-muted">NIS: {{ $student['nis'] ?: '-' }}</div>
                                </td>
                                @if ($selectedSubject)
                                    @foreach ($selectedSubject['assignments'] as $assignment)
                                        @php($cell = $row['cells'][$assignment->key])
                                        <td class="text-center">
                                            @switch($cell['status'])
                                                @case('graded')
                                                    <span class="fw-semibold">{{ $cell['score'] }}</span>
                                                    @break
                                                @case('ungraded')
                                                    <span class="badge text-bg-warning" title="Sudah mengumpulkan, belum dinilai">Belum dinilai</span>
                                                    @break
                                                @case('missing')
                                                    <span class="badge text-bg-danger" title="Batas waktu lewat tanpa pengumpulan">Tidak ada</span>
                                                    @break
                                                @default
                                                    <span class="text-muted" title="Belum melewati batas waktu">-</span>
                                            @endswitch
                                        </td>
                                    @endforeach
                                @else
                                    @foreach ($subjects as $subjectRecap)
                                        @php($subjectAverage = $subjectRecap['students'][$student['id']]['average'])
                                        <td class="text-center">{{ $subjectAverage !== null ? number_format($subjectAverage, 1, ',', '.') : '-' }}</td>
                                    @endforeach
                                @endif
                                <td class="text-center fw-semibold">{{ $row['average'] !== null ? number_format($row['average'], 1, ',', '.') : '-' }}</td>
                                <td class="text-center">
                                    @if ($row['missing'] > 0)
                                        <span class="badge text-bg-danger">{{ $row['missing'] }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                @if ($selectedSubject)
                                    @include('grades._report-cells', ['report' => $row['report']])
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="small text-muted mb-0">
                Rata-rata dihitung dalam skala 0–100 dari tugas yang sudah dinilai saja. Tugas yang tidak dikumpulkan setelah batas waktu
                dihitung terpisah di kolom Tidak Mengumpulkan dan tidak dianggap bernilai 0.
                @if ($selectedSubject)
                    @php($setting = $selectedSubject['setting'])
                    <br>Nilai Rapor memakai bobot guru (Tugas {{ $setting->assignment_weight }}% · Kuis {{ $setting->quiz_weight }}%, KKM {{ $setting->passing_score }}); di nilai rapor, tugas/kuis yang tidak dikerjakan dihitung 0.
                @endif
                @if ($selectedSubject && $selectedSubject['assignments']->isEmpty())
                    <br>Belum ada tugas yang diterbitkan untuk mata pelajaran ini.
                @endif
            </p>
        </div>
    </div>
@endif
@endsection
