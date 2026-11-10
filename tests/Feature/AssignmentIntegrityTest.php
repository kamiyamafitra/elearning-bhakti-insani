<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classes;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

/**
 * Guards that keep grades and submissions consistent when assignments or students change.
 */
class AssignmentIntegrityTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    private Classes $class;

    private Classes $otherClass;

    private Subject $subject;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = $this->makeClass('X TKJ 1');
        $this->otherClass = $this->makeClass('X TKJ 2');
        $this->subject = $this->makeSubject('Jaringan Dasar', 'JD');
        $this->teacher = $this->makeTeacher();
        $this->assignTeacher($this->teacher, $this->class, $this->subject);
        $this->assignTeacher($this->teacher, $this->otherClass, $this->subject);
        $this->student = $this->makeStudent($this->class, 'Andi', '24001');
    }

    public function test_max_score_cannot_drop_below_a_score_already_given(): void
    {
        $assignment = $this->makeAssignment($this->subject, $this->class, $this->teacher, ['max_score' => 100]);
        $this->makeSubmission($assignment, $this->student, ['score' => 90, 'graded_at' => now()]);

        $this->actingAs($this->teacher)
            ->put(route('teacher.assignments.update', $assignment), $this->payload($assignment, ['max_score' => 50]))
            ->assertSessionHasErrors(['max_score' => 'Skor maksimal tidak boleh lebih kecil dari nilai tertinggi yang sudah diberikan (90).']);
        $this->assertSame(100, $assignment->fresh()->max_score);

        $this->actingAs($this->teacher)
            ->put(route('teacher.assignments.update', $assignment), $this->payload($assignment, ['max_score' => 90]))
            ->assertSessionHasNoErrors();
        $this->assertSame(90, $assignment->fresh()->max_score);
    }

    public function test_class_cannot_change_once_students_have_submitted(): void
    {
        $assignment = $this->makeAssignment($this->subject, $this->class, $this->teacher);
        $this->makeSubmission($assignment, $this->student);

        $this->actingAs($this->teacher)
            ->put(route('teacher.assignments.update', $assignment), $this->payload($assignment, ['class_id' => $this->otherClass->id]))
            ->assertSessionHasErrors(['class_id' => 'Kelas tujuan tidak dapat diubah karena siswa sudah mengumpulkan tugas ini.']);
        $this->assertSame($this->class->id, $assignment->fresh()->class_id);
    }

    public function test_class_can_still_change_before_anyone_submits(): void
    {
        $assignment = $this->makeAssignment($this->subject, $this->class, $this->teacher);

        $this->actingAs($this->teacher)
            ->put(route('teacher.assignments.update', $assignment), $this->payload($assignment, ['class_id' => $this->otherClass->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($this->otherClass->id, $assignment->fresh()->class_id);
    }

    public function test_a_new_assignment_needs_a_deadline_in_the_future(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.assignments.store', $this->subject), [
                'class_id' => $this->class->id,
                'title' => 'Tugas Lama',
                'instructions' => 'Kerjakan.',
                'due_at' => now()->subDay()->format('Y-m-d H:i'),
                'max_score' => 100,
                'status' => Assignment::STATUS_PUBLISHED,
            ])
            ->assertSessionHasErrors(['due_at' => 'Batas pengumpulan harus setelah waktu sekarang.']);

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_an_existing_assignment_can_keep_a_deadline_that_has_passed(): void
    {
        $assignment = $this->makeAssignment($this->subject, $this->class, $this->teacher, ['due_at' => now()->subDays(3)]);

        $this->actingAs($this->teacher)
            ->put(route('teacher.assignments.update', $assignment), $this->payload($assignment, ['title' => 'Judul Baru']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Judul Baru', $assignment->fresh()->title);
    }

    public function test_deleting_a_student_also_deletes_their_submitted_files(): void
    {
        $assignment = $this->makeAssignment($this->subject, $this->class, $this->teacher);
        Storage::disk('local')->put('lms/submissions/andi.pdf', 'tugas andi');
        $this->makeSubmission($assignment, $this->student, ['file_path' => 'lms/submissions/andi.pdf', 'file_original_name' => 'andi.pdf']);

        $classmate = $this->makeStudent($this->class, 'Bunga', '24002');
        Storage::disk('local')->put('lms/submissions/bunga.pdf', 'tugas bunga');
        $this->makeSubmission($assignment, $classmate, ['file_path' => 'lms/submissions/bunga.pdf', 'file_original_name' => 'bunga.pdf']);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->delete(route('admin.students.destroy', $this->student->studentProfile))
            ->assertSessionHas('success');

        Storage::disk('local')->assertMissing('lms/submissions/andi.pdf');
        Storage::disk('local')->assertExists('lms/submissions/bunga.pdf');
        $this->assertDatabaseMissing('assignment_submissions', ['student_id' => $this->student->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Assignment $assignment, array $overrides = []): array
    {
        return array_merge([
            'class_id' => $assignment->class_id,
            'title' => $assignment->title,
            'instructions' => $assignment->instructions,
            'due_at' => $assignment->due_at->format('Y-m-d H:i'),
            'max_score' => $assignment->max_score,
            'status' => $assignment->status,
        ], $overrides);
    }
}
