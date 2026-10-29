<?php

namespace Tests\Feature\Teacher;

use App\Models\Assignment;
use App\Models\LearningMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class LmsManagementTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_teacher_can_create_and_publish_material_for_an_assigned_subject(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $this->assignTeacher($teacher, $class, $subject);

        $response = $this->actingAs($teacher)->post(route('teacher.materials.store', $subject), [
            'class_id' => $class->id,
            'title' => 'Modul Bahasa Jepang Bab 1',
            'description' => 'Materi pengenalan huruf hiragana.',
            'file' => UploadedFile::fake()->create('modul-hiragana.pdf', 120, 'application/pdf'),
            'external_url' => 'https://example.com/bahasa-jepang',
            'is_published' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $material = LearningMaterial::query()->firstOrFail();

        $this->assertSame($teacher->id, $material->teacher_id);
        $this->assertSame($class->id, $material->class_id);
        $this->assertSame($subject->id, $material->subject_id);
        $this->assertTrue($material->is_published);
        $this->assertNotNull($material->published_at);
        $this->assertNotNull($material->file_path);
        Storage::disk('local')->assertExists($material->file_path);

        $this->actingAs($teacher)
            ->get(route('teacher.subjects.show', ['subject' => $subject, 'tab' => 'materials']))
            ->assertOk()
            ->assertSee('Modul Bahasa Jepang Bab 1');
    }

    public function test_teacher_cannot_create_material_for_an_unassigned_class(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $otherTeacher = $this->makeTeacher('Guru Lain', '999');
        $assignedClass = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');

        $this->assignTeacher($teacher, $assignedClass, $subject);
        $this->assignTeacher($otherTeacher, $otherClass, $subject);

        $this->actingAs($teacher)->post(route('teacher.materials.store', $subject), [
            'class_id' => $otherClass->id,
            'title' => 'Materi Tidak Berhak',
            'description' => 'Tidak boleh dibuat.',
            'is_published' => '1',
        ])->assertForbidden();

        $this->assertDatabaseCount('learning_materials', 0);
    }

    public function test_teacher_cannot_edit_or_delete_another_teachers_material(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $owner = $this->makeTeacher('Guru Pemilik', '999');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');

        $this->assignTeacher($teacher, $class, $subject);
        $this->assignTeacher($owner, $class, $subject);

        $material = $this->makeMaterial($subject, $class, $owner);

        $this->actingAs($teacher)
            ->get(route('teacher.materials.edit', $material))
            ->assertForbidden();

        $this->actingAs($teacher)
            ->delete(route('teacher.materials.destroy', $material))
            ->assertForbidden();

        $this->assertDatabaseHas('learning_materials', ['id' => $material->id]);
    }

    public function test_teacher_can_create_assignment_for_an_authorized_class(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $this->assignTeacher($teacher, $class, $subject);

        $response = $this->actingAs($teacher)->post(route('teacher.assignments.store', $subject), [
            'class_id' => $class->id,
            'title' => 'Latihan Hiragana',
            'instructions' => 'Tuliskan huruf hiragana beserta contohnya.',
            'attachment' => UploadedFile::fake()->create('lembar-tugas.pdf', 80, 'application/pdf'),
            'due_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'max_score' => 100,
            'status' => Assignment::STATUS_PUBLISHED,
            'allows_text_response' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $assignment = Assignment::query()->firstOrFail();

        $this->assertSame($teacher->id, $assignment->teacher_id);
        $this->assertSame($class->id, $assignment->class_id);
        $this->assertSame(Assignment::STATUS_PUBLISHED, $assignment->status);
        $this->assertNotNull($assignment->published_at);
        $this->assertNotNull($assignment->attachment_path);
        Storage::disk('local')->assertExists($assignment->attachment_path);

        $this->actingAs($teacher)
            ->get(route('teacher.subjects.show', ['subject' => $subject, 'tab' => 'assignments']))
            ->assertOk()
            ->assertSee('Latihan Hiragana');
    }

    public function test_teacher_cannot_create_assignment_for_an_unrelated_class(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $otherTeacher = $this->makeTeacher('Guru Lain', '999');
        $assignedClass = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');

        $this->assignTeacher($teacher, $assignedClass, $subject);
        $this->assignTeacher($otherTeacher, $otherClass, $subject);

        $this->actingAs($teacher)->post(route('teacher.assignments.store', $subject), [
            'class_id' => $otherClass->id,
            'title' => 'Tugas Tidak Berhak',
            'instructions' => 'Tidak boleh dibuat.',
            'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'max_score' => 100,
            'status' => Assignment::STATUS_PUBLISHED,
        ])->assertForbidden();

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_teacher_can_review_submissions_and_enter_valid_grades_and_feedback(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $this->assignTeacher($teacher, $class, $subject);

        $assignment = $this->makeAssignment($subject, $class, $teacher, ['max_score' => 100]);
        $submission = $this->makeSubmission($assignment, $student, [
            'text_response' => 'Ini jawaban Mulyadi.',
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.assignments.show', $assignment))
            ->assertOk()
            ->assertSee('Mulyadi')
            ->assertSee('Beri Nilai')
            ->assertSee('Ini jawaban Mulyadi.');

        $response = $this->actingAs($teacher)->put(
            route('teacher.submissions.grade', [$assignment, $submission]),
            ['score' => 88, 'feedback' => 'Pekerjaan baik, tingkatkan lagi.'],
        );

        $response->assertRedirect(route('teacher.assignments.show', $assignment));
        $response->assertSessionHas('success');

        $submission->refresh();
        $this->assertSame(88, $submission->score);
        $this->assertSame('Pekerjaan baik, tingkatkan lagi.', $submission->feedback);
        $this->assertNotNull($submission->graded_at);
        $this->assertSame($teacher->id, $submission->graded_by);

        $this->actingAs($teacher)->put(
            route('teacher.submissions.grade', [$assignment, $submission]),
            ['score' => 101, 'feedback' => 'Melebihi skor maksimal.'],
        )->assertSessionHasErrors('score');

        $this->assertSame(88, $submission->fresh()->score);
    }

    public function test_teacher_can_download_a_students_submission_and_students_cannot_use_the_teacher_route(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $this->assignTeacher($teacher, $class, $subject);

        $assignment = $this->makeAssignment($subject, $class, $teacher);
        Storage::disk('local')->put('lms/submissions/jawaban.pdf', 'jawaban siswa');
        $submission = $this->makeSubmission($assignment, $student, [
            'file_path' => 'lms/submissions/jawaban.pdf',
            'file_original_name' => 'jawaban.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => 13,
        ]);

        $response = $this->actingAs($teacher)
            ->get(route('teacher.submissions.download', [$assignment, $submission]));

        $response->assertOk();
        $this->assertStringContainsString('jawaban.pdf', (string) $response->headers->get('content-disposition'));

        $this->actingAs($student)
            ->get(route('teacher.submissions.download', [$assignment, $submission]))
            ->assertForbidden();
    }

    public function test_teacher_dashboard_uses_real_assignment_and_grading_counts(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $firstStudent = $this->makeStudent($class, 'Siswa Satu', '1001');
        $secondStudent = $this->makeStudent($class, 'Siswa Dua', '1002');
        $this->assignTeacher($teacher, $class, $subject);

        $assignment = $this->makeAssignment($subject, $class, $teacher, [
            'due_at' => now()->addDays(3),
        ]);

        $this->makeSubmission($assignment, $firstStudent);
        $this->makeSubmission($assignment, $secondStudent, [
            'score' => 90,
            'graded_at' => now(),
            'graded_by' => $teacher->id,
        ]);

        $otherTeacher = $this->makeTeacher('Guru Lain', '999');
        $otherAssignment = $this->makeAssignment($subject, $class, $otherTeacher);

        $this->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertViewHas('assignmentsCreated', 1)
            ->assertViewHas('activeAssignments', 1)
            ->assertViewHas('pendingGrading', 1)
            ->assertViewHas('recentSubmissions', fn ($submissions) => $submissions->count() === 2)
            ->assertSee('Pengumpulan Terbaru');

        $this->assertNotSame($assignment->id, $otherAssignment->id);
    }

    public function test_invalid_material_file_type_is_rejected(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $this->assignTeacher($teacher, $class, $subject);

        $this->actingAs($teacher)->post(route('teacher.materials.store', $subject), [
            'class_id' => $class->id,
            'title' => 'Materi Berbahaya',
            'description' => 'Berkas tidak diizinkan.',
            'file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
            'is_published' => '1',
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('learning_materials', 0);
    }
}
