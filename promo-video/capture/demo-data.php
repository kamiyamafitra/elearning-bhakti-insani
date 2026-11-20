<?php

// Fills the throwaway SQLite demo database with realistic-looking classroom data for the promo screenshots.
// Run with DB_CONNECTION=sqlite pointing at promo-video/capture/demo.sqlite (never the real database).

use App\Models\{AcademicYear, Announcement, Assignment, AssignmentSubmission, Classes, ClassMembership, ClassSchedule,
    Discussion, DiscussionReply, LearningMaterial, Quiz, QuizAnswer, QuizAttempt, QuizOption, QuizQuestion,
    StudentProfile, Subject, TeacherProfile, TeachingAssignment, User};
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (config('database.default') !== 'sqlite') {
    exit("Refusing to run: not on the sqlite demo database.\n");
}

$now = now();
$year = AcademicYear::first();
$class = Classes::first();
$teacher = User::where('username', 'guru')->first();
$teacher->update(['name' => 'Bu Rina Kartika, S.Kom.']);
$student = User::where('username', 'siswa')->first();
$student->update(['name' => 'Raka Pratama']);

$subjects = collect([
    ['PKK', 'Produk Kreatif dan Kewirausahaan'],
    ['ASJ', 'Administrasi Sistem Jaringan'],
    ['TLJ', 'Teknologi Layanan Jaringan'],
    ['INF', 'Informatika'],
])->map(fn ($s) => Subject::firstOrCreate(['code' => $s[0]], ['name' => $s[1], 'description' => 'Mata pelajaran '.$s[1]]));

foreach ($subjects as $i => $subject) {
    TeachingAssignment::firstOrCreate(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id]);
    ClassSchedule::firstOrCreate(['subject_id' => $subject->id, 'class_id' => $class->id, 'day_of_week' => $i + 1, 'start_time' => '07:30', 'end_time' => '09:00']);
    ClassSchedule::firstOrCreate(['subject_id' => $subject->id, 'class_id' => $class->id, 'day_of_week' => (($i + 2) % 5) + 1, 'start_time' => '10:00', 'end_time' => '11:30']);
}

ClassMembership::firstOrCreate(['academic_year_id' => $year->id, 'class_id' => $class->id, 'student_id' => $student->id]);

$names = ['Aulia Rahmawati', 'Bagas Saputra', 'Citra Lestari', 'Dimas Aditya', 'Eka Wulandari', 'Fajar Nugroho', 'Gita Permata', 'Hendra Wijaya', 'Intan Maharani', 'Joko Susilo', 'Kirana Putri', 'Lutfi Hakim'];
$students = collect([$student]);
foreach ($names as $n => $name) {
    $u = User::firstOrCreate(['username' => 'siswa'.($n + 2)], ['name' => $name, 'email' => 'siswa'.($n + 2).'@demo.test', 'password' => Hash::make('siswa123'), 'role' => 'student', 'is_active' => true]);
    StudentProfile::firstOrCreate(['user_id' => $u->id], ['nisn' => '00512345'.str_pad($n + 10, 2, '0', STR_PAD_LEFT), 'nis' => (string) (12346 + $n), 'class_id' => $class->id, 'gender' => $n % 2 ? 'L' : 'P']);
    ClassMembership::firstOrCreate(['academic_year_id' => $year->id, 'class_id' => $class->id, 'student_id' => $u->id]);
    $students->push($u);
}

[$pkk, $asj, $tlj, $inf] = $subjects->all();

// A small real PDF so the in-browser file preview has something to show.
$pdf = '';


$files = [
    'pdf' => ['modul-demo.pdf', 'Modul-3-Subnetting.pdf', 'application/pdf'],
    'docx' => ['lks-dhcp.docx', 'LKS-Praktikum-DHCP.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'xlsx' => ['tabel-subnet.xlsx', 'Tabel-Subnet.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
];
foreach ($files as $key => [$src]) {
    Storage::put("demo-promo/{$src}", file_get_contents(__DIR__.'/'.$src));
}

$materials = [
    [$asj, 'Modul 3 — Subnetting IPv4', 'Materi lengkap subnetting beserta contoh soal dan pembahasan.', 'pdf'],
    [$asj, 'Konfigurasi Router MikroTik', 'Langkah konfigurasi dasar router untuk jaringan sekolah.', null],
    [$tlj, 'Layanan DNS & DHCP Server', 'Penjelasan layanan jaringan dan praktik instalasi.', null],
    [$pkk, 'Merancang Business Model Canvas', 'Panduan menyusun BMC untuk produk kreatif.', null],
    [$inf, 'Algoritma & Pemrograman Dasar', 'Pengantar logika algoritma dan flowchart.', null],
    [$asj, 'LKS Praktikum DHCP Server', 'Lembar kerja praktikum, kerjakan sesuai langkah.', 'docx'],
    [$asj, 'Tabel Cepat Subnetting', 'Tabel prefix, subnet mask, dan jumlah host.', 'xlsx'],
];
foreach ($materials as $k => [$subj, $title, $desc, $type]) {
    $f = $type ? $files[$type] : null;
    LearningMaterial::firstOrCreate(['title' => $title], [
        'subject_id' => $subj->id, 'class_id' => $class->id, 'teacher_id' => $teacher->id, 'description' => $desc,
        'file_path' => $f ? "demo-promo/{$f[0]}" : null,
        'file_original_name' => $f[1] ?? null,
        'file_mime' => $f[2] ?? null,
        'file_size' => $f ? filesize(__DIR__.'/'.$f[0]) : null,
        'external_url' => $f ? null : 'https://id.wikipedia.org/wiki/Jaringan_komputer',
        'is_published' => true, 'published_at' => $now->copy()->subDays(6 - $k),
    ]);
}

$assignments = [
    [$asj, 'Praktikum Subnetting Kelas C', 3, 'published'],
    [$tlj, 'Laporan Instalasi DHCP Server', 5, 'published'],
    [$pkk, 'Proposal Usaha Produk Kreatif', 9, 'published'],
    [$inf, 'Membuat Flowchart Kasir Sederhana', -2, 'published'],
];
foreach ($assignments as $k => [$subj, $title, $dueDays, $status]) {
    $a = Assignment::firstOrCreate(['title' => $title], [
        'subject_id' => $subj->id, 'class_id' => $class->id, 'teacher_id' => $teacher->id,
        'instructions' => "Kerjakan sesuai petunjuk pada modul. Unggah hasil dalam format PDF atau tuliskan jawaban langsung.",
        'due_at' => $now->copy()->addDays($dueDays)->setTime(23, 59), 'max_score' => 100, 'status' => $status,
        'allows_text_response' => true, 'allows_resubmission' => true, 'published_at' => $now->copy()->subDays(7),
    ]);
    $count = [9, 6, 3, 12][$k];
    foreach ($students->slice(1, $count) as $j => $s) {
        $graded = $k === 3 || ($k === 0 && $j < 5);
        AssignmentSubmission::firstOrCreate(['assignment_id' => $a->id, 'student_id' => $s->id], [
            'text_response' => 'Jawaban terlampir sesuai instruksi.', 'submitted_at' => $now->copy()->subHours(30 - $j * 2),
            'score' => $graded ? [92, 85, 78, 88, 95, 81, 90, 76, 84, 89, 93, 80][$j % 12] : null,
            'feedback' => $graded ? 'Bagus, perhatikan penulisan prefix.' : null,
            'graded_at' => $graded ? $now->copy()->subHours(5) : null, 'graded_by' => $graded ? $teacher->id : null,
        ]);
    }
    if ($k === 3) {
        AssignmentSubmission::firstOrCreate(['assignment_id' => $a->id, 'student_id' => $student->id], [
            'text_response' => 'Flowchart terlampir.', 'submitted_at' => $now->copy()->subDays(3), 'score' => 90,
            'feedback' => 'Alur logika sudah tepat. Pertahankan!', 'graded_at' => $now->copy()->subDays(1), 'graded_by' => $teacher->id,
        ]);
    }
}

$quiz = Quiz::firstOrCreate(['title' => 'Kuis Harian: Dasar Subnetting'], [
    'subject_id' => $asj->id, 'class_id' => $class->id, 'teacher_id' => $teacher->id,
    'instructions' => 'Pilih satu jawaban yang paling tepat. Waktu berjalan otomatis setelah kuis dimulai.',
    'opens_at' => $now->copy()->subDay(), 'closes_at' => $now->copy()->addDays(4), 'duration_minutes' => 20, 'shuffles' => true, 'status' => 'published',
]);
$questions = [
    ['Berapa jumlah host yang dapat digunakan pada prefix /26?', ['62', '64', '30', '126'], 0],
    ['Subnet mask untuk prefix /27 adalah ...', ['255.255.255.224', '255.255.255.192', '255.255.255.240', '255.255.255.0'], 0],
    ['Alamat broadcast dari 192.168.10.0/28 adalah ...', ['192.168.10.15', '192.168.10.16', '192.168.10.255', '192.168.10.14'], 0],
    ['Kelas IP 172.16.0.1 termasuk kelas ...', ['B', 'A', 'C', 'D'], 0],
    ['Protokol yang membagikan IP otomatis adalah ...', ['DHCP', 'DNS', 'FTP', 'SMTP'], 0],
];
foreach ($questions as $p => [$text, $opts, $correct]) {
    $q = QuizQuestion::firstOrCreate(['quiz_id' => $quiz->id, 'position' => $p + 1], ['question' => $text]);
    foreach ($opts as $o => $optText) {
        QuizOption::firstOrCreate(['quiz_question_id' => $q->id, 'position' => $o + 1], ['text' => $optText, 'is_correct' => $o === $correct]);
    }
}
Quiz::firstOrCreate(['title' => 'Kuis Bab 2: Layanan Jaringan'], [
    'subject_id' => $tlj->id, 'class_id' => $class->id, 'teacher_id' => $teacher->id, 'instructions' => 'Kerjakan dengan jujur.',
    'opens_at' => $now->copy()->addDays(2), 'closes_at' => $now->copy()->addDays(6), 'duration_minutes' => 30, 'shuffles' => true, 'status' => 'published',
]);

// Finished attempts from classmates so the teacher sees automatic scores.
$qIds = $quiz->questions()->orderBy('position')->pluck('id')->all();
foreach ($students->slice(1, 8)->values() as $j => $s) {
    $score = [100, 80, 80, 60, 100, 80, 40, 100][$j];
    QuizAttempt::firstOrCreate(['quiz_id' => $quiz->id, 'student_id' => $s->id], [
        'question_order' => $qIds, 'current_step' => count($qIds), 'started_at' => $now->copy()->subHours(6 + $j),
        'deadline_at' => $now->copy()->subHours(6 + $j)->addMinutes(20), 'submitted_at' => $now->copy()->subHours(6 + $j)->addMinutes(12), 'score' => $score,
    ]);
}

$d1 = Discussion::firstOrCreate(['title' => 'Kenapa host /26 jumlahnya 62, bukan 64?'], [
    'class_id' => $class->id, 'subject_id' => $asj->id, 'user_id' => $student->id,
    'body' => 'Saya masih bingung kenapa dua alamat tidak bisa dipakai host. Ada yang bisa jelaskan?', 'is_pinned' => true,
]);
$r1 = DiscussionReply::firstOrCreate(['discussion_id' => $d1->id, 'user_id' => $teacher->id], [
    'body' => 'Pertanyaan bagus! Dua alamat dipakai untuk network address dan broadcast address, jadi 64 − 2 = 62 host.', 'is_best' => true,
]);
DiscussionReply::firstOrCreate(['discussion_id' => $d1->id, 'user_id' => $students[2]->id], ['body' => 'Oh jadi rumusnya 2^n − 2 ya, Bu. Terima kasih!', 'parent_id' => $r1->id]);
DiscussionReply::firstOrCreate(['discussion_id' => $d1->id, 'user_id' => $students[4]->id], ['body' => 'Saya juga baru paham sekarang 👍']);
Discussion::firstOrCreate(['title' => 'Ide produk kreatif untuk proyek PKK'], [
    'class_id' => $class->id, 'subject_id' => $pkk->id, 'user_id' => $teacher->id,
    'body' => 'Silakan bagikan ide produk kalian di sini, nanti kita diskusikan bersama di kelas.',
]);
Discussion::firstOrCreate(['title' => 'Error saat konfigurasi DHCP di Debian'], [
    'class_id' => $class->id, 'subject_id' => $tlj->id, 'user_id' => $students[3]->id,
    'body' => 'Service isc-dhcp-server gagal start setelah saya edit config. Ada yang pernah mengalami?',
]);

$admin = User::where('role', 'admin')->first();
foreach ([
    ['Jadwal Penilaian Akhir Semester Ganjil', 'Penilaian Akhir Semester akan dilaksanakan mulai 1 Desember 2026. Persiapkan diri dengan baik.', 'all', null],
    ['Libur Peringatan Hari Guru Nasional', 'Kegiatan belajar diganti dengan upacara peringatan Hari Guru Nasional pada 25 November.', 'all', null],
    ['Pengumpulan Proposal PKK Diperpanjang', 'Batas pengumpulan proposal usaha untuk kelas X TKJ 1 diperpanjang 3 hari.', 'class', $class->id],
] as $k => [$title, $content, $vis, $classId]) {
    Announcement::firstOrCreate(['title' => $title], [
        'content' => $content, 'visibility' => $vis, 'class_id' => $classId, 'created_by' => $k === 2 ? $teacher->id : $admin->id,
        'is_published' => true, 'published_at' => $now->copy()->subDays($k), 'expires_at' => $now->copy()->addDays(20),
    ]);
}

echo "Demo data ready.\n";
