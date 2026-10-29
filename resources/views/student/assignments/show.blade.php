@extends('student.layouts.dashboard')

@section('title', 'Detail Tugas')
@section('page-title', $assignment->title)
@section('page-description', 'Baca instruksi, unduh lampiran, dan kumpulkan tugas Anda.')

@section('content')
@php
    $canResubmit = $submission === null
        || ($assignment->allows_resubmission && ! $submission->isGraded());
    $statusKey = $assignment->statusFor($submission);
@endphp

<div class="mb-4">
    <a href="{{ route('student.subjects.show', ['subject' => $subject, 'tab' => 'assignments']) }}#tugas" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali ke {{ $subject->name }}
    </a>
</div>

@if ($statusKey === \App\Models\AssignmentSubmission::STATUS_LATE)
    <div class="alert alert-warning border-0">
        <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
        Tugas ini dikumpulkan setelah deadline dan tercatat sebagai <strong>Terlambat</strong>.
    </div>
@endif

<div class="card card-dashboard">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <h2 class="h4 mb-0 text-break">{{ $assignment->title }}</h2>
                    <span class="badge {{ $assignment->statusBadgeClassFor($submission) }}">
                        {{ $assignment->statusLabelFor($submission) }}
                    </span>
                </div>
                <div class="text-muted">
                    {{ $assignment->subject?->name ?? $subject->name }}
                    &middot; {{ $assignment->class?->name ?? '-' }}
                    &middot; {{ $assignment->teacher?->name ?? 'Guru' }}
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
        <div class="text-break" style="white-space: pre-line;">{{ $assignment->instructions }}</div>

        <div class="d-flex flex-wrap align-items-center gap-2 mt-4">
            @if ($assignment->hasAttachment())
                <a href="{{ route('student.assignments.download', [$subject, $assignment]) }}" class="btn btn-outline-primary">
                    <i class="bi bi-download me-1" aria-hidden="true"></i> Unduh Lampiran Tugas
                </a>
                <span class="text-muted small">{{ $assignment->attachment_original_name }} &middot; {{ $assignment->readableAttachmentSize() }}</span>
            @else
                <span class="text-muted small">Guru tidak melampirkan berkas untuk tugas ini.</span>
            @endif
        </div>
    </div>
</div>

@if ($submission)
    <div class="card card-dashboard mt-3">
        <div class="card-header bg-white border-0">
            <h5 class="mb-0">Pengumpulan Saya</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">Status</div>
                    <span class="badge {{ $assignment->statusBadgeClassFor($submission) }}">
                        {{ $assignment->statusLabelFor($submission) }}
                    </span>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Waktu Pengumpulan</div>
                    <div class="fw-semibold">{{ $submission->submitted_at?->format('d-m-Y H:i') ?? '-' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Nilai</div>
                    <div class="fw-semibold">
                        @if ($submission->isGraded())
                            {{ $submission->score }} / {{ $assignment->max_score }}
                        @else
                            Belum dinilai
                        @endif
                    </div>
                </div>

                @if ($submission->hasFile())
                    <div class="col-12">
                        <div class="text-muted small">Berkas yang Dikumpulkan</div>
                        <a href="{{ route('student.submissions.download', [$subject, $assignment]) }}" class="btn btn-sm btn-outline-primary mt-1">
                            <i class="bi bi-download me-1" aria-hidden="true"></i> {{ $submission->file_original_name }}
                        </a>
                        <span class="text-muted small ms-1">{{ $submission->readableFileSize() }}</span>
                    </div>
                @endif

                <div class="col-12">
                    <div class="text-muted small">Jawaban Teks</div>
                    <div class="text-break" style="white-space: pre-line;">{{ $submission->text_response ?: 'Tidak ada jawaban teks.' }}</div>
                </div>

                @if ($submission->isGraded())
                    <div class="col-12">
                        <div class="text-muted small">Umpan Balik Guru</div>
                        <div class="alert alert-success border-0 mb-0 mt-1 text-break" style="white-space: pre-line;">{{ $submission->feedback ?: 'Guru tidak memberikan catatan tambahan.' }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif

<div class="card card-dashboard mt-3">
    <div class="card-header bg-white border-0">
        <h5 class="mb-0">{{ $submission ? 'Kumpulkan Ulang' : 'Kumpulkan Tugas' }}</h5>
    </div>
    <div class="card-body">
        @if (! $canResubmit)
            <div class="alert alert-info border-0 mb-0">
                <div class="d-flex gap-2">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    <div>
                        @if ($submission?->isGraded())
                            Tugas ini sudah dinilai sehingga tidak dapat dikumpulkan ulang.
                        @else
                            Tugas ini sudah dikumpulkan dan pengumpulan ulang tidak diizinkan.
                        @endif
                    </div>
                </div>
            </div>
        @else
            @if ($submission && $assignment->allows_resubmission)
                <div class="alert alert-warning border-0">
                    Anda mengumpulkan ulang tugas ini. Berkas dan jawaban sebelumnya akan diperbarui, dan nilai sebelumnya akan dihapus sampai guru menilai ulang.
                </div>
            @endif

            <form method="POST" action="{{ route('student.assignments.submit', [$subject, $assignment]) }}" enctype="multipart/form-data">
                @csrf

                <div class="row g-3">
                    @if ($assignment->allows_text_response)
                        <div class="col-12">
                            <label class="form-label" for="text_response">Jawaban Teks (opsional)</label>
                            <textarea id="text_response" name="text_response" rows="5" class="form-control @error('text_response') is-invalid @enderror">{{ old('text_response', $submission?->text_response) }}</textarea>
                            @error('text_response')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <div class="col-12">
                        <label class="form-label" for="file">Berkas Jawaban</label>
                        <input type="file" id="file" name="file" class="form-control @error('file') is-invalid @enderror"
                               accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.odt,.ods,.odp">
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, ZIP, ODT, ODS, atau ODP. Maksimal 20 MB.
                            @if ($submission?->hasFile())
                                Unggah berkas baru untuk menggantikan berkas sebelumnya.
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload me-1" aria-hidden="true"></i> {{ $submission ? 'Kumpulkan Ulang' : 'Kumpulkan Tugas' }}
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
