@extends('admin.layouts.dashboard')

@section('title', 'Pengaturan Sekolah')
@section('page-title', 'Pengaturan Sekolah')
@section('page-description', 'Identitas sekolah dan semester yang sedang berjalan.')

@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card card-dashboard">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0">Logo Sekolah</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        @if ($settings->logoUrl())
                            <img src="{{ $settings->logoUrl() }}" alt="Logo {{ $settings->name }}" class="img-fluid" style="max-height: 120px;">
                        @else
                            <span class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="bi bi-mortarboard-fill fs-2" aria-hidden="true"></i>
                            </span>
                        @endif
                    </div>

                    <label class="form-label" for="logo">Unggah Logo</label>
                    <input type="file" id="logo" name="logo" class="form-control @error('logo') is-invalid @enderror" accept=".png,.jpg,.jpeg,.webp">
                    @error('logo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">PNG, JPG, atau WEBP, maksimal 1 MB. Sebaiknya berbentuk persegi.</div>

                    @if ($settings->logo_path)
                        <div class="form-check mt-3">
                            <input type="checkbox" id="remove_logo" name="remove_logo" value="1" class="form-check-input">
                            <label class="form-check-label" for="remove_logo">Hapus logo</label>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card card-dashboard">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0">Identitas Sekolah</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="name">Nama Sekolah</label>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $settings->name) }}" maxlength="100" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="city">Kota</label>
                            <input type="text" id="city" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $settings->city) }}" maxlength="100">
                            @error('city')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="address">Alamat</label>
                            <input type="text" id="address" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $settings->address) }}" maxlength="255">
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Telepon</label>
                            <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $settings->phone) }}" maxlength="30">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $settings->email) }}" maxlength="255">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="semester">Semester Berjalan</label>
                            <select id="semester" name="semester" class="form-select @error('semester') is-invalid @enderror" required>
                                @foreach ($semesterLabels as $semesterValue => $semesterLabel)
                                    <option value="{{ $semesterValue }}" @selected(old('semester', $settings->semester) === $semesterValue)>{{ $semesterLabel }}</option>
                                @endforeach
                            </select>
                            @error('semester')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Tampil di dasbor. Tahun ajaran diatur di menu Tahun Ajaran.</div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Simpan Perubahan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
