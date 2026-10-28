@extends('admin.layouts.dashboard')

@section('title', 'Pengelolaan Guru')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-3 border-bottom">
    <h1 class="h3">Guru</h1>
    <a href="{{ route('admin.teachers.create') }}" class="btn btn-primary">Tambah Guru</a>
</div>

<form method="GET" action="{{ route('admin.teachers.index') }}" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="q" class="form-control" placeholder="Cari nama, surel, NIP..." value="{{ $search }}">
    </div>
    <div class="col-md-2">
        <button class="btn btn-outline-secondary" type="submit">Cari</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th>Nama</th>
                <th>Surel</th>
                <th>NIP</th>
                <th>No. Telepon</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($teachers as $teacher)
                <tr>
                    <td>{{ $teacher->user?->name }}</td>
                    <td>{{ $teacher->user?->email }}</td>
                    <td>{{ $teacher->nip }}</td>
                    <td>{{ $teacher->phone }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.teachers.show', $teacher) }}" class="btn btn-sm btn-outline-info">Lihat</a>
                        <a href="{{ route('admin.teachers.edit', $teacher) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
                        <form action="{{ route('admin.teachers.destroy', $teacher) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus data guru ini?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted">Tidak ada data guru.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $teachers->links() }}
@endsection
