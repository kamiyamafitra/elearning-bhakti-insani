@extends('layouts.dashboard')

@section('sidebar')
<ul class="nav flex-column">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('student.dashboard') ? 'active' : '' }}" href="{{ route('student.dashboard') }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('student.subjects.*') ? 'active' : '' }}" href="{{ route('student.subjects.index') }}">
            <i class="bi bi-journal-code"></i> Mata Pelajaran Saya
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('student.quizzes.*') ? 'active' : '' }}" href="{{ route('student.quizzes.index') }}">
            <i class="bi bi-patch-question"></i> Kuis
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('student.grades.*') ? 'active' : '' }}" href="{{ route('student.grades.index') }}">
            <i class="bi bi-award"></i> Nilai Saya
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('student.discussions.*') ? 'active' : '' }}" href="{{ route('student.discussions.index') }}">
            <i class="bi bi-chat-dots"></i> Diskusi
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('student.announcements.*') ? 'active' : '' }}" href="{{ route('student.announcements.index') }}">
            <i class="bi bi-megaphone"></i> Pengumuman
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('student.pdf-converter.*') ? 'active' : '' }}" href="{{ route('student.pdf-converter.index') }}">
            <i class="bi bi-file-earmark-pdf"></i> Konversi PDF
        </a>
    </li>
</ul>
@endsection
