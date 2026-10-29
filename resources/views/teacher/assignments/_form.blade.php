<form class="admin-form" method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="row g-3">
        <div class="col-md-5">
            <label class="form-label" for="class_id">Kelas Tujuan</label>
            <select id="class_id" name="class_id" class="form-select @error('class_id') is-invalid @enderror" required>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected((string) old('class_id', $assignment->class_id) === (string) $class->id)>
                        {{ $class->name }}
                    </option>
                @endforeach
            </select>
            @error('class_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-7">
            <label class="form-label" for="title">Judul Tugas</label>
            <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror"
                   value="{{ old('title', $assignment->title) }}" maxlength="255" required>
            @error('title')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-12">
            <label class="form-label" for="instructions">Instruksi / Deskripsi Tugas</label>
            <textarea id="instructions" name="instructions" rows="7" class="form-control @error('instructions') is-invalid @enderror" required>{{ old('instructions', $assignment->instructions) }}</textarea>
            @error('instructions')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="attachment">Lampiran Tugas (opsional)</label>
            <input type="file" id="attachment" name="attachment" class="form-control @error('attachment') is-invalid @enderror"
                   accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.odt,.ods,.odp">
            @error('attachment')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, ZIP, ODT, ODS, atau ODP. Maksimal 20 MB.</div>

            @if ($assignment->hasAttachment())
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <span class="badge text-bg-light">{{ $assignment->attachment_original_name }}</span>
                    <span class="text-muted small">{{ $assignment->readableAttachmentSize() }}</span>
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="remove_attachment" value="1" id="remove_attachment" @checked(old('remove_attachment'))>
                    <label class="form-check-label" for="remove_attachment">Hapus lampiran saat ini</label>
                </div>
            @endif
        </div>

        <div class="col-md-3">
            <label class="form-label" for="due_at">Deadline Pengumpulan</label>
            <input type="datetime-local" id="due_at" name="due_at" class="form-control @error('due_at') is-invalid @enderror"
                   value="{{ old('due_at', $assignment->due_at?->format('Y-m-d\TH:i')) }}" required>
            @error('due_at')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label class="form-label" for="max_score">Skor Maksimal</label>
            <input type="number" id="max_score" name="max_score" min="1" max="1000" class="form-control @error('max_score') is-invalid @enderror"
                   value="{{ old('max_score', $assignment->max_score ?? 100) }}" required>
            @error('max_score')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="status">Status Publikasi</label>
            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                <option value="draft" @selected(old('status', $assignment->status ?? 'draft') === 'draft')>Draf (belum terlihat siswa)</option>
                <option value="published" @selected(old('status', $assignment->status ?? 'draft') === 'published')>Publikasikan kepada siswa</option>
            </select>
            @error('status')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row g-2 my-3">
        <div class="col-md-6">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="allows_text_response" value="1" id="allows_text_response"
                       @checked(old('allows_text_response', $assignment->exists ? $assignment->allows_text_response : true))>
                <label class="form-check-label" for="allows_text_response">Izinkan jawaban teks</label>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="allows_resubmission" value="1" id="allows_resubmission"
                       @checked(old('allows_resubmission', $assignment->allows_resubmission))>
                <label class="form-check-label" for="allows_resubmission">Izinkan pengumpulan ulang sebelum dinilai</label>
            </div>
        </div>
    </div>

    <div class="admin-form__actions">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> {{ $submitLabel }}
        </button>
        <a href="{{ route('teacher.subjects.show', ['subject' => $subject, 'tab' => 'assignments']) }}#tugas" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
