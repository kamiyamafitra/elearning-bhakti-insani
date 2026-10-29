<?php

namespace Tests\Feature\Concerns;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Classes;
use App\Models\ClassSchedule;
use App\Models\LearningMaterial;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeachingAssignment;
use App\Models\User;

trait CreatesAcademicData
{
    protected function makeAcademicYear(string $name = '2025/2026'): AcademicYear
    {
        return AcademicYear::firstOrCreate(
            ['name' => $name],
            [
                'start_date' => '2025-07-14',
                'end_date' => '2026-06-30',
                'is_active' => true,
            ],
        );
    }

    protected function makeClass(string $name = 'X TKJ 1', ?AcademicYear $academicYear = null): Classes
    {
        $academicYear ??= $this->makeAcademicYear();

        return Classes::create([
            'academic_year_id' => $academicYear->id,
            'name' => $name,
            'code' => 'CLS-'.substr(md5($name), 0, 6),
            'level' => '10',
        ]);
    }

    protected function makeSubject(string $name, string $code, ?string $description = null): Subject
    {
        return Subject::create([
            'code' => $code,
            'name' => $name,
            'description' => $description,
        ]);
    }

    protected function makeSchedule(
        Subject $subject,
        Classes $class,
        int $dayOfWeek = 1,
        string $startTime = '07:30',
        string $endTime = '09:00',
    ): ClassSchedule {
        return $subject->schedules()->create([
            'class_id' => $class->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    protected function makeTeacher(string $name = 'Budi Santoso', string $nip = '198001012005011001'): User
    {
        $teacher = User::factory()->create([
            'name' => $name,
            'role' => 'teacher',
            'is_active' => true,
        ]);

        TeacherProfile::create([
            'user_id' => $teacher->id,
            'nip' => $nip,
            'phone' => '08123456789',
            'address' => 'Bogor',
        ]);

        return $teacher;
    }

    protected function makeStudent(
        Classes $class,
        string $name = 'Siswa Contoh',
        string $nis = '1234567890',
    ): User {
        $student = User::factory()->create([
            'name' => $name,
            'role' => 'student',
            'is_active' => true,
        ]);

        StudentProfile::create([
            'user_id' => $student->id,
            'nis' => $nis,
            'nisn' => '00'.$nis,
            'class_id' => $class->id,
            'gender' => 'L',
        ]);

        return $student;
    }

    protected function assignTeacher(User $teacher, Classes $class, Subject $subject): TeachingAssignment
    {
        return TeachingAssignment::create([
            'academic_year_id' => $class->academic_year_id,
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeAnnouncement(array $attributes = []): Announcement
    {
        return Announcement::create(array_merge([
            'title' => 'Pengumuman Sekolah',
            'content' => 'Isi pengumuman sekolah.',
            'visibility' => Announcement::VISIBILITY_ALL,
            'target_role' => null,
            'class_id' => null,
            'created_by' => null,
            'is_published' => true,
            'published_at' => now()->subHour(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeMaterial(
        Subject $subject,
        Classes $class,
        User $teacher,
        array $attributes = [],
    ): LearningMaterial {
        return LearningMaterial::create(array_merge([
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'title' => 'Materi Pembelajaran',
            'description' => 'Deskripsi materi pembelajaran.',
            'is_published' => true,
            'published_at' => now()->subHour(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeAssignment(
        Subject $subject,
        Classes $class,
        User $teacher,
        array $attributes = [],
    ): Assignment {
        return Assignment::create(array_merge([
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'title' => 'Tugas Pembelajaran',
            'instructions' => 'Kerjakan tugas berikut dengan baik.',
            'due_at' => now()->addDay(),
            'max_score' => 100,
            'status' => Assignment::STATUS_PUBLISHED,
            'allows_text_response' => true,
            'allows_resubmission' => false,
            'published_at' => now()->subHour(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeSubmission(
        Assignment $assignment,
        User $student,
        array $attributes = [],
    ): AssignmentSubmission {
        return AssignmentSubmission::create(array_merge([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'text_response' => 'Jawaban siswa.',
            'submitted_at' => now(),
        ], $attributes));
    }
}
