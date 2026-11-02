@php
    $selectedTeaching = old('teaching', $quiz->exists ? "{$quiz->subject_id}-{$quiz->class_id}" : null);
    $isLocked = $isLocked ?? false;
@endphp

<div class="row g-3">
    <div class="col-12">
        <label class="form-label" for="title">Judul</label>
        <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $quiz->title) }}" maxlength="255" required>
        @error('title')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12">
        <label class="form-label" for="teaching">Mata Pelajaran dan Kelas</label>
        <select id="teaching" name="teaching" class="form-select @error('teaching') is-invalid @enderror" required @disabled($isLocked)>
            @foreach ($teachings as $teaching)
                @php($teachingValue = "{$teaching->subject_id}-{$teaching->class_id}")
                <option value="{{ $teachingValue }}" @selected($selectedTeaching === $teachingValue)>{{ $teaching->subject?->name }} &middot; {{ $teaching->class?->name }}</option>
            @endforeach
        </select>
        @if ($isLocked)
            <input type="hidden" name="teaching" value="{{ $selectedTeaching }}">
            <div class="form-text">Tidak dapat diubah karena siswa sudah mulai mengerjakan.</div>
        @endif
        @error('teaching')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12">
        <label class="form-label" for="instructions">Petunjuk <span class="text-muted">(opsional)</span></label>
        <textarea id="instructions" name="instructions" rows="3" class="form-control @error('instructions') is-invalid @enderror" maxlength="5000">{{ old('instructions', $quiz->instructions) }}</textarea>
        @error('instructions')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="opens_at">Dibuka</label>
        <input type="datetime-local" id="opens_at" name="opens_at" class="form-control @error('opens_at') is-invalid @enderror" value="{{ old('opens_at', $quiz->opens_at?->format('Y-m-d\TH:i')) }}" required>
        @error('opens_at')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="closes_at">Ditutup</label>
        <input type="datetime-local" id="closes_at" name="closes_at" class="form-control @error('closes_at') is-invalid @enderror" value="{{ old('closes_at', $quiz->closes_at?->format('Y-m-d\TH:i')) }}" required>
        @error('closes_at')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="duration_minutes">Durasi (menit)</label>
        <input type="number" id="duration_minutes" name="duration_minutes" min="1" max="600" class="form-control @error('duration_minutes') is-invalid @enderror" value="{{ old('duration_minutes', $quiz->duration_minutes) }}" required>
        @error('duration_minutes')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="hidden" name="shuffles" value="0">
            <input type="checkbox" id="shuffles" name="shuffles" value="1" class="form-check-input" @checked(old('shuffles', $quiz->shuffles))>
            <label class="form-check-label" for="shuffles">Acak urutan soal dan pilihan untuk setiap siswa</label>
        </div>
        <div class="form-text">Siswa yang mulai mendekati waktu tutup mendapat sisa waktu sampai kuis ditutup.</div>
    </div>
</div>
