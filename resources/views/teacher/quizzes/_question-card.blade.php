<div class="card card-dashboard mb-3 {{ $errors->has("questions.{$index}.*") ? 'border border-danger' : '' }}" id="question-card-{{ $index }}" data-question-card>
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center gap-2">
        <h5 class="mb-0">Soal <span data-question-number>{{ $number }}</span></h5>
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-question>Hapus dari daftar</button>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="question-{{ $index }}">Pertanyaan</label>
                <textarea id="question-{{ $index }}" name="questions[{{ $index }}][question]" rows="3" class="form-control @error("questions.{$index}.question") is-invalid @enderror" maxlength="5000" required>{{ old("questions.{$index}.question") }}</textarea>
                @error("questions.{$index}.question")
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="image-{{ $index }}">Gambar <span class="text-muted">(opsional)</span></label>
                <input type="file" id="image-{{ $index }}" name="questions[{{ $index }}][image]" class="form-control @error("questions.{$index}.image") is-invalid @enderror" accept=".png,.jpg,.jpeg,.webp">
                @error("questions.{$index}.image")
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="text-danger small mt-1 d-none" data-image-error role="alert"></div>
                <img alt="Pratinjau gambar soal" class="img-fluid rounded mt-2 d-none" style="max-height: 200px;" data-image-preview>
                <div>
                    <button type="button" class="btn btn-sm btn-light mt-2 d-none" data-clear-image>Batalkan gambar ini</button>
                </div>
                <div class="form-text">PNG, JPG, atau WEBP, maksimal 2 MB.</div>
            </div>
            <div class="col-12">
                <span class="form-label d-block">Pilihan Jawaban</span>
                <p class="small text-muted">Isi {{ \App\Models\QuizQuestion::MIN_OPTIONS }} sampai {{ \App\Models\QuizQuestion::MAX_OPTIONS }} pilihan, lalu tandai satu jawaban yang benar.</p>

                @foreach (range(0, \App\Models\QuizQuestion::MAX_OPTIONS - 1) as $optionIndex)
                    <div class="input-group mb-2">
                        <div class="input-group-text">
                            <input type="radio" name="questions[{{ $index }}][correct_option]" value="{{ $optionIndex }}" class="form-check-input mt-0" aria-label="Tandai pilihan {{ chr(65 + $optionIndex) }} sebagai jawaban benar" @checked((string) old("questions.{$index}.correct_option") === (string) $optionIndex)>
                        </div>
                        <span class="input-group-text">{{ chr(65 + $optionIndex) }}</span>
                        <input type="text" name="questions[{{ $index }}][options][{{ $optionIndex }}]" class="form-control" value="{{ old("questions.{$index}.options.{$optionIndex}") }}" maxlength="1000" aria-label="Pilihan {{ chr(65 + $optionIndex) }}">
                    </div>
                @endforeach

                @error("questions.{$index}.options")
                    <div class="text-danger small">{{ $message }}</div>
                @enderror
                @error("questions.{$index}.correct_option")
                    <div class="text-danger small">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>
