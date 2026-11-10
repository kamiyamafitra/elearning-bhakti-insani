@extends('layouts.dashboard')

@section('title', 'Ubah Password')
@section('page-title', 'Ubah Password')
@section('page-description', 'Ganti password akun Anda.')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-6 col-lg-8">
        @if ($mustChangePassword)
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i>
                Password Anda baru saja diatur ulang oleh administrator. Buat password baru sebelum melanjutkan.
                Password saat ini adalah password sementara yang diberikan administrator.
            </div>
        @endif

        @include('auth.partials.password-form')
    </div>
</div>
@endsection
