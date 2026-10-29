<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Masuk ke E-Learning SMK Bhakti Insani Bogor sebagai siswa, guru, atau administrator.">
    <title>Masuk - E-Learning SMK Bhakti Insani Bogor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page">
    <main class="auth-shell">
        <div class="auth-card">
            <aside class="auth-aside">
                <div class="auth-brand">
                    <span class="auth-brand__icon">
                        <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
                    </span>
                    <span class="auth-brand__text">
                        <strong>SMK Bhakti Insani</strong>
                        <small>Bogor</small>
                    </span>
                </div>

                <div class="auth-aside__body">
                    <span class="auth-aside__eyebrow">E-Learning</span>
                    <h1 class="auth-aside__title">Belajar tanpa batas,<br>untuk masa depan.</h1>
                    <p class="auth-aside__description">
                        Satu tempat untuk materi, tugas, jadwal, dan informasi sekolah.
                        Masuk dengan akun yang diberikan oleh administrator.
                    </p>

                    <ul class="auth-benefits">
                        <li>
                            <i class="bi bi-check2-circle" aria-hidden="true"></i>
                            <span>Materi dan tugas dalam satu platform</span>
                        </li>
                        <li>
                            <i class="bi bi-check2-circle" aria-hidden="true"></i>
                            <span>Akses sesuai peran siswa, guru, dan admin</span>
                        </li>
                        <li>
                            <i class="bi bi-check2-circle" aria-hidden="true"></i>
                            <span>Dapat digunakan di komputer maupun ponsel</span>
                        </li>
                    </ul>
                </div>

                <div class="auth-aside__footer">
                    <i class="bi bi-shield-lock" aria-hidden="true"></i>
                    <span>Koneksi aman &mdash; kata sandi tidak pernah dibagikan.</span>
                </div>
            </aside>

            <section class="auth-main" aria-labelledby="auth-title">
                <div class="auth-main__inner">
                    <header class="auth-header">
                        <span class="auth-header__badge">
                            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                            Masuk ke akun
                        </span>
                        <h2 class="auth-header__title" id="auth-title">Selamat datang kembali</h2>
                        <p class="auth-header__description">
                            Masukkan akun Anda. Jenis akun dikenali otomatis oleh sistem.
                        </p>
                    </header>

                    @if (session('status'))
                        <div class="alert alert-success auth-alert" role="status">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger auth-alert" role="alert">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger auth-alert" role="alert">
                            <div class="d-flex gap-2">
                                <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
                                <div>
                                    <strong class="d-block">Tidak dapat masuk.</strong>
                                    <ul class="mb-0 ps-3">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="auth-form">
                        @csrf

                        <div class="auth-field">
                            <label class="form-label" for="username">Username (NIS / NIP)</label>
                            <div class="input-group auth-input">
                                <span class="input-group-text">
                                    <i class="bi bi-person-badge" aria-hidden="true"></i>
                                </span>
                                <input type="text"
                                    id="username"
                                    name="username"
                                    class="form-control @error('username') is-invalid @enderror"
                                    value="{{ old('username') }}"
                                    placeholder="Masukkan NIS, NIP, atau username administrator"
                                    autocomplete="username"
                                    maxlength="255"
                                    required
                                    autofocus>
                            </div>
                            @error('username')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="auth-field">
                            <label class="form-label" for="password">Kata Sandi</label>
                            <div class="input-group auth-input">
                                <span class="input-group-text">
                                    <i class="bi bi-lock" aria-hidden="true"></i>
                                </span>
                                <input type="password"
                                    id="password"
                                    name="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    placeholder="Masukkan kata sandi"
                                    autocomplete="current-password"
                                    required>
                                <button class="btn btn-outline-secondary auth-password-toggle"
                                    type="button"
                                    data-password-toggle="#password"
                                    aria-label="Tampilkan kata sandi"
                                    aria-pressed="false">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="auth-guidance">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            <div>
                                <strong class="d-block">Gunakan identitas akun Anda</strong>
                                <span>Siswa memakai NIS, guru memakai NIP, dan administrator memakai username yang terdaftar.</span>
                            </div>
                        </div>

                        <div class="form-check auth-remember">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                            <label class="form-check-label" for="remember">Ingat saya di perangkat ini</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 auth-submit">
                            <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>
                            Masuk
                        </button>
                    </form>

                    <p class="auth-help">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        Lupa kata sandi? Hubungi administrator sekolah untuk pengaturan ulang.
                    </p>
                </div>
            </section>
        </div>

        <p class="auth-copyright">
            &copy; {{ date('Y') }} SMK Bhakti Insani Bogor. Seluruh hak cipta dilindungi.
        </p>
    </main>
</body>
</html>
