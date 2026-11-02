@extends($layout)

@section('title', 'Diskusi Kelas')
@section('page-title', 'Diskusi Kelas')
@section('page-description', 'Tanya jawab per kelas dan mata pelajaran.')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <form method="GET" action="{{ route("{$routePrefix}.index") }}" class="d-flex gap-2">
        <select name="room" class="form-select" aria-label="Saring menurut mata pelajaran" onchange="this.form.submit()">
            <option value="">Semua Mata Pelajaran</option>
            @foreach ($rooms as $room)
                <option value="{{ $room->key }}" @selected($selectedRoom?->key === $room->key)>{{ $room->label }}</option>
            @endforeach
        </select>
    </form>
    @if ($rooms->isNotEmpty())
        <a href="{{ route("{$routePrefix}.create", array_filter(['room' => $selectedRoom?->key])) }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Buat Diskusi
        </a>
    @endif
</div>

@forelse ($discussions as $discussion)
    <div class="card card-dashboard mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div style="min-width: 0; flex: 1 1 16rem;">
                <h5 class="mb-1 text-break">
                    @if ($discussion->is_pinned)
                        <i class="bi bi-pin-angle-fill text-primary" title="Disematkan" aria-label="Disematkan"></i>
                    @endif
                    {{ $discussion->title }}
                </h5>
                <div class="small text-muted mb-2">
                    {{ $discussion->user?->name ?? 'Pengguna dihapus' }}
                    @if ($discussion->user?->isTeacher())
                        <span class="badge text-bg-primary">Guru</span>
                    @endif
                    &middot; {{ $discussion->updated_at->format('d/m/Y H:i') }}
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge text-bg-light">{{ $discussion->class->name }} &middot; {{ $discussion->subject->name }}</span>
                    <span class="badge text-bg-light"><i class="bi bi-chat-left-text" aria-hidden="true"></i> {{ $discussion->replies_count }} balasan</span>
                    @if ($discussion->has_best_reply)
                        <span class="badge text-bg-success">Terjawab</span>
                    @endif
                    @if ($discussion->is_closed)
                        <span class="badge text-bg-secondary">Ditutup</span>
                    @endif
                </div>
            </div>
            <a href="{{ route("{$routePrefix}.show", $discussion) }}" class="btn btn-outline-primary">Buka</a>
        </div>
    </div>
@empty
    <div class="card card-dashboard">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-chat-dots fs-1" aria-hidden="true"></i>
            <p class="mt-2 mb-1">Belum ada diskusi.</p>
            <p class="small mb-0">
                {{ $rooms->isEmpty() ? 'Diskusi tersedia setelah Anda terhubung dengan kelas dan mata pelajaran.' : 'Mulai diskusi pertama dengan tombol Buat Diskusi.' }}
            </p>
        </div>
    </div>
@endforelse

{{ $discussions->links() }}
@endsection
