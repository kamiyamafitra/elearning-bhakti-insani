@extends('student.layouts.dashboard')

@section('title', $quiz->title)
@section('page-title', $quiz->title)
@section('page-description', 'Jawaban tersimpan otomatis. Soal yang sudah dilewati tidak dapat dibuka lagi.')

@section('content')
@php
    $isLastQuestion = $attempt->isOnLastQuestion();
    $questionNumber = $attempt->current_step + 1;
@endphp

<div class="card card-dashboard mb-3 sticky-top" style="top: 0.5rem; z-index: 1010;">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
        <div>
            <span class="text-muted small">Sisa waktu</span>
            <span class="fw-semibold fs-5 ms-1" id="quizTimer" role="timer">--:--</span>
        </div>
        <div class="small text-muted"><span id="quizAnswered">{{ $answeredCount }}</span>/{{ $totalQuestions }} dijawab</div>
        <div class="small text-muted" id="quizSaveStatus" role="status" aria-live="polite"></div>
    </div>
    <div class="progress rounded-0" style="height: 4px;" role="progressbar" aria-label="Kemajuan soal" aria-valuenow="{{ $questionNumber }}" aria-valuemin="1" aria-valuemax="{{ $totalQuestions }}">
        <div class="progress-bar" style="width: {{ $questionNumber / $totalQuestions * 100 }}%;"></div>
    </div>
</div>

<form method="POST" action="{{ route('student.quizzes.submit', $quiz) }}" id="quizForm" data-answer-url="{{ route('student.quizzes.answer', $quiz) }}" data-remaining="{{ $attempt->remainingSeconds() }}" data-was-answered="{{ $selectedOptionId ? '1' : '0' }}">
    @csrf
    <input type="hidden" name="step" value="{{ $attempt->current_step }}">

    <fieldset class="card card-dashboard mb-3">
        <div class="card-body">
            <div class="text-muted small mb-2">Soal {{ $questionNumber }} dari {{ $totalQuestions }}</div>
            <legend class="fs-6 fw-semibold text-break" style="white-space: pre-line;">{{ $question->question }}</legend>
            @if ($question->image_path)
                <img src="{{ route('student.quizzes.questions.image', [$quiz, $question]) }}" alt="Gambar soal {{ $questionNumber }}" class="img-fluid rounded mb-3" style="max-height: 320px;">
            @endif
            @foreach ($options as $option)
                <div class="form-check mb-2">
                    <input type="radio" class="form-check-input" id="option-{{ $option->id }}" name="answers[{{ $question->id }}]" value="{{ $option->id }}" data-question="{{ $question->id }}" @checked((int) $selectedOptionId === $option->id)>
                    <label class="form-check-label text-break" for="option-{{ $option->id }}">{{ chr(65 + $loop->index) }}. {{ $option->text }}</label>
                </div>
            @endforeach
        </div>
    </fieldset>

    <div class="d-flex justify-content-end">
        @if ($isLastQuestion)
            <button type="submit" class="btn btn-primary" id="quizSubmit">Kumpulkan Jawaban</button>
        @else
            <button type="submit" class="btn btn-primary" id="quizNext" formaction="{{ route('student.quizzes.next', $quiz) }}">
                Berikutnya <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
            </button>
        @endif
    </div>
</form>
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('quizForm');
        const timer = document.getElementById('quizTimer');
        const status = document.getElementById('quizSaveStatus');
        const answered = document.getElementById('quizAnswered');
        const token = form.querySelector('input[name="_token"]').value;
        const endsAt = Date.now() + Number(form.dataset.remaining) * 1000;
        let wasAnswered = form.dataset.wasAnswered === '1';
        let submitted = false;

        function isAnswered() {
            return form.querySelector('input[type="radio"]:checked') !== null;
        }

        function tick() {
            const remaining = Math.max(0, Math.round((endsAt - Date.now()) / 1000));
            const hours = Math.floor(remaining / 3600);
            const minutes = String(Math.floor(remaining / 60) % 60).padStart(2, '0');
            const seconds = String(remaining % 60).padStart(2, '0');

            timer.textContent = (hours > 0 ? hours + ':' : '') + minutes + ':' + seconds;
            timer.classList.toggle('text-danger', remaining <= 60);

            if (remaining === 0 && !submitted) {
                clearInterval(interval);
                submitted = true;
                form.submit();
            }
        }

        form.addEventListener('submit', function (event) {
            const isFinish = event.submitter?.id === 'quizSubmit';
            let message = null;

            if (isFinish) {
                message = isAnswered()
                    ? 'Kumpulkan jawaban? Jawaban tidak dapat diubah lagi.'
                    : 'Soal ini belum dijawab. Tetap kumpulkan?';
            } else if (!isAnswered()) {
                message = 'Soal ini belum dijawab dan tidak dapat dibuka lagi. Tetap lanjut?';
            }

            if (message && !confirm(message)) {
                event.preventDefault();

                return;
            }

            if (submitted) {
                event.preventDefault();
            }

            submitted = true;
        });

        form.addEventListener('change', function (event) {
            if (!event.target.dataset.question) {
                return;
            }

            if (!wasAnswered) {
                wasAnswered = true;
                answered.textContent = Number(answered.textContent) + 1;
            }

            status.textContent = 'Menyimpan...';

            fetch(form.dataset.answerUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ question_id: Number(event.target.dataset.question), option_id: Number(event.target.value) }),
            }).then(function (response) {
                if (response.status === 409 || response.status === 422) {
                    location.reload();

                    return;
                }

                status.textContent = response.ok ? 'Tersimpan' : 'Gagal menyimpan. Pilih ulang jawaban.';
            }).catch(function () {
                status.textContent = 'Koneksi terputus. Pilih ulang jawaban.';
            });
        });

        const interval = setInterval(tick, 1000);
        tick();
    })();
</script>
@endpush
