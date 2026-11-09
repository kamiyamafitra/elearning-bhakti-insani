<?php

namespace Tests\Feature\Student;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class LmsStudentTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_student_sees_only_published_materials_for_their_class_and_can_download_them(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $otherTeacher = $this->makeTeacher('Guru Lain', '999');
        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');

        $this->assignTeacher($teacher, $class, $subject);
        $this->assignTeacher($otherTeacher, $otherClass, $subject);

        Storage::disk('local')->put('lms/materials/hiragana.pdf', 'materi hiragana');

        $published = $this->makeMaterial($subject, $class, $teacher, [
            'title' => 'Modul Hiragana',
            'description' => 'Materi pengenalan huruf hiragana.',
            'file_path' => 'lms/materials/hiragana.pdf',
            'file_original_name' => 'hiragana.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => 15,
        ]);

        $draft = $this->makeMaterial($subject, $class, $teacher, [
            'title' => 'Materi Draf Rahasia',
            'is_published' => false,
            'published_at' => null,
        ]);

        $otherClassMaterial = $this->makeMaterial($subject, $otherClass, $otherTeacher, [
            'title' => 'Materi Kelas Lain',
        ]);

        $this->actingAs($student)
            ->get(route('student.subjects.show', ['subject' => $subject, 'tab' => 'materials']))
            ->assertOk()
            ->assertSee('Modul Hiragana')
            ->assertDontSee('Materi Draf Rahasia')
            ->assertDontSee('Materi Kelas Lain');

        $this->actingAs($student)
            ->get(route('student.materials.show', [$subject, $published]))
            ->assertOk()
            ->assertSee('Materi pengenalan huruf hiragana.');

        $response = $this->actingAs($student)
            ->get(route('student.materials.download', [$subject, $published]));

        $response->assertOk();
        $this->assertStringContainsString('hiragana.pdf', (string) $response->headers->get('content-disposition'));

        $this->actingAs($student)
            ->get(route('student.materials.show', [$subject, $draft]))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('student.materials.download', [$subject, $draft]))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('student.materials.show', [$subject, $otherClassMaterial]))
            ->assertForbidden();

        $this->assertModelExists($published);
        $this->assertModelExists($draft);
        $this->assertModelExists($otherClassMaterial);
    }

    public function test_student_cannot_access_a_subject_that_is_not_part_of_their_class(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $unrelatedSubject = $this->makeSubject('Matematika', 'MTK');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');

        $this->assignTeacher($teacher, $otherClass, $unrelatedSubject);

        $material = $this->makeMaterial($unrelatedSubject, $otherClass, $teacher);

        $this->actingAs($student)
            ->get(route('student.materials.show', [$unrelatedSubject, $material]))
            ->assertNotFound();

        $this->actingAs($student)
            ->get(route('student.subjects.show', $unrelatedSubject))
            ->assertNotFound();

        $this->assertModelExists($material);
        $this->assertSame($subject->code, 'BJPN');
    }

    public function test_student_can_submit_an_assignment_with_a_text_answer(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $this->assignTeacher($teacher, $class, $subject);

        $assignment = $this->makeAssignment($subject, $class, $teacher, [
            'title' => 'Latihan Hiragana',
        ]);

        $this->actingAs($student)
            ->get(route('student.assignments.show', [$subject, $assignment]))
            ->assertOk()
            ->assertSee('Latihan Hiragana')
            ->assertSee('Kumpulkan Tugas')
            ->assertSee('Belum Dikumpulkan');

        $response = $this->actingAs($student)->post(
            route('student.assignments.submit', [$subject, $assignment]),
            ['text_response' => 'Ini jawaban hiragana saya.'],
        );

        $response->assertRedirect(route('student.assignments.show', [$subject, $assignment]));
        $response->assertSessionHas('success');

        $submission = AssignmentSubmission::query()->firstOrFail();

        $this->assertSame($assignment->id, $submission->assignment_id);
        $this->assertSame($student->id, $submission->student_id);
        $this->assertSame('Ini jawaban hiragana saya.', $submission->text_response);
        $this->assertSame(AssignmentSubmission::STATUS_SUBMITTED, $submission->statusKey());

        $this->actingAs($student)
            ->get(route('student.assignments.show', [$subject, $assignment]))
            ->assertOk()
            ->assertSee('Dikumpulkan')
            ->assertSee('Ini jawaban hiragana saya.');
    }

    public function test_student_can_submit_an_assignment_with_a_file_and_download_it_back(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $this->assignTeacher($teacher, $class, $subject);

        $assignment = $this->makeAssignment($subject, $class, $teacher);

        $this->actingAs($student)->post(
            route('student.assignments.submit', [$subject, $assignment]),
            ['file' => UploadedFile::fake()->create('jawaban.pdf', 40, 'application/pdf')],
        )->assertSessionHas('success');

        $submission = AssignmentSubmission::query()->firstOrFail();

        $this->assertNotNull($submission->file_path);
        Storage::disk('local')->assertExists($submission->file_path);

        $response = $this->actingAs($student)
            ->get(route('student.submissions.download', [$subject, $assignment]));

        $response->assertOk();
        $this->assertStringContainsString('jawaban.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_assignment_submission_requires_a_file_or_text_answer(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $this->assignTeacher($teacher, $class, $subject);

        $assignment = $this->makeAssignment($subject, $class, $teacher);

        $this->actingAs($student)
            ->post(route('student.assignments.submit', [$subject, $assignment]), [])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('assignment_submissions', 0);
    }

    public function test_submission_after_the_deadline_is_marked_as_late(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $this->assignTeacher($teacher, $class, $subject);

        $assignment = $this->makeAssignment($subject, $class, $teacher, [
            'due_at' => now()->subDay(),
        ]);

        $this->actingAs($student)
            ->post(route('student.assignments.submit', [$subject, $assignment]), [
                'text_response' => 'Jawaban terlambat.',
            ])
            ->assertSessionHas('success');

        $submission = AssignmentSubmission::query()->firstOrFail();

        $this->assertSame(AssignmentSubmission::STATUS_LATE, $submission->statusKey());
        $this->assertSame('Terlambat', $submission->statusLabel());

        $this->actingAs($student)
            ->get(route('student.assignments.show', [$subject, $assignment]))
            ->assertOk()
            ->assertSee('Terlambat');
    }

    public function test_student_cannot_resubmit_when_resubmission_is_disallowed(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $this->assignTeacher($teacher, $class, $subject);

        $assignment = $this->makeAssignment($subject, $class, $teacher, [
            'allows_resubmission' => false,
        ]);

        $this->makeSubmission($assignment, $student, [
            'text_response' => 'Jawaban pertama.',
        ]);

        $this->actingAs($student)
            ->post(route('student.assignments.submit', [$subject, $assignment]), [
                'text_response' => 'Jawaban kedua.',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('assignment_submissions', 1);

        $submission = AssignmentSubmission::query()->firstOrFail();
        $this->assertSame('Jawaban pertama.', $submission->text_response);
    }

    public function test_student_can_resubmit_when_allowed_and_the_previous_file_is_replaced(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $this->assignTeacher($teacher, $class, $subject);

        $assignment = $this->makeAssignment($subject, $class, $teacher, [
            'allows_resubmission' => true,
        ]);

        Storage::disk('local')->put('lms/submissions/lama.pdf', 'jawaban lama');

        $this->makeSubmission($assignment, $student, [
            'text_response' => 'Jawaban lama.',
            'file_path' => 'lms/submissions/lama.pdf',
            'file_original_name' => 'lama.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => 11,
        ]);

        $this->actingAs($student)
            ->post(route('student.assignments.submit', [$subject, $assignment]), [
                'text_response' => 'Jawaban revisi.',
                'file' => UploadedFile::fake()->create('baru.pdf', 30, 'application/pdf'),
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('assignment_submissions', 1);

        $submission = AssignmentSubmission::query()->firstOrFail();

        $this->assertSame('Jawaban revisi.', $submission->text_response);
        $this->assertNotSame('lms/submissions/lama.pdf', $submission->file_path);
        Storage::disk('local')->assertMissing('lms/submissions/lama.pdf');
        Storage::disk('local')->assertExists($submission->file_path);
    }

    public function test_double_submit_saves_over_the_submission_created_a_moment_earlier(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $this->assignTeacher($teacher, $class, $subject);

        $assignment = $this->makeAssignment($subject, $class, $teacher, [
            'allows_resubmission' => true,
        ]);

        Storage::disk('local')->put('lms/submissions/ketukan-pertama.pdf', 'ketukan pertama');
        $this->saveConcurrentSubmissionAfterExistenceCheck($assignment, $student, 'lms/submissions/ketukan-pertama.pdf');

        $this->actingAs($student)
            ->post(route('student.assignments.submit', [$subject, $assignment]), [
                'text_response' => 'Jawaban kedua.',
                'file' => UploadedFile::fake()->create('jawaban.pdf', 30, 'application/pdf'),
            ])
            ->assertRedirect(route('student.assignments.show', [$subject, $assignment]))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('assignment_submissions', 1);

        $submission = AssignmentSubmission::query()->firstOrFail();

        $this->assertSame('Jawaban kedua.', $submission->text_response);
        Storage::disk('local')->assertExists($submission->file_path);
        Storage::disk('local')->assertMissing('lms/submissions/ketukan-pertama.pdf');
    }

    public function test_double_submit_keeps_the_first_submission_when_resubmission_is_disallowed(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $this->assignTeacher($teacher, $class, $subject);

        $assignment = $this->makeAssignment($subject, $class, $teacher, [
            'allows_resubmission' => false,
        ]);

        Storage::disk('local')->put('lms/submissions/ketukan-pertama.pdf', 'ketukan pertama');
        $this->saveConcurrentSubmissionAfterExistenceCheck($assignment, $student, 'lms/submissions/ketukan-pertama.pdf');

        $this->actingAs($student)
            ->post(route('student.assignments.submit', [$subject, $assignment]), [
                'file' => UploadedFile::fake()->create('jawaban.pdf', 30, 'application/pdf'),
            ])
            ->assertRedirect(route('student.assignments.show', [$subject, $assignment]))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('assignment_submissions', 1);

        $submission = AssignmentSubmission::query()->firstOrFail();

        $this->assertSame('lms/submissions/ketukan-pertama.pdf', $submission->file_path);
        $this->assertSame(['lms/submissions/ketukan-pertama.pdf'], Storage::disk('local')->allFiles('lms/submissions'));
    }

    /**
     * Imitates a double tap: right after this request checks for an earlier submission and finds none,
     * the other tap saves its row, so this request's insert hits the unique key.
     */
    private function saveConcurrentSubmissionAfterExistenceCheck(Assignment $assignment, User $student, string $filePath): void
    {
        $inserted = false;

        DB::listen(function (QueryExecuted $query) use (&$inserted, $assignment, $student, $filePath): void {
            if ($inserted || ! str_starts_with($query->sql, 'select') || ! str_contains($query->sql, 'assignment_submissions')) {
                return;
            }

            $inserted = true;

            DB::table('assignment_submissions')->insert([
                'assignment_id' => $assignment->id,
                'student_id' => $student->id,
                'text_response' => 'Jawaban pertama.',
                'file_path' => $filePath,
                'file_original_name' => 'jawaban.pdf',
                'file_mime' => 'application/pdf',
                'file_size' => 15,
                'submitted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function test_graded_submission_cannot_be_resubmitted_even_when_resubmission_is_allowed(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $this->assignTeacher($teacher, $class, $subject);

        $assignment = $this->makeAssignment($subject, $class, $teacher, [
            'allows_resubmission' => true,
            'max_score' => 100,
        ]);

        $submission = $this->makeSubmission($assignment, $student, [
            'text_response' => 'Jawaban awal.',
            'score' => 90,
            'feedback' => 'Sudah bagus.',
            'graded_at' => now(),
            'graded_by' => $teacher->id,
        ]);

        $this->actingAs($student)
            ->post(route('student.assignments.submit', [$subject, $assignment]), [
                'text_response' => 'Jawaban pengganti.',
            ])
            ->assertSessionHas('error');

        $submission->refresh();

        $this->assertSame(90, $submission->score);
        $this->assertSame('Jawaban awal.', $submission->text_response);
    }

    public function test_student_cannot_submit_or_view_a_draft_assignment(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $this->assignTeacher($teacher, $class, $subject);

        $draft = $this->makeAssignment($subject, $class, $teacher, [
            'title' => 'Tugas Draf',
            'status' => Assignment::STATUS_DRAFT,
            'published_at' => null,
        ]);

        $this->actingAs($student)
            ->get(route('student.subjects.show', ['subject' => $subject, 'tab' => 'assignments']))
            ->assertOk()
            ->assertDontSee('Tugas Draf');

        $this->actingAs($student)
            ->get(route('student.assignments.show', [$subject, $draft]))
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('student.assignments.submit', [$subject, $draft]), [
                'text_response' => 'Tidak boleh.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('assignment_submissions', 0);
    }

    public function test_student_can_only_see_and_download_their_own_submission(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $otherStudent = $this->makeStudent($class, 'Siswa Lain', '1111111111');
        $this->assignTeacher($teacher, $class, $subject);

        $assignment = $this->makeAssignment($subject, $class, $teacher);

        Storage::disk('local')->put('lms/submissions/milik-sendiri.pdf', 'punya sendiri');
        Storage::disk('local')->put('lms/submissions/milik-lain.pdf', 'punya orang lain');

        $this->makeSubmission($assignment, $student, [
            'text_response' => 'Jawaban milik sendiri.',
            'file_path' => 'lms/submissions/milik-sendiri.pdf',
            'file_original_name' => 'milik-sendiri.pdf',
            'file_size' => 13,
        ]);

        $otherSubmission = $this->makeSubmission($assignment, $otherStudent, [
            'text_response' => 'Jawaban rahasia siswa lain.',
            'file_path' => 'lms/submissions/milik-lain.pdf',
            'file_original_name' => 'milik-lain.pdf',
            'file_size' => 17,
        ]);

        $this->actingAs($student)
            ->get(route('student.assignments.show', [$subject, $assignment]))
            ->assertOk()
            ->assertSee('Jawaban milik sendiri.')
            ->assertDontSee('Jawaban rahasia siswa lain.');

        $response = $this->actingAs($student)
            ->get(route('student.submissions.download', [$subject, $assignment]));

        $response->assertOk();
        $this->assertStringContainsString('milik-sendiri.pdf', (string) $response->headers->get('content-disposition'));

        $this->actingAs($student)
            ->get(route('teacher.submissions.download', [$assignment, $otherSubmission]))
            ->assertForbidden();
    }

    public function test_student_can_download_the_teacher_attachment_for_an_accessible_assignment_only(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $this->assignTeacher($teacher, $class, $subject);

        Storage::disk('local')->put('lms/assignments/lembar.pdf', 'lembar tugas');

        $assignment = $this->makeAssignment($subject, $class, $teacher, [
            'attachment_path' => 'lms/assignments/lembar.pdf',
            'attachment_original_name' => 'lembar.pdf',
            'attachment_size' => 12,
        ]);

        $draft = $this->makeAssignment($subject, $class, $teacher, [
            'status' => Assignment::STATUS_DRAFT,
            'published_at' => null,
            'attachment_path' => 'lms/assignments/lembar.pdf',
            'attachment_original_name' => 'lembar.pdf',
        ]);

        $response = $this->actingAs($student)
            ->get(route('student.assignments.download', [$subject, $assignment]));

        $response->assertOk();
        $this->assertStringContainsString('lembar.pdf', (string) $response->headers->get('content-disposition'));

        $this->actingAs($student)
            ->get(route('student.assignments.download', [$subject, $draft]))
            ->assertForbidden();
    }

    public function test_student_cannot_manage_materials_assignments_or_grades(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $this->assignTeacher($teacher, $class, $subject);

        $material = $this->makeMaterial($subject, $class, $teacher);
        $assignment = $this->makeAssignment($subject, $class, $teacher);
        $submission = $this->makeSubmission($assignment, $student, ['score' => 70, 'graded_at' => now()]);

        $this->actingAs($student)->post(route('teacher.materials.store', $subject), [
            'class_id' => $class->id,
            'title' => 'Materi Tidak Sah',
            'description' => 'Tidak boleh.',
            'is_published' => '1',
        ])->assertForbidden();

        $this->actingAs($student)
            ->put(route('teacher.materials.update', $material), [
                'class_id' => $class->id,
                'title' => 'Materi Diubah',
                'description' => 'Tidak boleh.',
                'is_published' => '1',
            ])
            ->assertForbidden();

        $this->actingAs($student)
            ->delete(route('teacher.materials.destroy', $material))
            ->assertForbidden();

        $this->actingAs($student)->post(route('teacher.assignments.store', $subject), [
            'class_id' => $class->id,
            'title' => 'Tugas Tidak Sah',
            'instructions' => 'Tidak boleh.',
            'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'max_score' => 100,
            'status' => Assignment::STATUS_PUBLISHED,
        ])->assertForbidden();

        $this->actingAs($student)
            ->put(route('teacher.assignments.update', $assignment), [
                'class_id' => $class->id,
                'title' => 'Tugas Diubah',
                'instructions' => 'Tidak boleh.',
                'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
                'max_score' => 100,
                'status' => Assignment::STATUS_PUBLISHED,
            ])
            ->assertForbidden();

        $this->actingAs($student)
            ->put(route('teacher.submissions.grade', [$assignment, $submission]), [
                'score' => 100,
                'feedback' => 'Nilai tidak sah.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('learning_materials', [
            'id' => $material->id,
            'title' => $material->title,
        ]);
        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'title' => $assignment->title,
        ]);
        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submission->id,
            'score' => 70,
        ]);
    }

    public function test_student_cannot_access_an_assignment_from_another_class(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $otherTeacher = $this->makeTeacher('Guru Lain', '999');
        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');

        $this->assignTeacher($teacher, $class, $subject);
        $this->assignTeacher($otherTeacher, $otherClass, $subject);

        $otherClassAssignment = $this->makeAssignment($subject, $otherClass, $otherTeacher);

        $this->actingAs($student)
            ->get(route('student.assignments.show', [$subject, $otherClassAssignment]))
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('student.assignments.submit', [$subject, $otherClassAssignment]), [
                'text_response' => 'Tidak boleh.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('assignment_submissions', 0);
    }

    public function test_student_dashboard_uses_real_assignment_counts(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $otherTeacher = $this->makeTeacher('Guru Lain', '999');
        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $this->assignTeacher($teacher, $class, $subject);
        $this->assignTeacher($otherTeacher, $otherClass, $subject);

        $submitted = $this->makeAssignment($subject, $class, $teacher, [
            'due_at' => now()->addDays(2),
        ]);

        $this->makeAssignment($subject, $class, $teacher, [
            'title' => 'Tugas Belum Dikumpulkan',
            'due_at' => now()->addDays(3),
        ]);

        $this->makeAssignment($subject, $class, $teacher, [
            'title' => 'Tugas Jauh',
            'due_at' => now()->addDays(10),
        ]);

        $this->makeAssignment($subject, $class, $teacher, [
            'title' => 'Tugas Draf',
            'status' => Assignment::STATUS_DRAFT,
            'published_at' => null,
        ]);

        $this->makeAssignment($subject, $otherClass, $otherTeacher);

        $this->makeSubmission($submitted, $student, [
            'score' => 85,
            'feedback' => 'Bagus.',
            'graded_at' => now(),
            'graded_by' => $teacher->id,
        ]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertViewHas('availableAssignments', 3)
            ->assertViewHas('pendingAssignments', 2)
            ->assertViewHas('upcomingDeadlines', 2)
            ->assertViewHas('gradedAssignments', 1)
            ->assertViewHas('latestGrade', 85);
    }

    public function test_invalid_submission_file_type_is_rejected(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $this->assignTeacher($teacher, $class, $subject);

        $assignment = $this->makeAssignment($subject, $class, $teacher);

        $this->actingAs($student)
            ->post(route('student.assignments.submit', [$subject, $assignment]), [
                'file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('assignment_submissions', 0);
    }

    public function test_material_and_assignment_status_helpers_reflect_real_data(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $this->assignTeacher($teacher, $class, $subject);

        $material = $this->makeMaterial($subject, $class, $teacher, [
            'is_published' => true,
            'published_at' => now()->addDay(),
        ]);

        $this->assertFalse($material->isPublished());

        $assignment = $this->makeAssignment($subject, $class, $teacher, [
            'due_at' => now()->subHour(),
        ]);

        $this->assertNull($assignment->submissions()->first());
        $this->assertSame(
            AssignmentSubmission::STATUS_NOT_SUBMITTED,
            $assignment->statusFor(null),
        );

        $onTime = $this->makeSubmission($assignment, $student, [
            'submitted_at' => now()->subHours(2),
        ]);

        $onTime->setRelation('assignment', $assignment);
        $this->assertSame(AssignmentSubmission::STATUS_SUBMITTED, $onTime->statusKey());

        $late = $this->makeSubmission($assignment, $this->makeStudent($class, 'Siswa Lain', '1111111111'), [
            'submitted_at' => now()->subMinutes(5),
        ]);

        $late->setRelation('assignment', $assignment);
        $this->assertSame(AssignmentSubmission::STATUS_LATE, $late->statusKey());
    }
}
