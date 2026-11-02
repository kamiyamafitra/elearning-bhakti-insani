@extends($layout)

@section('title', $discussion->title)
@section('page-title', 'Diskusi Kelas')
@section('page-description', $discussion->class->name.' · '.$discussion->subject->name)

@section('content')
<div class="d-flex flex-wrap justify-content-between gap-2 mb-4">
    <a href="{{ route("{$routePrefix}.index") }}" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
    <div class="d-flex flex-wrap gap-2">
        @can('moderate', $discussion)
            <form method="POST" action="{{ route("{$routePrefix}.pin", $discussion) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-light">
                    <i class="bi bi-pin-angle me-1" aria-hidden="true"></i> {{ $discussion->is_pinned ? 'Lepas Sematan' : 'Sematkan' }}
                </button>
            </form>
            <form method="POST" action="{{ route("{$routePrefix}.close", $discussion) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-light">
                    <i class="bi {{ $discussion->is_closed ? 'bi-unlock' : 'bi-lock' }} me-1" aria-hidden="true"></i> {{ $discussion->is_closed ? 'Buka Kembali' : 'Tutup Diskusi' }}
                </button>
            </form>
        @endcan
        @can('delete', $discussion)
            <form method="POST" action="{{ route("{$routePrefix}.destroy", $discussion) }}" onsubmit="return confirm('Hapus diskusi ini beserta semua balasannya?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">
                    <i class="bi bi-trash me-1" aria-hidden="true"></i> Hapus
                </button>
            </form>
        @endcan
    </div>
</div>

<div class="card card-dashboard mb-4">
    <div class="card-body">
        <h4 class="text-break mb-1">
            @if ($discussion->is_pinned)
                <i class="bi bi-pin-angle-fill text-primary" title="Disematkan" aria-label="Disematkan"></i>
            @endif
            {{ $discussion->title }}
        </h4>
        <div class="small text-muted mb-3">
            {{ $discussion->user?->name ?? 'Pengguna dihapus' }}
            @if ($discussion->user?->isTeacher())
                <span class="badge text-bg-primary">Guru</span>
            @endif
            &middot; {{ $discussion->created_at->format('d/m/Y H:i') }}
            @if ($discussion->is_closed)
                <span class="badge text-bg-secondary">Ditutup</span>
            @endif
        </div>
        <div class="text-break" style="white-space: pre-line;">{{ $discussion->body }}</div>
        @if ($discussion->image_path)
            <a href="{{ route("{$routePrefix}.image", $discussion) }}" target="_blank" rel="noopener">
                <img src="{{ route("{$routePrefix}.image", $discussion) }}" alt="Gambar diskusi" class="rounded bg-light mt-3" style="width: 100%; max-width: 280px; aspect-ratio: 1 / 1; object-fit: contain;" loading="lazy">
            </a>
        @endif
    </div>
</div>

<h5 class="mb-3">{{ $replies->count() }} Balasan</h5>

@foreach ($repliesByParent->get(0, collect()) as $reply)
    @include('discussions._reply', ['reply' => $reply, 'depth' => 0])
@endforeach

@can('reply', $discussion)
    @php
        $replyingTo = $replies->firstWhere('id', (int) old('parent_id'));
    @endphp

    <div id="replyFormHome">
        <div class="card card-dashboard mb-3" id="replyCard">
            <div class="card-body">
                <form method="POST" action="{{ route("{$routePrefix}.replies.store", $discussion) }}" enctype="multipart/form-data" id="replyForm">
                    @csrf
                    <input type="hidden" name="parent_id" id="replyParentId" value="{{ $replyingTo?->id }}">

                    <div class="d-flex justify-content-between align-items-start gap-2 small text-muted border-start border-3 border-primary ps-2 mb-3 {{ $replyingTo ? '' : 'd-none' }}" id="replyTarget">
                        <div class="text-break">
                            Membalas <span class="fw-semibold" id="replyTargetAuthor">{{ $replyingTo?->user?->name }}</span>:
                            <span id="replyTargetText">{{ $replyingTo ? \Illuminate\Support\Str::limit($replyingTo->body, 120) : '' }}</span>
                        </div>
                        <button type="button" class="btn-close" id="cancelReplyTarget" aria-label="Batalkan balasan"></button>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="body">Tulis Balasan</label>
                        <textarea id="body" name="body" rows="4" class="form-control @error('body') is-invalid @enderror" maxlength="5000" required>{{ old('body') }}</textarea>
                        @error('body')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        @include('discussions._image-field')
                    </div>
                    <button type="submit" class="btn btn-primary">Kirim Balasan</button>
                </form>
            </div>
        </div>
    </div>
@else
    <p class="text-muted text-center py-3 mb-0"><i class="bi bi-lock me-1" aria-hidden="true"></i> Diskusi ini sudah ditutup.</p>
@endcan
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('replyForm');

        document.addEventListener('click', function (event) {
            const toggle = event.target.closest('[data-toggle-replies]');

            if (!toggle) {
                return;
            }

            const children = toggle.closest('[data-reply]').querySelector(':scope > [data-reply-children]');
            const isHidden = children.classList.toggle('d-none');

            toggle.setAttribute('aria-expanded', String(!isHidden));
            toggle.textContent = isHidden
                ? 'Tampilkan ' + children.querySelectorAll('[data-reply]').length + ' balasan'
                : 'Sembunyikan balasan';
        });

        if (!form) {
            return;
        }

        const card = document.getElementById('replyCard');
        const home = document.getElementById('replyFormHome');
        const parentInput = document.getElementById('replyParentId');
        const target = document.getElementById('replyTarget');

        document.addEventListener('click', function (event) {
            const button = event.target.closest('[data-reply-to]');

            if (!button) {
                return;
            }

            parentInput.value = button.dataset.replyTo;
            document.getElementById('replyTargetAuthor').textContent = button.dataset.replyAuthor;
            document.getElementById('replyTargetText').textContent = button.dataset.replyText;
            target.classList.remove('d-none');
            button.closest('[data-reply]').querySelector(':scope > [data-reply-slot]').appendChild(card);
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            form.querySelector('textarea').focus({ preventScroll: true });
        });

        document.getElementById('cancelReplyTarget').addEventListener('click', function () {
            parentInput.value = '';
            target.classList.add('d-none');
            home.appendChild(card);
        });
    })();
</script>
@endpush
