@extends('layouts.dashboard')

@section('sidebar')
<ul class="nav flex-column">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}" href="{{ route('teacher.dashboard') }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('teacher.subjects.*') ? 'active' : '' }}" href="{{ route('teacher.subjects.index') }}">
            <i class="bi bi-journal-bookmark"></i> Mata Pelajaran Saya
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('teacher.quizzes.*') ? 'active' : '' }}" href="{{ route('teacher.quizzes.index') }}">
            <i class="bi bi-patch-question"></i> Kuis
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('teacher.grades.*') ? 'active' : '' }}" href="{{ route('teacher.grades.index') }}">
            <i class="bi bi-award"></i> Penilaian
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('teacher.discussions.*') ? 'active' : '' }}" href="{{ route('teacher.discussions.index') }}">
            <i class="bi bi-chat-dots"></i> Diskusi Kelas
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('teacher.announcements.*') ? 'active' : '' }}" href="{{ route('teacher.announcements.index') }}">
            <i class="bi bi-megaphone"></i> Pengumuman
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('teacher.pdf-converter.*') ? 'active' : '' }}" href="{{ route('teacher.pdf-converter.index') }}">
            <i class="bi bi-file-earmark-pdf"></i> Konversi PDF
        </a>
    </li>
</ul>
@endsection
