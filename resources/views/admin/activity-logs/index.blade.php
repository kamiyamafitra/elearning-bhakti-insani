@extends('admin.layouts.dashboard')

@section('title', 'Log Aktivitas')

@section('content')
@php
    $actionBadges = [
        'login' => 'text-bg-success',
        'login_failed' => 'text-bg-danger',
        'logout' => 'text-bg-secondary',
        'created' => 'text-bg-primary',
        'updated' => 'text-bg-warning',
        'deleted' => 'text-bg-danger',
        'bulk' => 'text-bg-primary',
    ];
    $roleLabels = ['admin' => 'Administrator', 'teacher' => 'Guru', 'student' => 'Siswa'];
    $hasFilters = $filters['search'] !== '' || $filters['action'] || $filters['from'] || $filters['to'];
@endphp

<div class="directory-page">
    <section class="directory-hero">
        <div class="directory-hero__content">
            <div class="directory-hero__heading">
                <h1 class="directory-hero__title">Log Aktivitas</h1>
                <span class="directory-count-badge">
                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                    {{ $todayCount }} hari ini
                </span>
            </div>
            <p class="directory-hero__description">
                Riwayat masuk, keluar, dan perubahan data oleh pengguna. Log disimpan selama {{ \App\Models\ActivityLog::RETENTION_DAYS }} hari.
            </p>
        </div>
    </section>

    @if ($failedLoginsToday > 0)
        <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="alert">
            <span><i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i> Ada {{ $failedLoginsToday }} percobaan masuk yang gagal hari ini.</span>
            <a href="{{ route('admin.activity-logs.index', ['action' => 'login_failed', 'from' => today()->format('Y-m-d')]) }}" class="btn btn-sm btn-outline-secondary">Lihat</a>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="directory-toolbar" role="search">
        <div class="directory-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" name="q" class="form-control" placeholder="Cari nama pengguna atau aktivitas..." value="{{ $filters['search'] }}" aria-label="Cari log aktivitas">
        </div>

        <div class="directory-filter">
            <label for="action" class="directory-filter__label">
                <i class="bi bi-funnel" aria-hidden="true"></i>
                Jenis
            </label>
            <select name="action" id="action" class="form-select">
                <option value="">Semua</option>
                @foreach ($actionLabels as $actionValue => $actionLabel)
                    <option value="{{ $actionValue }}" @selected($filters['action'] === $actionValue)>{{ $actionLabel }}</option>
                @endforeach
            </select>
        </div>

        <div class="directory-filter">
            <label for="from" class="directory-filter__label">Dari</label>
            <input type="date" name="from" id="from" class="form-control" value="{{ $filters['from'] }}">
        </div>

        <div class="directory-filter">
            <label for="to" class="directory-filter__label">Sampai</label>
            <input type="date" name="to" id="to" class="form-control" value="{{ $filters['to'] }}">
        </div>

        <button class="btn btn-primary directory-toolbar__button" type="submit">Terapkan</button>

        @if ($hasFilters)
            <a href="{{ route('admin.activity-logs.index') }}" class="btn btn-light directory-toolbar__reset">Reset</a>
        @endif
    </form>

    <div class="directory-table-card">
        @if ($logs->isEmpty())
            <div class="directory-empty">
                <div class="directory-empty__icon"><i class="bi bi-clock-history" aria-hidden="true"></i></div>
                <h2 class="directory-empty__title">{{ $hasFilters ? 'Tidak ada log yang cocok.' : 'Belum ada aktivitas tercatat.' }}</h2>
                <p class="directory-empty__description">Aktivitas masuk, keluar, dan perubahan data akan muncul di sini.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="directory-table">
                    <thead>
                        <tr>
                            <th scope="col">Waktu</th>
                            <th scope="col">Pengguna</th>
                            <th scope="col">Jenis</th>
                            <th scope="col">Aktivitas</th>
                            <th scope="col">Alamat IP</th>
                            <th scope="col" class="text-end"><span class="visually-hidden">Rincian</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            @php
                                $changes = $log->properties['changes'] ?? [];
                                $otherProperties = collect($log->properties ?? [])->except('changes');
                                $hasDetails = $changes !== [] || $otherProperties->isNotEmpty() || $log->user_agent;
                            @endphp
                            <tr>
                                <td class="text-nowrap">
                                    <div>{{ $log->created_at->format('d-m-Y H:i') }}</div>
                                    <div class="small text-muted">{{ $log->created_at->locale('id')->diffForHumans() }}</div>
                                </td>
                                <td>
                                    @if ($log->user)
                                        <div class="fw-semibold">{{ $log->user->name }}</div>
                                        <div class="small text-muted">{{ $roleLabels[$log->user->role] ?? $log->user->role }}</div>
                                    @else
                                        <span class="text-muted">{{ $log->action === 'login_failed' ? 'Tidak dikenal' : 'Sistem' }}</span>
                                    @endif
                                </td>
                                <td><span class="badge {{ $actionBadges[$log->action] ?? 'text-bg-light' }}">{{ $log->actionLabel() }}</span></td>
                                <td class="text-break">{{ $log->description }}</td>
                                <td class="text-nowrap small text-muted">{{ $log->ip_address ?? '-' }}</td>
                                <td class="text-end">
                                    @if ($hasDetails)
                                        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#log-{{ $log->id }}" aria-expanded="false" aria-controls="log-{{ $log->id }}">
                                            Rincian
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @if ($hasDetails)
                                <tr class="collapse" id="log-{{ $log->id }}">
                                    <td colspan="6" class="bg-light small">
                                        @if ($changes !== [])
                                            <table class="table table-sm mb-2 bg-transparent">
                                                <thead>
                                                    <tr>
                                                        <th scope="col">Kolom</th>
                                                        <th scope="col">Sebelum</th>
                                                        <th scope="col">Sesudah</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($changes as $attribute => [$before, $after])
                                                        <tr>
                                                            <td><code>{{ $attribute }}</code></td>
                                                            <td class="text-break">{{ is_scalar($before) ? Str::limit((string) $before, 120) : json_encode($before) }}</td>
                                                            <td class="text-break">{{ is_scalar($after) ? Str::limit((string) $after, 120) : json_encode($after) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @endif
                                        @if ($otherProperties->has('password_changed'))
                                            <div class="mb-1"><i class="bi bi-key me-1" aria-hidden="true"></i>Password diubah.</div>
                                        @endif
                                        @foreach ($otherProperties->except('password_changed') as $key => $value)
                                            <div class="mb-1 text-break"><span class="text-muted">{{ $key }}:</span> {{ is_scalar($value) ? $value : Str::limit(json_encode($value), 300) }}</div>
                                        @endforeach
                                        @if ($log->user_agent)
                                            <div class="text-muted text-break">Peramban: {{ $log->user_agent }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="directory-pagination p-3">
                    {{ $logs->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
