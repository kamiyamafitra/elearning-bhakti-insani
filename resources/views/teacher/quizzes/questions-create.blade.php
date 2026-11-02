@extends('teacher.layouts.dashboard')

@section('title', 'Tambah Soal')
@section('page-title', 'Tambah Soal')
@section('page-description', $quiz->title)
@section('hide-error-summary', '1')

@section('content')
@php
    $questionKeys = array_keys(old('questions', [0 => []]));
    $questionProblems = collect($questionKeys)
        ->map(fn ($key, $position) => ['key' => $key, 'number' => $position + 1, 'messages' => collect($errors->get("questions.{$key}.*"))->flatten()->unique()])
        ->filter(fn (array $problem) => $problem['messages']->isNotEmpty());
@endphp

<div class="mb-4">
    <a href="{{ route('teacher.quizzes.show', $quiz) }}" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <p class="mb-2">Soal belum disimpan. {{ $errors->first('questions') }} Perbaiki bagian berikut, lalu simpan lagi. Gambar perlu dipilih ulang.</p>
        <ul class="mb-0">
            @foreach ($questionProblems as $problem)
                <li>
                    <a href="#question-card-{{ $problem['key'] }}" class="alert-link">Soal {{ $problem['number'] }}</a>:
                    {{ $problem['messages']->implode(' ') }}
                </li>
            @endforeach
        </ul>
    </div>
@endif

<p class="small text-muted">Nilai maksimal {{ \App\Models\Quiz::MAX_SCORE }} dibagi rata ke semua soal secara otomatis.</p>

<form method="POST" action="{{ route('teacher.quizzes.questions.store', $quiz) }}" enctype="multipart/form-data" id="questionsForm" data-max-questions="{{ $maxQuestions }}" data-max-upload-bytes="{{ $maxUploadBytes }}">
    @csrf

    <div id="questionCards">
        @foreach ($questionKeys as $questionIndex)
            @include('teacher.quizzes._question-card', ['index' => $questionIndex, 'number' => $loop->iteration])
        @endforeach
    </div>

    <template id="questionCardTemplate">
        @include('teacher.quizzes._question-card', ['index' => '__INDEX__', 'number' => ''])
    </template>

    <div class="d-flex flex-wrap justify-content-between gap-2">
        <button type="button" class="btn btn-light" id="addQuestion">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Tambah Soal Lain
        </button>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Simpan <span id="questionCount">1</span> Soal
        </button>
    </div>
</form>
@endsection

@push('scripts')
@include('teacher.quizzes._image-check')
<script>
    (function () {
        const form = document.getElementById('questionsForm');
        const cards = document.getElementById('questionCards');
        const template = document.getElementById('questionCardTemplate');
        const addButton = document.getElementById('addQuestion');
        const count = document.getElementById('questionCount');
        const maxQuestions = Number(form.dataset.maxQuestions);
        const maxUploadBytes = Number(form.dataset.maxUploadBytes);
        let nextIndex = Math.max(...[...cards.querySelectorAll('textarea')].map((input) => Number(input.id.replace('question-', '')))) + 1;

        function render() {
            const allCards = [...cards.querySelectorAll('[data-question-card]')];

            allCards.forEach(function (card, index) {
                card.querySelector('[data-question-number]').textContent = index + 1;
                card.querySelector('[data-remove-question]').classList.toggle('d-none', allCards.length === 1);
            });

            count.textContent = allCards.length;
            addButton.disabled = allCards.length >= maxQuestions;
        }

        addButton.addEventListener('click', function () {
            const wrapper = document.createElement('div');
            wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', nextIndex++);
            const card = wrapper.firstElementChild;

            cards.appendChild(card);
            render();
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            card.querySelector('textarea').focus({ preventScroll: true });
        });

        function checkImage(input) {
            const card = input.closest('[data-question-card]');
            const file = input.files[0];
            const problem = file ? window.quizImageProblem(file) : null;
            const errorMessage = card.querySelector('[data-image-error]');

            errorMessage.textContent = problem ?? '';
            errorMessage.classList.toggle('d-none', !problem);
            window.quizShowImagePreview(card.querySelector('[data-image-preview]'), problem ? null : file);
            card.querySelector('[data-clear-image]').classList.toggle('d-none', !file);
            input.classList.toggle('is-invalid', Boolean(problem));
            card.classList.toggle('border', Boolean(problem));
            card.classList.toggle('border-danger', Boolean(problem));

            return problem;
        }

        cards.addEventListener('change', function (event) {
            if (event.target.type === 'file') {
                checkImage(event.target);
            }
        });

        cards.addEventListener('click', function (event) {
            const clearButton = event.target.closest('[data-clear-image]');

            if (clearButton) {
                const input = clearButton.closest('[data-question-card]').querySelector('input[type="file"]');

                input.value = '';
                checkImage(input);
            }
        });

        cards.addEventListener('click', function (event) {
            const removeButton = event.target.closest('[data-remove-question]');

            if (removeButton && confirm('Hapus soal ini dari daftar?')) {
                removeButton.closest('[data-question-card]').remove();
                render();
            }
        });

        form.addEventListener('submit', function (event) {
            const imageProblems = [...cards.querySelectorAll('[data-question-card]')]
                .map((card, index) => ({ card, number: index + 1, problem: checkImage(card.querySelector('input[type="file"]')) }))
                .filter((item) => item.problem);

            if (imageProblems.length > 0) {
                event.preventDefault();
                imageProblems[0].card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                alert('Soal belum disimpan.\n\n' + imageProblems.map((item) => 'Soal ' + item.number + ': ' + item.problem).join('\n'));

                return;
            }

            const uploadBytes = [...form.querySelectorAll('input[type="file"]')]
                .reduce((total, input) => total + (input.files[0]?.size ?? 0), 0);

            if (uploadBytes > maxUploadBytes * 0.9) {
                event.preventDefault();
                alert('Total ukuran gambar terlalu besar untuk sekali simpan. Kurangi gambar, lalu tambahkan sisanya setelah disimpan.');
            }
        });

        render();
    })();
</script>
@endpush
