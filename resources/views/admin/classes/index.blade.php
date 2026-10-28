@extends('admin.layouts.dashboard')

@section('title', 'Pengelolaan Kelas')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-3 border-bottom">
    <h1 class="h3">Kelas</h1>
    <a href="{{ route('admin.classes.create') }}" class="btn btn-primary">Tambah Kelas</a>
</div>

<form method="GET" action="{{ route('admin.classes.index') }}" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="q" class="form-control" placeholder="Cari nama atau kode kelas..." value="{{ $search }}">
    </div>
    <div class="col-md-2">
        <button class="btn btn-outline-secondary" type="submit">Cari</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th>Nama Kelas</th>
                <th>Kode</th>
                <th>Tingkat</th>
                <th>Tahun Ajaran</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($classes as $class)
                <tr>
                    <td>{{ $class->name }}</td>
                    <td>{{ $class->code }}</td>
                    <td>{{ $class->level }}</td>
                    <td>{{ $class->academicYear?->name }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.classes.show', $class) }}" class="btn btn-sm btn-outline-info">Lihat</a>
                        <a href="{{ route('admin.classes.edit', $class) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
                        <form action="{{ route('admin.classes.destroy', $class) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus kelas ini?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted">Tidak ada data kelas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $classes->links() }}
@endsection
