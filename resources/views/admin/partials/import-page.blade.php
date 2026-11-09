{{--
    Shared import page.
    Expects: $heading, $description, $indexRoute, $templateRoute, $storeRoute, $columnGuide (list of [column, required, description]), $notes (list of strings).
--}}
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">{{ $heading }}</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">{{ $description }}</p>
    </div>
    <a href="{{ route($indexRoute) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

@if (session('import_errors'))
    <div class="card shadow-sm border-danger mb-3">
        <div class="card-body">
            <h2 class="h6 text-danger mb-2">
                <i class="bi bi-exclamation-octagon me-1" aria-hidden="true"></i>
                Daftar kesalahan
            </h2>
            <ul class="mb-0 small import-errors">
                @foreach (session('import_errors') as $importError)
                    <li>{{ $importError }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="admin-form__section-title">1. Unduh template</h2>
                <p class="small" style="color: var(--sb-text-secondary);">
                    Isi data mulai baris kedua. Baris pertama adalah judul kolom dan jangan diubah. Hapus baris contoh sebelum mengunggah.
                </p>
                <a href="{{ route($templateRoute) }}" class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i> Unduh Template Excel
                </a>

                <h2 class="admin-form__section-title mt-4">2. Unggah file</h2>
                <form class="admin-form" method="POST" action="{{ route($storeRoute) }}" enctype="multipart/form-data">
                    @csrf

                    <label class="form-label" for="file">File Excel (.xlsx, .xls) atau CSV</label>
                    <input type="file" id="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".xlsx,.xls,.csv" required>
                    @error('file')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Maksimal 5 MB dan 1.000 baris per file.</div>

                    <div class="admin-form__actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1" aria-hidden="true"></i> Impor Data
                        </button>
                        <a href="{{ route($indexRoute) }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="admin-form__section-title">Panduan kolom</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-3">
                        <thead>
                            <tr>
                                <th scope="col">Kolom</th>
                                <th scope="col">Wajib</th>
                                <th scope="col">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($columnGuide as [$column, $isRequired, $columnDescription])
                                <tr>
                                    <td><code>{{ $column }}</code></td>
                                    <td>
                                        @if ($isRequired)
                                            <span class="badge text-bg-danger">Wajib</span>
                                        @else
                                            <span class="badge text-bg-light">Opsional</span>
                                        @endif
                                    </td>
                                    <td class="small">{{ $columnDescription }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <ul class="small mb-0" style="color: var(--sb-text-secondary);">
                    @foreach ($notes as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
