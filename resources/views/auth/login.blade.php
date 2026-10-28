<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - E-Learning SMK Bhakti Insani Bogor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: radial-gradient(circle at top, #f8fbff, #f4f7fb);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 400;
            line-height: 1.6;
        }
        h1, h2, h3, h4, h5, h6,
        .h1, .h2, .h3, .h4, .h5, .h6 {
            font-weight: 600;
            letter-spacing: -0.01em;
        }
        .btn-primary,
        .btn,
        button[type="submit"] {
            font-weight: 600;
        }
        label,
        .form-label {
            font-weight: 500;
        }
        .text-muted {
            font-weight: 300;
        }
        p {
            font-weight: 400;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-mortarboard-fill text-white fs-3"></i>
                    </div>
                    <h4 class="mb-1">SMK BHAKTI INSANI BOGOR</h4>
                    <p class="text-muted">Sistem Pembelajaran Elektronik</p>
                </div>
                <div class="card card-dashboard border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="mb-3 text-center">Masuk ke Sistem</h5>
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if (session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif
                        <form method="POST" action="{{ route('login') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="email" class="form-label">Surel</label>
                                <input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Kata Sandi</label>
                                <input id="password" type="password" class="form-control" name="password" required>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                                <label class="form-check-label" for="remember">Ingat Saya</label>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Masuk</button>
                        </form>
                    </div>
                </div>
                <div class="text-center text-muted mt-3">
                    <small>© {{ date('Y') }} SMK Bhakti Insani Bogor</small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
