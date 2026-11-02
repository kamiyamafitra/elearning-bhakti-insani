@extends('admin.layouts.dashboard')

@section('title', 'Tetapkan Siswa ke Kelas')

@section('content')
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Tetapkan Siswa</h1>
        <p class="mb-0" style="color: var(--sb-text-secondary);">{{ $class->name }} &middot; {{ $class->academicYear?->name ?? '-' }}</p>
    </div>
    <a href="{{ route('admin.classes.show', $class) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Siswa di Kelas Ini</h5>
                <span class="badge text-bg-light">{{ $assignedStudents->count() }} Siswa</span>
            </div>
            <div class="card-body">
                @if ($assignedStudents->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-person-x fs-1" aria-hidden="true"></i>
                        <p class="mt-2 mb-0">Belum ada siswa di kelas ini.</p>
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($assignedStudents->sortBy(fn ($student) => $student->user?->name) as $student)
                            <li class="list-group-item px-0 d-flex align-items-center gap-2">
                                <span class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                    <span class="small fw-semibold">{{ $student->user?->initials ?? '?' }}</span>
                                </span>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-break">{{ $student->user?->name ?? 'Tanpa Nama' }}</div>
                                    <div class="text-muted small">NIS {{ $student->nis ?: '-' }}</div>
                                </div>
                                <a href="{{ route('admin.students.show', $student) }}" class="btn btn-sm btn-outline-primary" aria-label="Lihat {{ $student->user?->name }}">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Tambahkan Siswa</h5>
            </div>
            <div class="card-body">
                @if ($students->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-check2-circle fs-1" aria-hidden="true"></i>
                        <p class="mt-2 mb-0">Semua siswa sudah berada di kelas ini.</p>
                    </div>
                @else
                    <form method="POST" action="{{ route('admin.classes.assign-students.store', $class) }}">
                        @csrf
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" class="form-check-input" id="select-all-students" aria-label="Pilih semua siswa">
                                        </th>
                                        <th>Siswa</th>
                                        <th>Kelas Saat Ini</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $student)
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input student-checkbox" name="student_profile_ids[]" value="{{ $student->id }}" id="student-{{ $student->id }}">
                                            </td>
                                            <td>
                                                <label class="form-check-label" for="student-{{ $student->id }}">
                                                    <span class="fw-semibold text-break">{{ $student->user?->name ?? 'Tanpa Nama' }}</span>
                                                    <span class="text-muted small d-block">NIS {{ $student->nis ?: '-' }}</span>
                                                </label>
                                            </td>
                                            <td>{{ $student->class?->name ?? 'Belum ada kelas' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @error('student_profile_ids')
                            <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror

                        <div class="mt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-plus me-1" aria-hidden="true"></i> Tetapkan ke Kelas
                            </button>
                            <a href="{{ route('admin.classes.show', $class) }}" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('select-all-students');
        if (!selectAll) {
            return;
        }

        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.student-checkbox').forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
        });
    });
</script>
@endpush
