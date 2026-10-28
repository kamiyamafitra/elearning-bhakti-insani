<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@smkbhaktiinsani.sch.id'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        $teacherUser = User::firstOrCreate(
            ['email' => 'guru@smkbhaktiinsani.sch.id'],
            [
                'name' => 'Guru Demo',
                'password' => Hash::make('guru123'),
                'role' => 'teacher',
                'is_active' => true,
            ]
        );

        TeacherProfile::firstOrCreate(
            ['user_id' => $teacherUser->id],
            [
                'nip' => '198001012005011001',
                'phone' => '081234567890',
            ]
        );

        $academicYear = AcademicYear::firstOrCreate(
            ['name' => '2025/2026'],
            [
                'start_date' => '2025-07-14',
                'end_date' => '2026-06-30',
                'is_active' => true,
            ]
        );

        $class = Classes::firstOrCreate(
            ['code' => 'X-TKJ-1'],
            [
                'academic_year_id' => $academicYear->id,
                'name' => 'X TKJ 1',
                'level' => 'X',
            ]
        );

        Subject::firstOrCreate(
            ['code' => 'PKK'],
            [
                'name' => 'Produk Kreatif dan Kewirausahaan',
                'description' => 'Mata pelajaran PKK',
            ]
        );

        $studentUser = User::firstOrCreate(
            ['email' => 'siswa@smkbhaktiinsani.sch.id'],
            [
                'name' => 'Siswa Demo',
                'password' => Hash::make('siswa123'),
                'role' => 'student',
                'is_active' => true,
            ]
        );

        StudentProfile::firstOrCreate(
            ['user_id' => $studentUser->id],
            [
                'nisn' => '0051234567',
                'nis' => '12345',
                'class_id' => $class->id,
                'gender' => 'L',
            ]
        );
    }
}
