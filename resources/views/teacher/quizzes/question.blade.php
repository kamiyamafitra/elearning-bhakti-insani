@extends('teacher.layouts.dashboard')

@section('title', 'Ubah Soal')
@section('page-title', 'Ubah Soal')
@section('page-description', $quiz->title)

@section('content')
@php
    $selectedCorrect = old('correct_option', $correctOption);
@endphp

<div class="mb-4">
    <a href="{{ route('teacher.quizzes.show', $quiz) }}" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

<div class="card card-dashboard">
    <div class="card-body">
        <form method="POST" action="{{ route('teacher.quizzes.questions.update', [$quiz, $question]) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="question">Pertanyaan</label>
                    <textarea id="question" name="question" rows="3" class="form-control @error('question') is-invalid @enderror" maxlength="5000" required>{{ old('question', $question->question) }}</textarea>
                    @error('question')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="image">Gambar <span class="text-muted">(opsional)</span></label>
                    @if ($question->image_path)
                        <div class="mb-2">
                            <img src="{{ route('teacher.quizzes.questions.image', [$quiz, $question]) }}" alt="Gambar soal saat ini" class="img-fluid rounded" style="max-height: 200px;">
                        </div>
                    @endif
                    <input type="file" id="image" name="image" class="form-control @error('image') is-invalid @enderror" accept=".png,.jpg,.jpeg,.webp">
                    @error('image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="text-danger small mt-1 d-none" id="imageError" role="alert"></div>
                    <img alt="Pratinjau gambar baru" class="img-fluid rounded mt-2 d-none" style="max-height: 200px;" id="imagePreview">
                    <div>
                        <button type="button" class="btn btn-sm btn-light mt-2 d-none" id="clearImage">Batalkan gambar ini</button>
                    </div>
                    <div class="form-text">PNG, JPG, atau WEBP, maksimal 2 MB.</div>
                    @if ($question->image_path)
                        <div class="form-check mt-2">
                            <input type="checkbox" id="remove_image" name="remove_image" value="1" class="form-check-input">
                            <label class="form-check-label" for="remove_image">Hapus gambar</label>
                        </div>
                    @endif
                </div>
                <div class="col-12">
                    <span class="form-label d-block">Pilihan Jawaban</span>
                    <p class="small text-muted">Isi {{ \App\Models\QuizQuestion::MIN_OPTIONS }} sampai {{ \App\Models\QuizQuestion::MAX_OPTIONS }} pilihan, lalu tandai satu jawaban yang benar.</p>

                    @foreach (range(0, \App\Models\QuizQuestion::MAX_OPTIONS - 1) as $index)
                        <div class="input-group mb-2">
                            <div class="input-group-text">
                                <input type="radio" name="correct_option" value="{{ $index }}" class="form-check-input mt-0" aria-label="Tandai pilihan {{ chr(65 + $index) }} sebagai jawaban benar" @checked((string) $selectedCorrect === (string) $index)>
                            </div>
                            <span class="input-group-text">{{ chr(65 + $index) }}</span>
                            <input type="text" name="options[{{ $index }}]" class="form-control" value="{{ old("options.{$index}", $optionTexts[$index] ?? '') }}" maxlength="1000" aria-label="Pilihan {{ chr(65 + $index) }}">
                        </div>
                    @endforeach

                    @error('options')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                    @error('correct_option')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary" id="saveQuestion">Simpan Soal</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@include('teacher.quizzes._image-check')
<script>
    (function () {
        const input = document.getElementById('image');
        const errorMessage = document.getElementById('imageError');
        const clearButton = document.getElementById('clearImage');

        function checkImage() {
            const file = input.files[0];
            const problem = file ? window.quizImageProblem(file) : null;

            errorMessage.textContent = problem ?? '';
            errorMessage.classList.toggle('d-none', !problem);
            window.quizShowImagePreview(document.getElementById('imagePreview'), problem ? null : file);
            clearButton.classList.toggle('d-none', !file);
            input.classList.toggle('is-invalid', Boolean(problem));
            document.getElementById('saveQuestion').disabled = Boolean(problem);
        }

        input.addEventListener('change', checkImage);
        clearButton.addEventListener('click', function () {
            input.value = '';
            checkImage();
        });
    })();
</script>
@endpush
