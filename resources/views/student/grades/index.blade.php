@extends('student.layouts.dashboard')

@section('title', 'Nilai Saya')
@section('page-title', 'Nilai Saya')
@section('page-description', 'Rekap nilai dari penilaian yang dipublikasikan untuk Anda.')

@section('content')
<div class="card card-dashboard">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Rekap Nilai</h5>
        <span class="badge text-bg-light">{{ $classroom?->name ?? 'Kelas belum ditetapkan' }}</span>
    </div>
    <div class="card-body">
        @if ($subjects->isNotEmpty())
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach ($subjects as $subject)
                    <span class="badge text-bg-light">{{ $subject->code }} &middot; {{ $subject->name }}</span>
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Mata Pelajaran</th>
                        <th>Penilaian</th>
                        <th>Nilai</th>
                        <th>Umpan Balik</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="bi bi-award fs-1 d-block mb-2" aria-hidden="true"></i>
                            <span class="d-block mb-1">Belum ada nilai yang dipublikasikan.</span>
                            <span class="small">Nilai akan tampil di sini setelah guru menyelesaikan penilaian.</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
