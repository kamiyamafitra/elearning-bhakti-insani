@extends('layouts.app')

@section('title', 'Dashboard Administrator')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Dashboard Administrator</h4>
</div>
<div class="card shadow-sm">
    <div class="card-body">
        <p class="mb-0">Selamat datang, {{ auth()->user()->name }}. Anda masuk sebagai Administrator.</p>
        <p class="text-muted mb-0">Fitur pengelolaan pengguna, tahun ajaran, kelas, mata pelajaran, dan penugasan guru akan tersedia pada milestone berikutnya.</p>
    </div>
</div>
@endsection
