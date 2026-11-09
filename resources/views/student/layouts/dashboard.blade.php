@extends('layouts.dashboard')

@section('sidebar')
<ul class="nav flex-column">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('student.dashboard') ? 'active' : '' }}" href="{{ route('student.dashboard') }}">
            <i class="bi bi-speedometer2" aria-hidden="true"></i> <span class="nav-link__label">Dashboard</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('student.subjects.*') ? 'active' : '' }}" href="{{ route('student.subjects.index') }}">
            <i class="bi bi-journal-code" aria-hidden="true"></i> <span class="nav-link__label">Mata Pelajaran Saya</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('student.quizzes.*') ? 'active' : '' }}" href="{{ route('student.quizzes.index') }}">
            <i class="bi bi-patch-question" aria-hidden="true"></i> <span class="nav-link__label">Kuis</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('student.grades.*') ? 'active' : '' }}" href="{{ route('student.grades.index') }}">
            <i class="bi bi-award" aria-hidden="true"></i> <span class="nav-link__label">Nilai Saya</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('student.discussions.*') ? 'active' : '' }}" href="{{ route('student.discussions.index') }}">
            <i class="bi bi-chat-dots" aria-hidden="true"></i> <span class="nav-link__label">Diskusi</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('student.announcements.*') ? 'active' : '' }}" href="{{ route('student.announcements.index') }}">
            <i class="bi bi-megaphone" aria-hidden="true"></i> <span class="nav-link__label">Pengumuman</span>
            @include('layouts._new-announcement-badge')
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('student.pdf-converter.*') ? 'active' : '' }}" href="{{ route('student.pdf-converter.index') }}">
            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> <span class="nav-link__label">Konversi PDF</span>
        </a>
    </li>
</ul>
@endsection
