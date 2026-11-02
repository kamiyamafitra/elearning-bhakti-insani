@php
    $childReplies = $repliesByParent->get($reply->id, collect());
    $maxIndentedDepth = 5;
@endphp

<div id="reply-{{ $reply->id }}" data-reply>
    <div class="card card-dashboard mb-3 {{ $reply->is_best ? 'border border-success' : '' }}">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div class="small text-muted">
                    {{ $reply->user?->name ?? 'Pengguna dihapus' }}
                    @if ($reply->user?->isTeacher())
                        <span class="badge text-bg-primary">Guru</span>
                    @endif
                    &middot; {{ $reply->created_at->format('d/m/Y H:i') }}
                </div>
                @if ($reply->is_best)
                    <span class="badge text-bg-success"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Jawaban terbaik</span>
                @endif
            </div>
            <div class="text-break" style="white-space: pre-line;">{{ $reply->body }}</div>
            @if ($reply->image_path)
                <a href="{{ route("{$routePrefix}.replies.image", [$discussion, $reply]) }}" target="_blank" rel="noopener">
                    <img src="{{ route("{$routePrefix}.replies.image", [$discussion, $reply]) }}" alt="Gambar balasan" class="rounded bg-light mt-3" style="width: 100%; max-width: 280px; aspect-ratio: 1 / 1; object-fit: contain;" loading="lazy">
                </a>
            @endif
            <div class="d-flex flex-wrap gap-2 mt-3">
                @can('reply', $discussion)
                    <button type="button" class="btn btn-sm btn-outline-primary" data-reply-to="{{ $reply->id }}" data-reply-author="{{ $reply->user?->name ?? 'Pengguna dihapus' }}" data-reply-text="{{ \Illuminate\Support\Str::limit($reply->body, 120) }}">
                        <i class="bi bi-reply me-1" aria-hidden="true"></i> Balas
                    </button>
                @endcan
                @if ($childReplies->isNotEmpty())
                    <button type="button" class="btn btn-sm btn-light" data-toggle-replies aria-expanded="true">Sembunyikan balasan</button>
                @endif
                @can('moderate', $discussion)
                    <form method="POST" action="{{ route("{$routePrefix}.replies.best", [$discussion, $reply]) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-sm btn-outline-success">
                            {{ $reply->is_best ? 'Batalkan Jawaban Terbaik' : 'Tandai Jawaban Terbaik' }}
                        </button>
                    </form>
                @endcan
                @can('deleteReply', [$discussion, $reply])
                    <form method="POST" action="{{ route("{$routePrefix}.replies.destroy", [$discussion, $reply]) }}" onsubmit="return confirm('Hapus balasan ini?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    <div class="ms-3 ms-md-4" data-reply-slot></div>

    @if ($childReplies->isNotEmpty())
        <div class="{{ $depth < $maxIndentedDepth ? 'ms-2 ms-md-3 ps-2 ps-md-3 border-start border-2' : '' }}" data-reply-children>
            @foreach ($childReplies as $childReply)
                @include('discussions._reply', ['reply' => $childReply, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>
