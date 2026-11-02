<label class="form-label" for="image">Gambar <span class="text-muted">(opsional)</span></label>
<input type="file" id="image" name="image" class="form-control @error('image') is-invalid @enderror" accept=".png,.jpg,.jpeg,.webp">
@error('image')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
<div class="text-danger small mt-1 d-none" id="imageError" role="alert"></div>
<img alt="Pratinjau gambar" class="rounded bg-light mt-2 d-none" style="width: 100%; max-width: 280px; aspect-ratio: 1 / 1; object-fit: contain;" id="imagePreview">
<div class="form-text">PNG, JPG, atau WEBP, maksimal 2 MB.</div>

@push('scripts')
@include('teacher.quizzes._image-check')
<script>
    (function () {
        const input = document.getElementById('image');
        const errorMessage = document.getElementById('imageError');
        const submitButton = input.form.querySelector('[type="submit"]');

        input.addEventListener('change', function () {
            const file = input.files[0];
            const problem = file ? window.quizImageProblem(file) : null;

            errorMessage.textContent = problem ?? '';
            errorMessage.classList.toggle('d-none', !problem);
            window.quizShowImagePreview(document.getElementById('imagePreview'), problem ? null : file);
            input.classList.toggle('is-invalid', Boolean(problem));
            submitButton.disabled = Boolean(problem);
        });
    })();
</script>
@endpush
