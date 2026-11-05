@extends('layouts.dashboard')

@section('sidebar')
@php
    $menuSections = [
        'Utama' => [
            ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
        ],
        'Data Master' => [
            ['route' => 'admin.students.index', 'active' => 'admin.students.*', 'icon' => 'bi-person-badge', 'label' => 'Data Siswa'],
            ['route' => 'admin.teachers.index', 'active' => 'admin.teachers.*', 'icon' => 'bi-person-workspace', 'label' => 'Data Guru'],
            ['route' => 'admin.academic-years.index', 'active' => 'admin.academic-years.*', 'icon' => 'bi-calendar3', 'label' => 'Tahun Ajaran'],
            ['route' => 'admin.classes.index', 'active' => 'admin.classes.*', 'icon' => 'bi-grid', 'label' => 'Kelas'],
            ['route' => 'admin.subjects.index', 'active' => 'admin.subjects.*', 'icon' => 'bi-journal-bookmark', 'label' => 'Mata Pelajaran'],
        ],
        'Akademik' => [
            ['route' => 'admin.promotions.index', 'active' => 'admin.promotions.*', 'icon' => 'bi-arrow-up-right-circle', 'label' => 'Kenaikan Kelas'],
            ['route' => 'admin.announcements.index', 'active' => 'admin.announcements.*', 'icon' => 'bi-megaphone', 'label' => 'Pengumuman'],
            ['route' => 'admin.quizzes.index', 'active' => 'admin.quizzes.*', 'icon' => 'bi-patch-question', 'label' => 'Kuis'],
        ],
        'Laporan' => [
            ['route' => 'admin.grades.index', 'active' => 'admin.grades.*', 'icon' => 'bi-graph-up', 'label' => 'Rekap Nilai'],
            ['route' => 'admin.reports.teachers', 'active' => 'admin.reports.teachers*', 'icon' => 'bi-clipboard-data', 'label' => 'Laporan Guru'],
            ['route' => 'admin.activity-logs.index', 'active' => 'admin.activity-logs.*', 'icon' => 'bi-clock-history', 'label' => 'Log Aktivitas'],
        ],
        'Sistem' => [
            ['route' => 'admin.settings.edit', 'active' => 'admin.settings.*', 'icon' => 'bi-gear', 'label' => 'Pengaturan Sekolah'],
            ['route' => 'admin.backups.index', 'active' => 'admin.backups.*', 'icon' => 'bi-database', 'label' => 'Backup Database'],
        ],
    ];
@endphp
<ul class="nav flex-column">
    @foreach ($menuSections as $sectionLabel => $menuItems)
        <li class="nav-section">{{ $sectionLabel }}</li>
        @foreach ($menuItems as $menuItem)
            <li class="nav-item">
                @if ($menuItem['route'])
                    <a class="nav-link {{ request()->routeIs($menuItem['active']) ? 'active' : '' }}" href="{{ route($menuItem['route']) }}" @if (request()->routeIs($menuItem['active'])) aria-current="page" @endif>
                        <i class="bi {{ $menuItem['icon'] }}" aria-hidden="true"></i> {{ $menuItem['label'] }}
                        @if ($menuItem['route'] === 'admin.announcements.index')
                            @include('layouts._new-announcement-badge')
                        @endif
                    </a>
                @else
                    <a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true">
                        <i class="bi {{ $menuItem['icon'] }}" aria-hidden="true"></i> {{ $menuItem['label'] }}
                        <span class="badge text-bg-light ms-auto">Segera</span>
                    </a>
                @endif
            </li>
        @endforeach
    @endforeach
</ul>
@endsection
