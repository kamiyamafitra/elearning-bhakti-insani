@extends('admin.layouts.dashboard')

@section('title', 'Backup Database')

@section('content')
<div class="directory-page">
    <section class="directory-hero">
        <div class="directory-hero__content">
            <div class="directory-hero__heading">
                <h1 class="directory-hero__title">Backup Database</h1>
                <span class="directory-count-badge">
                    <i class="bi bi-database" aria-hidden="true"></i>
                    {{ $backups->count() }} backup
                </span>
            </div>
            <p class="directory-hero__description">
                Salinan seluruh data aplikasi. Hanya {{ $keepLatest }} backup terbaru yang disimpan; file materi dan tugas yang diunggah tidak termasuk.
            </p>
        </div>
        <form method="POST" action="{{ route('admin.backups.store') }}">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Buat Backup
            </button>
        </form>
    </section>

    @if ($isStale)
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
            {{ $backups->isEmpty() ? 'Belum ada backup.' : 'Backup terakhir sudah lebih dari seminggu yang lalu.' }}
            Buat backup baru agar data tetap aman.
        </div>
    @endif

    <div class="directory-table-card">
        @if ($backups->isEmpty())
            <div class="directory-empty">
                <div class="directory-empty__icon"><i class="bi bi-database" aria-hidden="true"></i></div>
                <h2 class="directory-empty__title">Belum ada backup.</h2>
                <p class="directory-empty__description">Klik Buat Backup untuk menyimpan salinan pertama.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="directory-table">
                    <thead>
                        <tr>
                            <th scope="col">Waktu</th>
                            <th scope="col">Nama File</th>
                            <th scope="col">Ukuran</th>
                            <th scope="col" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($backups as $backup)
                            <tr>
                                <td class="text-nowrap">{{ $backup['created_at']->format('d-m-Y H:i') }}</td>
                                <td class="text-break">{{ $backup['name'] }}</td>
                                <td class="text-nowrap">{{ number_format($backup['size'] / 1048576, 2, ',', '.') }} MB</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('admin.backups.download', $backup['name']) }}" class="btn btn-sm btn-light">
                                        <i class="bi bi-download me-1" aria-hidden="true"></i> Unduh
                                    </a>
                                    <form method="POST" action="{{ route('admin.backups.destroy', $backup['name']) }}" class="d-inline" onsubmit="return confirm('Hapus backup ini? Tindakan ini tidak dapat dibatalkan.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <p class="small text-muted mt-3 mb-0">
        <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>
        File backup berisi data pribadi siswa dan guru. Simpan di tempat yang aman dan jangan dibagikan.
    </p>
</div>
@endsection
