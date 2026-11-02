@extends($layout)

@section('title', 'Buat Diskusi')
@section('page-title', 'Buat Diskusi')
@section('page-description', 'Ajukan pertanyaan atau topik kepada kelas.')

@section('content')
<div class="mb-4">
    <a href="{{ route("{$routePrefix}.index") }}" class="btn btn-light">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

<div class="card card-dashboard">
    <div class="card-body">
        <form method="POST" action="{{ route("{$routePrefix}.store") }}" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="room">Kelas dan Mata Pelajaran</label>
                    <select id="room" name="room" class="form-select @error('room') is-invalid @enderror" required>
                        <option value="">Pilih...</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->key }}" @selected($selectedRoomKey === $room->key)>{{ $room->label }}</option>
                        @endforeach
                    </select>
                    @error('room')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="title">Judul</label>
                    <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" maxlength="255" required>
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="body">Isi</label>
                    <textarea id="body" name="body" rows="6" class="form-control @error('body') is-invalid @enderror" maxlength="5000" required>{{ old('body') }}</textarea>
                    @error('body')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    @include('discussions._image-field')
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Kirim Diskusi</button>
            </div>
        </form>
    </div>
</div>
@endsection
