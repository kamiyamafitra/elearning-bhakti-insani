@extends('admin.layouts.dashboard')

@section('title', 'Impor Data Siswa')

@section('content')
    @include('admin.partials.import-page', [
        'heading' => 'Impor Data Siswa',
        'description' => 'Tambahkan banyak siswa sekaligus dari file Excel atau CSV.',
        'indexRoute' => 'admin.students.index',
        'templateRoute' => 'admin.students.import.template',
        'storeRoute' => 'admin.students.import.store',
        'columnGuide' => [
            ['nama', true, 'Nama lengkap siswa.'],
            ['nis', true, 'Nomor Induk Siswa. Dipakai untuk login dan sebagai password awal.'],
            ['nisn', false, 'Nomor Induk Siswa Nasional.'],
            ['kelas', false, 'Kode kelas (misalnya X-TKJ-1) atau nama kelas yang sudah terdaftar.'],
            ['jenis_kelamin', false, 'L atau P.'],
            ['tanggal_lahir', false, 'Format 2008-01-13 atau 13/01/2008.'],
            ['email', false, "Jika kosong, dibuat otomatis: NIS@{$emailDomain}."],
            ['telepon', false, 'Nomor telepon siswa.'],
            ['alamat', false, 'Alamat tempat tinggal.'],
            ['nama_orang_tua', false, 'Nama orang tua atau wali.'],
            ['telepon_orang_tua', false, 'Nomor telepon orang tua atau wali.'],
        ],
        'notes' => [
            'Jika ada satu baris yang salah, tidak ada data yang disimpan. Perbaiki semua kesalahan yang ditampilkan lalu unggah ulang.',
            'NIS, NISN, dan email tidak boleh sama dengan data yang sudah terdaftar atau dengan baris lain di file.',
            'Format kolom NIS dan NISN sebagai Teks di Excel agar angka 0 di depan tidak hilang. Template sudah diatur seperti itu.',
            'Siswa masuk menggunakan NIS dengan password NIS, lalu wajib membuat password baru saat pertama kali masuk.',
        ],
    ])
@endsection
