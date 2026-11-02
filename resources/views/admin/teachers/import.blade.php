@extends('admin.layouts.dashboard')

@section('title', 'Impor Data Guru')

@section('content')
    @include('admin.partials.import-page', [
        'heading' => 'Impor Data Guru',
        'description' => 'Tambahkan banyak guru sekaligus dari file Excel atau CSV.',
        'indexRoute' => 'admin.teachers.index',
        'templateRoute' => 'admin.teachers.import.template',
        'storeRoute' => 'admin.teachers.import.store',
        'columnGuide' => [
            ['nama', true, 'Nama lengkap guru beserta gelar.'],
            ['nip', true, 'Nomor Induk Pegawai. Dipakai untuk login dan sebagai password awal.'],
            ['email', false, "Jika kosong, dibuat otomatis: NIP@{$emailDomain}."],
            ['telepon', false, 'Nomor telepon guru.'],
            ['alamat', false, 'Alamat tempat tinggal.'],
        ],
        'notes' => [
            'Jika ada satu baris yang salah, tidak ada data yang disimpan. Perbaiki semua kesalahan yang ditampilkan lalu unggah ulang.',
            'NIP dan email tidak boleh sama dengan data yang sudah terdaftar atau dengan baris lain di file.',
            'Format kolom NIP sebagai Teks di Excel agar angka panjang tidak berubah menjadi format ilmiah (1,98E+17). Template sudah diatur seperti itu.',
            'Guru masuk menggunakan NIP dengan password NIP, lalu wajib membuat password baru saat pertama kali masuk.',
        ],
    ])
@endsection
