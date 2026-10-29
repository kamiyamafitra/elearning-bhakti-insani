@extends('teacher.layouts.dashboard')

@section('title', 'Detail Tugas')
@section('page-title', $assignment->title)
@section('page-description', 'Pantau pengumpulan siswa, unduh berkas, dan beri nilai.')

@section('content')
<div class="mb-4 d-flex flex-wrap gap-2">
    <a href="{{ route('teacher.subjects.show', ['subject' => $assignment->subject, 'tab' => 'assignments']) }}#tugas" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali ke {{ $assignment->subject?->name ?? 'Mata Pelajaran' }}
    </a>
    <a href="{{ route('teacher.assignments.edit', $assignment) }}" class="btn btn-outline-primary">
        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Edit Tugas
    </a>
</div>

<div class="card card-dashboard">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <h2 class="h4 mb-0 text-break">{{ $assignment->title }}</h2>
                    <span class="badge {{ $assignment->isPublished() ? 'text-bg-success' : 'text-bg-secondary' }}">
                        {{ $assignment->isPublished() ? 'Dipublikasikan' : 'Draf' }}
                    </span>
                </div>
                <div class="text-muted">
                    {{ $assignment->subject?->name ?? '-' }}
                    &middot; {{ $assignment->class?->name ?? '-' }}
                    &middot; Dibuat {{ $assignment->created_at?->format('d-m-Y H:i') ?? '-' }}
                </div>
            </div>
            <div class="text-end">
                <div class="text-muted small">Deadline</div>
                <div class="fw-semibold {{ $assignment->isPastDue() ? 'text-danger' : '' }}">
                    {{ $assignment->due_at?->format('d-m-Y H:i') ?? '-' }}
                </div>
                <div class="text-muted small">Skor maksimal {{ $assignment->max_score }}</div>
            </div>
        </div>

        <hr>

        <h3 class="h6">Instruksi Tugas</h3>
        <p class="text-break" style="white-space: pre-line;">{{ $assignment->instructions }}</p>

        <div class="d-flex flex-wrap align-items-center gap-2">
            @if ($assignment->hasAttachment())
                <a href="{{ route('teacher.assignments.download', $assignment) }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-download me-1" aria-hidden="true"></i> {{ $assignment->attachment_original_name }}
                </a>
                <span class="text-muted small">{{ $assignment->readableAttachmentSize() }}</span>
            @else
                <span class="text-muted small">Tidak ada lampiran tugas.</span>
            @endif
            <span class="badge {{ $assignment->allows_text_response ? 'text-bg-light' : 'text-bg-secondary' }}">
                {{ $assignment->allows_text_response ? 'Jawaban teks diizinkan' : 'Jawaban teks tidak diizinkan' }}
            </span>
            <span class="badge {{ $assignment->allows_resubmission ? 'text-bg-light' : 'text-bg-secondary' }}">
                {{ $assignment->allows_resubmission ? 'Pengumpulan ulang diizinkan' : 'Tanpa pengumpulan ulang' }}
            </span>
        </div>

        <div class="mt-3 pt-3 border-top">
            <form method="POST" action="{{ route('teacher.assignments.destroy', $assignment) }}"
                  onsubmit="return confirm('Hapus tugas ini beserta seluruh pengumpulan siswa?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash me-1" aria-hidden="true"></i> Hapus Tugas
                </button>
            </form>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-6 col-lg">
        <div class="card card-dashboard h-100">
            <div class="card-body">
                <div class="text-muted small">Terkumpul</div>
                <div class="h4 mb-0">{{ $stats['submitted'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card card-dashboard h-100">
            <div class="card-body">
                <div class="text-muted small">Belum Dikumpulkan</div>
                <div class="h4 mb-0">{{ $stats['not_submitted'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card card-dashboard h-100">
            <div class="card-body">
                <div class="text-muted small">Terlambat</div>
                <div class="h4 mb-0">{{ $stats['late'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card card-dashboard h-100">
            <div class="card-body">
                <div class="text-muted small">Perlu Dinilai</div>
                <div class="h4 mb-0">{{ $stats['pending'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card card-dashboard h-100">
            <div class="card-body">
                <div class="text-muted small">Sudah Dinilai</div>
                <div class="h4 mb-0">{{ $stats['graded'] }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card card-dashboard mt-3">
    <div class="card-header bg-white border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="mb-0">Pengumpulan Siswa</h5>
            <div class="text-muted small">Klik Beri Nilai untuk menyimpan skor dan umpan balik.</div>
        </div>
        <span class="badge text-bg-light">{{ $students->count() }} siswa</span>
    </div>
    <div class="card-body">
        @if ($students->isEmpty())
            <div class="text-center text-muted py-4">
                <i class="bi bi-person-x fs-1" aria-hidden="true"></i>
                <p class="mt-2 mb-0">Belum ada siswa di kelas ini.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Siswa</th>
                            <th>Status</th>
                            <th>Dikumpulkan</th>
                            <th>Nilai</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students->sortBy(fn ($student) => $student->user?->name) as $student)
                            @php($submission = $submissions->get($student->user_id))
                            <tr>
                                <td>
                                    <div class="fw-semibold text-break">{{ $student->user?->name ?? 'Tanpa Nama' }}</div>
                                    <div class="text-muted small">NIS {{ $student->nis ?: '-' }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $assignment->statusBadgeClassFor($submission) }}">
                                        {{ $assignment->statusLabelFor($submission) }}
                                    </span>
                                </td>
                                <td>
                                    @if ($submission)
                                        <div>{{ $submission->submitted_at?->format('d-m-Y H:i') ?? '-' }}</div>
                                        <div class="text-muted small">
                                            @if ($submission->isGraded())
                                                Dinilai {{ $submission->graded_at?->format('d-m-Y H:i') ?? '-' }}
                                            @else
                                                Menunggu penilaian
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($submission?->isGraded())
                                        <span class="fw-semibold">{{ $submission->score }}</span>
                                        <span class="text-muted small">/ {{ $assignment->max_score }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($submission)
                                        <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                            @if ($submission->hasFile())
                                                <a href="{{ route('teacher.submissions.download', [$assignment, $submission]) }}" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-download" aria-hidden="true"></i>
                                                </a>
                                            @endif
                                            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse"
                                                    data-bs-target="#grade-{{ $submission->id }}" aria-expanded="false"
                                                    aria-controls="grade-{{ $submission->id }}">
                                                <i class="bi bi-award me-1" aria-hidden="true"></i> Beri Nilai
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-muted small">Menunggu siswa</span>
                                    @endif
                                </td>
                            </tr>
                            @if ($submission)
                                <tr class="table-light">
                                    <td colspan="5" class="p-0 border-0">
                                        <div class="collapse" id="grade-{{ $submission->id }}">
                                            <div class="p-3">
                                                <div class="mb-3">
                                                    <div class="text-muted small">Jawaban teks siswa</div>
                                                    <div class="text-break" style="white-space: pre-line;">{{ $submission->text_response ?: 'Tidak ada jawaban teks.' }}</div>
                                                </div>

                                                <form method="POST" action="{{ route('teacher.submissions.grade', [$assignment, $submission]) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="row g-2 align-items-end">
                                                        <div class="col-md-2">
                                                            <label class="form-label" for="score-{{ $submission->id }}">Nilai (0–{{ $assignment->max_score }})</label>
                                                            <input type="number" id="score-{{ $submission->id }}" name="score" min="0" max="{{ $assignment->max_score }}"
                                                                   class="form-control" value="{{ old('score', $submission->score) }}" required>
                                                        </div>
                                                        <div class="col-md-7">
                                                            <label class="form-label" for="feedback-{{ $submission->id }}">Umpan Balik</label>
                                                            <textarea id="feedback-{{ $submission->id }}" name="feedback" rows="2" class="form-control">{{ old('feedback', $submission->feedback) }}</textarea>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <button type="submit" class="btn btn-primary w-100">
                                                                <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Simpan Nilai
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
