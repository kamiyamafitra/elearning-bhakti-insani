<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'E-Learning SMK Bhakti Insani Bogor')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            font-weight: 400;
            line-height: 1.6;
        }
        h1, h2, h3, h4, h5, h6,
        .h1, .h2, .h3, .h4, .h5, .h6,
        .page-title, .section-title, .card-title {
            font-weight: 600;
            letter-spacing: -0.01em;
        }
        .navbar-brand,
        .sidebar .nav-link,
        .menu-label {
            font-weight: 600;
        }
        .btn-primary,
        .btn,
        button[type="submit"],
        .form-action,
        .action-label {
            font-weight: 600;
        }
        .table thead th,
        th {
            font-weight: 600;
        }
        label,
        .form-label {
            font-weight: 500;
        }
        .text-secondary,
        .text-muted,
        .supporting-text,
        .descriptive-text,
        .date,
        .timestamp,
        small {
            font-weight: 300;
        }
        .table td,
        .table tbody td,
        .content,
        p {
            font-weight: 400;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="#">E-Learning SMK Bhakti Insani Bogor</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    @auth
                        <li class="nav-item">
                            <span class="navbar-text me-3">{{ auth()->user()->name }} ({{ auth()->user()->role }})</span>
                        </li>
                        <li class="nav-item">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="btn btn-outline-light btn-sm" type="submit">Keluar</button>
                            </form>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <main class="container">
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>

    <footer class="text-center py-4 mt-5 text-muted">
        <small>&copy; {{ date('Y') }} SMK Bhakti Insani Bogor</small>
    </footer>
</body>
</html>
