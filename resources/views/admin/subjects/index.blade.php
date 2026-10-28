@extends('admin.layouts.dashboard')

@section('title', 'Pengelolaan Mata Pelajaran')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-3 border-bottom">
    <h1 class="h3">Mata Pelajaran</h1>
    <a href="{{ route('admin.subjects.create') }}" class="btn btn-primary">Tambah Mata Pelajaran</a>
</div>

<form method="GET" action="{{ route('admin.subjects.index') }}" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="q" class="form-control" placeholder="Cari nama atau kode mata pelajaran..." value="{{ $search }}">
    </div>
    <div class="col-md-2">
        <button class="btn btn-outline-secondary" type="submit">Cari</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama Mata Pelajaran</th>
                <th>Deskripsi</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($subjects as $subject)
                <tr>
                    <td>{{ $subject->code }}</td>
                    <td>{{ $subject->name }}</td>
                    <td>{{ Str::limit($subject->description, 60) }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.subjects.show', $subject) }}" class="btn btn-sm btn-outline-info">Lihat</a>
                        <a href="{{ route('admin.subjects.edit', $subject) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
                        <form action="{{ route('admin.subjects.destroy', $subject) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus mata pelajaran ini?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center text-muted">Tidak ada data mata pelajaran.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $subjects->links() }}
@endsection
