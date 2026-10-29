<form class="admin-form" method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="row g-3">
        <div class="col-md-5">
            <label class="form-label" for="class_id">Kelas</label>
            <select id="class_id" name="class_id" class="form-select @error('class_id') is-invalid @enderror" required>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected((string) old('class_id', $material->class_id) === (string) $class->id)>
                        {{ $class->name }}
                    </option>
                @endforeach
            </select>
            @error('class_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-7">
            <label class="form-label" for="title">Judul Materi</label>
            <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror"
                   value="{{ old('title', $material->title) }}" maxlength="255" required>
            @error('title')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-12">
            <label class="form-label" for="description">Deskripsi / Petunjuk</label>
            <textarea id="description" name="description" rows="6" class="form-control @error('description') is-invalid @enderror" required>{{ old('description', $material->description) }}</textarea>
            @error('description')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="file">Berkas Materi</label>
            <input type="file" id="file" name="file" class="form-control @error('file') is-invalid @enderror"
                   accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.odt,.ods,.odp">
            @error('file')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, ZIP, ODT, ODS, atau ODP. Maksimal 20 MB.</div>

            @if ($material->hasFile())
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <span class="badge text-bg-light">{{ $material->file_original_name }}</span>
                    <span class="text-muted small">{{ $material->readableFileSize() }}</span>
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="remove_file" value="1" id="remove_file" @checked(old('remove_file'))>
                    <label class="form-check-label" for="remove_file">Hapus berkas saat ini</label>
                </div>
            @endif
        </div>

        <div class="col-md-6">
            <label class="form-label" for="external_url">Tautan Sumber Eksternal (opsional)</label>
            <input type="url" id="external_url" name="external_url" class="form-control @error('external_url') is-invalid @enderror"
                   value="{{ old('external_url', $material->external_url) }}" placeholder="https://contoh.com/materi">
            @error('external_url')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="published_at">Tanggal Publikasi</label>
            <input type="datetime-local" id="published_at" name="published_at" class="form-control @error('published_at') is-invalid @enderror"
                   value="{{ old('published_at', $material->published_at?->format('Y-m-d\TH:i')) }}">
            @error('published_at')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">Kosongkan untuk memakai waktu saat ini ketika dipublikasikan.</div>
        </div>
    </div>

    <div class="form-check my-3">
        <input class="form-check-input" type="checkbox" name="is_published" value="1" id="is_published"
               @checked(old('is_published', $material->exists ? $material->is_published : true))>
        <label class="form-check-label" for="is_published">Publikasikan materi kepada siswa</label>
    </div>

    <div class="admin-form__actions">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> {{ $submitLabel }}
        </button>
        <a href="{{ route('teacher.subjects.show', ['subject' => $subject, 'tab' => 'materials']) }}#materi" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
