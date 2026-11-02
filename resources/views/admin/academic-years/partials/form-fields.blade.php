<h2 class="admin-form__section-title">Informasi Tahun Ajaran</h2>

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label" for="name">Tahun Ajaran</label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $academicYear?->name) }}" placeholder="Contoh: 2025/2026" maxlength="9" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="start_date">Tanggal Mulai</label>
        <input type="date" id="start_date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', $academicYear?->start_date?->format('Y-m-d')) }}" required>
        @error('start_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="end_date">Tanggal Selesai</label>
        <input type="date" id="end_date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date', $academicYear?->end_date?->format('Y-m-d')) }}" required>
        @error('end_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="form-check my-3">
    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $academicYear?->is_active))>
    <label class="form-check-label" for="is_active">Jadikan tahun ajaran aktif</label>
    <div class="form-text">Hanya satu tahun ajaran yang bisa aktif. Tahun ajaran aktif lainnya akan otomatis dinonaktifkan.</div>
</div>
