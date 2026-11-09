<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'E-Learning '.$school->fullName())</title>
    @include('layouts._head-assets')
</head>
<body class="app-body">
    @php
        $currentUser = auth()->user();
        $dashboardRoute = match ($currentUser?->role) {
            'admin' => 'admin.dashboard',
            'teacher' => 'teacher.dashboard',
            'student' => 'student.dashboard',
            default => 'login',
        };
        $profileUrl = match ($currentUser?->role) {
            'admin' => route('admin.profile.edit'),
            'teacher' => route('teacher.profile.edit'),
            'student' => route('student.profile.edit'),
            default => null,
        };
        $roleLabel = match ($currentUser?->role) {
            'admin' => 'Administrator',
            'teacher' => 'Guru',
            'student' => 'Siswa',
            default => '',
        };
    @endphp

    <div class="app-shell">
        <aside class="app-sidebar offcanvas-lg offcanvas-start" id="sidebarMenu" tabindex="-1" aria-label="Menu navigasi">
            <div class="app-sidebar__brand">
                <a href="{{ route($dashboardRoute) }}" class="app-brand">
                    <span class="app-brand__logo" aria-hidden="true">@if ($school->logoUrl())<img src="{{ $school->logoUrl() }}" alt="">@else<i class="bi bi-mortarboard-fill" aria-hidden="true"></i>@endif</span>
                    <span class="app-brand__text">
                        <span class="app-brand__name">{{ $school->name }}</span>
                        <span class="app-brand__tagline">{{ trim('E-Learning '.$school->city) }}</span>
                    </span>
                </a>
                <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Tutup menu"></button>
            </div>
            <nav class="app-sidebar__nav sidebar">
                @yield('sidebar')
            </nav>
        </aside>

        <div class="app-main">
            <header class="app-topbar topbar">
                <button class="app-topbar__toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-label="Buka menu">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <button class="app-topbar__toggle d-none d-lg-inline-flex" type="button" data-sidebar-toggle aria-label="Ciutkan menu" title="Ciutkan menu">
                    <i class="bi bi-layout-sidebar" aria-hidden="true"></i>
                </button>
                <a class="app-topbar__brand d-lg-none" href="{{ route($dashboardRoute) }}">{{ $school->name }}</a>

                <div class="app-topbar__actions">
                    <button class="app-icon-button" type="button" data-theme-toggle aria-label="Ganti tema terang/gelap" title="Ganti tema">
                        <i class="bi bi-moon-stars app-theme-icon--light" aria-hidden="true"></i>
                        <i class="bi bi-sun app-theme-icon--dark" aria-hidden="true"></i>
                    </button>
                    <div class="dropdown">
                        <button class="app-user-button" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="app-avatar" aria-hidden="true">{{ strtoupper(substr($currentUser->name ?? 'U', 0, 1)) }}</span>
                            <span class="app-user-button__text d-none d-md-flex">
                                <span class="app-user-button__name">{{ $currentUser->name ?? '' }}</span>
                                <span class="app-user-button__role">{{ $roleLabel }}</span>
                            </span>
                            <i class="bi bi-chevron-down app-user-button__chevron" aria-hidden="true"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @if ($profileUrl)
                                <li><a class="dropdown-item" href="{{ $profileUrl }}"><i class="bi bi-person me-2" aria-hidden="true"></i>Profil Saya</a></li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Keluar</button></form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="app-content">
                @hasSection('page-title')
                    <div class="page-header">
                        <div>
                            <h1 class="page-header__title">@yield('page-title')</h1>
                            <p class="page-header__description">@yield('page-description')</p>
                        </div>
                    </div>
                @endif
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="bi bi-exclamation-circle me-2" aria-hidden="true"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>
                @endif
                @if($errors->any() && ! View::hasSection('hide-error-summary'))
                    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                @yield('content')
            </main>

            <footer class="app-footer">
                &copy; {{ date('Y') }} {{ $school->fullName() }}
            </footer>
        </div>
    </div>
    @include('files._preview-modal')
    @stack('scripts')
</body>
</html>
