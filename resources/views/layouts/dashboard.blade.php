<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'E-Learning SMK Bhakti Insani Bogor')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --sb-primary:#2563EB;--sb-primary-dark:#1E3A8A;--sb-primary-navy:#1E40AF;--sb-primary-light:#DBEAFE;--sb-bg:#EFF6FF;--sb-surface:#FFFFFF;--sb-text:#1E293B;--sb-text-secondary:#64748B;--sb-border:#DBEAFE;
        }
        body{font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,Cantarell,'Open Sans','Helvetica Neue',sans-serif;background:var(--sb-bg);color:var(--sb-text);font-weight:400;line-height:1.6}
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
        small,
        .form-text {
            font-weight: 300;
        }
        .table td,
        .table tbody td,
        .content,
        p {
            font-weight: 400;
        }
        .topbar{background:var(--sb-surface);border-bottom:1px solid var(--sb-border);box-shadow:0 1px 2px rgba(30,58,138,.02);z-index:1030}
        .sidebar{background:var(--sb-primary-dark);min-height:calc(100vh - 56px)}
        .sidebar .nav-link{color:rgba(255,255,255,.92);border-radius:.5rem;margin:.25rem .75rem;display:flex;align-items:center;gap:.625rem;padding:.625rem .875rem;text-decoration:none}
        .sidebar .nav-link i{color:rgba(219,234,254,.9);font-size:1.05rem}
        .sidebar .nav-link:hover{background:rgba(37,99,235,.9);color:#fff}
        .sidebar .nav-link.active{background:var(--sb-primary);color:#fff;font-weight:600;box-shadow:inset 3px 0 0 rgba(219,234,254,.9)}
        .sidebar .nav-link.active i{color:#fff}
        .card-dashboard{background:var(--sb-surface);border:1px solid var(--sb-border);box-shadow:0 10px 30px rgba(30,58,138,.04);border-radius:.875rem}
        .card-dashboard .card-header{background:transparent;border-bottom:1px solid var(--sb-border)}
        .stat-icon{width:52px;height:52px;border-radius:.875rem;display:flex;align-items:center;justify-content:center;background:var(--sb-primary-light);color:var(--sb-primary)}
        .btn-primary{background:var(--sb-primary);border-color:var(--sb-primary)}
        .btn-primary:hover{background:var(--sb-primary-navy);border-color:var(--sb-primary-navy)}
        .btn-outline-primary{color:var(--sb-primary);border-color:rgba(37,99,235,.4)}
        .btn-outline-primary:hover{background:var(--sb-primary-light);color:var(--sb-primary-navy);border-color:var(--sb-primary)}
        .table thead.table-light th{background:linear-gradient(90deg,rgba(219,234,254,.7) 0%,rgba(239,246,255,.9) 100%);color:var(--sb-primary-dark);border-bottom:1px solid var(--sb-border)}
        .table-hover tbody tr:hover{background:rgba(239,246,255,.8)}
        .badge.text-bg-light{background:var(--sb-primary-light)!important;color:var(--sb-primary-navy)!important}
        .badge.text-bg-primary{background:var(--sb-primary)!important}
        .form-control:focus,.form-select:focus{border-color:rgba(37,99,235,.6);box-shadow:0 0 0 .25rem rgba(37,99,235,.08)}
        .nav-tabs .nav-link.active{color:var(--sb-primary);border-color:var(--sb-border) var(--sb-border) #fff}
        .nav-tabs .nav-link{color:var(--sb-text-secondary)}
        footer{background:var(--sb-surface);border-top:1px solid var(--sb-border)}
    </style>
</head>
<body>
    <header class="topbar sticky-top">
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid">
                <button class="btn btn-link text-dark d-lg-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu"><i class="bi bi-list fs-4"></i></button>
                <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                    <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width:38px;height:38px;background:linear-gradient(135deg,#2563EB 0%,#1E40AF 100%);"><i class="bi bi-mortarboard-fill text-white"></i></span>
                    <span class="fw-semibold" style="color: var(--sb-primary-dark);">SMK BHAKTI INSANI BOGOR</span>
                </a>
                <div class="ms-auto d-flex align-items-center gap-3">
                    <button class="btn btn-light position-relative border-0" style="background:var(--sb-primary-light);"><i class="bi bi-bell" style="color:var(--sb-primary-navy);"></i><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">0</span></button>
                    <div class="dropdown">
                        <button class="btn dropdown-toggle d-flex align-items-center gap-2 border-0" data-bs-toggle="dropdown" style="background:var(--sb-primary-light);">
                            <span class="rounded-circle text-white d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;background:linear-gradient(135deg,#2563EB 0%,#1E40AF 100%);">{{ strtoupper(substr(auth()->user()->name ?? 'U',0,1)) }}</span>
                            <div class="text-start d-none d-md-block">
                                <div class="fw-semibold small">{{ auth()->user()->name ?? '' }}</div>
                                <div class="small text-capitalize" style="color:var(--sb-text-secondary);">{{ auth()->user()->role ?? '' }}</div>
                            </div>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profil</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Pengaturan Akun</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button></form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <div class="container-fluid">
        <div class="row">
            <aside class="col-lg-2 col-md-3 d-lg-block collapse sidebar px-0" id="sidebarMenu">
                <div class="pt-3">@yield('sidebar')</div>
            </aside>
            <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4 py-4">
                <div class="mb-4">
                    <h1 class="h3 mb-1">@yield('page-title')</h1>
                    <p class="mb-0" style="color:var(--sb-text-secondary);">@yield('page-description')</p>
                </div>
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <footer class="py-3 mt-4">
        <div class="container-fluid text-center" style="color:var(--sb-text-secondary);">
            <small>&copy; {{ date('Y') }} SMK Bhakti Insani Bogor</small>
        </div>
    </footer>
</body>
</html>
