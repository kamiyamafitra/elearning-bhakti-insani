@extends('admin.layouts.dashboard')

@section('title', 'Pengelolaan Pengumuman')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-3 border-bottom">
    <h1 class="h3">Pengumuman</h1>
    <a href="{{ route('admin.announcements.create') }}" class="btn btn-primary">Tambah Pengumuman</a>
</div>

<form method="GET" action="{{ route('admin.announcements.index') }}" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="q" class="form-control" placeholder="Cari judul pengumuman..." value="{{ $search }}">
    </div>
    <div class="col-md-2">
        <button class="btn btn-outline-secondary" type="submit">Cari</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th>Judul</th>
                <th>Visibilitas</th>
                <th>Dipublikasikan</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($announcements as $announcement)
                <tr>
                    <td>{{ $announcement->title }}</td>
                    <td>{{ $announcement->visibility }}</td>
                    <td>{{ $announcement->is_published ? 'Ya' : 'Tidak' }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.announcements.show', $announcement) }}" class="btn btn-sm btn-outline-info">Lihat</a>
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
                    <td colspan="4" class="text-center text-muted">Tidak ada data pengumuman.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $announcements->links() }}
@endsection
