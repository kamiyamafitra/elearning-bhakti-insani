@extends('admin.layouts.dashboard')

@section('title', 'Pengelolaan Pengumuman')

@section('content')
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Pengumuman</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">Kelola informasi sekolah untuk siswa dan guru.</p>
    </div>
    <a href="{{ route('admin.announcements.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Tambah Pengumuman
    </a>
</div>

<form method="GET" action="{{ route('admin.announcements.index') }}" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="q" class="form-control" placeholder="Cari judul pengumuman..." value="{{ $search }}">
    </div>
    <div class="col-md-2">
        <button class="btn btn-outline-secondary" type="submit">Cari</button>
    </div>
</form>

<div class="card card-dashboard">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Judul</th>
                        <th>Visibilitas</th>
                        <th>Status</th>
                        <th>Dipublikasikan</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($announcements as $announcement)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold text-break">{{ $announcement->title ?: '(Tanpa judul)' }}</div>
                                <div class="text-muted small">{{ \Illuminate\Support\Str::limit($announcement->content, 80) }}</div>
                            </td>
                            <td>
                                @if ($announcement->visibility === 'role')
                                    <span class="badge text-bg-light">{{ $announcement->target_role === 'teacher' ? 'Guru' : 'Siswa' }}</span>
                                @elseif ($announcement->visibility === 'class')
                                    <span class="badge text-bg-light">{{ $announcement->class?->name ?? 'Kelas' }}</span>
                                @else
                                    <span class="badge text-bg-light">Seluruh Sekolah</span>
                                @endif
                            </td>
                            <td>
                                @php($status = $announcement->statusLabel())
                                <span class="badge {{ ['Berakhir' => 'text-bg-warning', 'Terjadwal' => 'text-bg-info', 'Dipublikasikan' => 'text-bg-success'][$status] ?? 'text-bg-secondary' }}">{{ $status }}</span>
                            </td>
                            <td>
                                {{ $announcement->published_at?->format('d-m-Y H:i') ?? '-' }}
                                @if ($announcement->expires_at)
                                    <div class="small text-muted">s.d. {{ $announcement->expires_at->format('d-m-Y H:i') }}</div>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('admin.announcements.show', $announcement) }}" class="btn btn-sm btn-outline-info" aria-label="Lihat {{ $announcement->title }}">Lihat</a>
                                <a href="{{ route('admin.announcements.edit', $announcement) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
                                <form action="{{ route('admin.announcements.destroy', $announcement) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus pengumuman ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-megaphone fs-1 d-block mb-2" aria-hidden="true"></i>
                                Belum ada pengumuman.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $announcements->links() }}
</div>
@endsection
