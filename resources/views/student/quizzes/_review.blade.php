<div class="row g-3 my-1">
    <div class="col-4">
        <div class="card card-dashboard h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Benar</div>
                <div class="h4 mb-0 text-success">{{ $review->where('status', 'correct')->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card card-dashboard h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Salah</div>
                <div class="h4 mb-0 text-danger">{{ $review->where('status', 'wrong')->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card card-dashboard h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Tidak Dijawab</div>
                <div class="h4 mb-0 text-muted">{{ $review->where('status', 'unanswered')->count() }}</div>
            </div>
        </div>
    </div>
</div>

@foreach ($review as $item)
    <div class="card card-dashboard mt-3">
        <div class="card-body">
            <div class="d-flex justify-content-between gap-3 mb-2">
                <span class="text-muted small">Soal {{ $loop->iteration }} dari {{ $review->count() }}</span>
                @switch($item['status'])
                    @case('correct')
                        <span class="badge text-bg-success align-self-start">Benar</span>
                        @break
                    @case('wrong')
                        <span class="badge text-bg-danger align-self-start">Salah</span>
                        @break
                    @default
                        <span class="badge text-bg-secondary align-self-start">Tidak dijawab</span>
                @endswitch
            </div>

            <div class="fw-semibold text-break mb-2" style="white-space: pre-line;">{{ $item['question']->question }}</div>

            @if ($item['question']->image_path)
                <img src="{{ route('student.quizzes.questions.image', [$quiz, $item['question']]) }}" alt="Gambar soal {{ $loop->iteration }}" class="img-fluid rounded mb-3" style="max-height: 320px;" loading="lazy">
            @endif

            <ul class="list-unstyled mb-0">
                @foreach ($item['options'] as $option)
                    @php
                        $isSelected = $option->id === $item['selected_option_id'];
                    @endphp
                    <li class="text-break p-2 rounded mb-1 {{ $option->is_correct ? 'bg-success-subtle' : ($isSelected ? 'bg-danger-subtle' : '') }}">
                        {{ chr(65 + $loop->index) }}. {{ $option->text }}
                        @if ($option->is_correct)
                            <span class="small fw-semibold text-success ms-1"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Jawaban benar</span>
                        @endif
                        @if ($isSelected)
                            <span class="small fw-semibold ms-1 {{ $option->is_correct ? 'text-success' : 'text-danger' }}"><i class="bi {{ $option->is_correct ? 'bi-person-check-fill' : 'bi-person-x-fill' }}" aria-hidden="true"></i> Jawaban Anda</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endforeach
