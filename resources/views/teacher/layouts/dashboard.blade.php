@extends('layouts.dashboard')

@section('sidebar')
<ul class="nav flex-column">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}" href="{{ route('teacher.dashboard') }}">
            <i class="bi bi-speedometer2" aria-hidden="true"></i> <span class="nav-link__label">Dashboard</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('teacher.subjects.*') ? 'active' : '' }}" href="{{ route('teacher.subjects.index') }}">
            <i class="bi bi-journal-bookmark" aria-hidden="true"></i> <span class="nav-link__label">Mata Pelajaran Saya</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('teacher.quizzes.*') ? 'active' : '' }}" href="{{ route('teacher.quizzes.index') }}">
            <i class="bi bi-patch-question" aria-hidden="true"></i> <span class="nav-link__label">Kuis</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('teacher.grades.*') ? 'active' : '' }}" href="{{ route('teacher.grades.index') }}">
            <i class="bi bi-award" aria-hidden="true"></i> <span class="nav-link__label">Penilaian</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('teacher.discussions.*') ? 'active' : '' }}" href="{{ route('teacher.discussions.index') }}">
            <i class="bi bi-chat-dots" aria-hidden="true"></i> <span class="nav-link__label">Diskusi Kelas</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('teacher.announcements.*') ? 'active' : '' }}" href="{{ route('teacher.announcements.index') }}">
            <i class="bi bi-megaphone" aria-hidden="true"></i> <span class="nav-link__label">Pengumuman</span>
            @include('layouts._new-announcement-badge')
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('teacher.pdf-converter.*') ? 'active' : '' }}" href="{{ route('teacher.pdf-converter.index') }}">
            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> <span class="nav-link__label">Konversi PDF</span>
        </a>
    </li>
</ul>
@endsection
