@extends('admin.layouts.dashboard')

@section('title', 'Pengelolaan Siswa')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-3 border-bottom">
    <h1 class="h3">Siswa</h1>
    <a href="{{ route('admin.students.create') }}" class="btn btn-primary">Tambah Siswa</a>
</div>

<form method="GET" action="{{ route('admin.students.index') }}" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="q" class="form-control" placeholder="Cari nama, surel, NIS/NISN..." value="{{ $search }}">
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
                <th>NISN</th>
                <th>NIS</th>
                <th>Kelas</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $student)
                <tr>
                    <td>{{ $student->user?->name }}</td>
                    <td>{{ $student->user?->email }}</td>
                    <td>{{ $student->nisn }}</td>
                    <td>{{ $student->nis }}</td>
                    <td>{{ $student->class?->name }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.students.show', $student) }}" class="btn btn-sm btn-outline-info">Lihat</a>
                        <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
                        <form action="{{ route('admin.students.destroy', $student) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus data siswa ini?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted">Tidak ada data siswa.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $students->links() }}
@endsection
