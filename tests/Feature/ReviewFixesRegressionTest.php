<?php

namespace Tests\Feature;

use App\Models\Classes;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

/**
 * Regressions for the issues found in the November 2026 bug and UI review.
 */
class ReviewFixesRegressionTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    private Classes $class;

    private Subject $subject;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = $this->makeClass('X TKJ 1');
        $this->subject = $this->makeSubject('Jaringan', 'JAR');
        $this->teacher = $this->makeTeacher();
        $this->assignTeacher($this->teacher, $this->class, $this->subject);
        $this->student = $this->makeStudent($this->class, 'Andi', '24001');
    }

    public function test_new_teacher_of_a_class_takes_over_its_coursework(): void
    {
        $schedule = $this->makeSchedule($this->subject, $this->class);
        $newTeacher = $this->makeTeacher('Sari Dewi', '199001012015012002');
        $assignment = $this->makeAssignment($this->subject, $this->class, $this->teacher);
        $material = $this->makeMaterial($this->subject, $this->class, $this->teacher);
        $quiz = Quiz::factory()->create(['subject_id' => $this->subject->id, 'class_id' => $this->class->id, 'teacher_id' => $this->teacher->id]);

        $this->actingAs($this->admin())->put(route('admin.subjects.update', $this->subject), [
            'code' => 'JAR',
            'name' => 'Jaringan',
            'schedules' => [[
                'id' => $schedule->id,
                'class_id' => $this->class->id,
                'day_of_week' => 1,
                'start_time' => '07:30',
                'end_time' => '09:00',
                'teacher_id' => $newTeacher->id,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame($newTeacher->id, (int) $assignment->fresh()->teacher_id);
        $this->assertSame($newTeacher->id, (int) $material->fresh()->teacher_id);
        $this->assertSame($newTeacher->id, (int) $quiz->fresh()->teacher_id);

        $this->actingAs($newTeacher)->get(route('teacher.assignments.show', $assignment))->assertOk();
        $this->actingAs($this->teacher)->get(route('teacher.assignments.show', $assignment))->assertForbidden();
    }

    public function test_former_teacher_no_longer_lists_or_downloads_old_submissions(): void
    {
        $assignment = $this->makeAssignment($this->subject, $this->class, $this->teacher);
        $submission = $this->makeSubmission($assignment, $this->student);

        $this->actingAs($this->teacher)->get(route('teacher.grades.index'))->assertSee($assignment->title);

        $this->subject->teachingAssignments()->delete();

        $this->actingAs($this->teacher)->get(route('teacher.grades.index'))->assertDontSee($assignment->title);
        $this->assertFalse($this->teacher->can('viewSubmission', [$assignment, $submission]));
    }

    public function test_swapping_two_sessions_of_a_subject_is_not_a_schedule_clash(): void
    {
        $first = $this->makeSchedule($this->subject, $this->class, 1, '07:30', '09:00');
        $second = $this->makeSchedule($this->subject, $this->class, 1, '09:00', '10:30');

        $this->actingAs($this->admin())->put(route('admin.subjects.update', $this->subject), [
            'code' => 'JAR',
            'name' => 'Jaringan',
            'schedules' => [
                ['id' => $first->id, 'class_id' => $this->class->id, 'day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '10:30', 'teacher_id' => $this->teacher->id],
                ['id' => $second->id, 'class_id' => $this->class->id, 'day_of_week' => 1, 'start_time' => '07:30', 'end_time' => '09:00', 'teacher_id' => $this->teacher->id],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('09:00', substr((string) $first->fresh()->start_time, 0, 5));
    }

    public function test_other_subjects_schedules_still_clash(): void
    {
        $other = $this->makeSubject('Basis Data', 'BD');
        $this->makeSchedule($other, $this->class, 1, '07:30', '09:00');

        $this->actingAs($this->admin())->put(route('admin.subjects.update', $this->subject), [
            'code' => 'JAR',
            'name' => 'Jaringan',
            'schedules' => [
                ['class_id' => $this->class->id, 'day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '09:30', 'teacher_id' => $this->teacher->id],
            ],
        ])->assertSessionHasErrors('schedules.0.start_time');
    }

    public function test_empty_resubmission_does_not_wipe_a_text_only_submission(): void
    {
        $assignment = $this->makeAssignment($this->subject, $this->class, $this->teacher, ['allows_resubmission' => true]);
        $submittedAt = now()->subDays(2)->startOfSecond();
        $submission = $this->makeSubmission($assignment, $this->student, ['text_response' => 'Jawaban awal', 'submitted_at' => $submittedAt]);

        $this->actingAs($this->student)
            ->post(route('student.assignments.submit', [$this->subject, $assignment]), ['text_response' => ''])
            ->assertSessionHasErrors('file');

        $this->actingAs($this->student)
            ->post(route('student.assignments.submit', [$this->subject, $assignment]), ['text_response' => 'Jawaban awal'])
            ->assertSessionHasNoErrors();

        $submission->refresh();
        $this->assertSame('Jawaban awal', $submission->text_response);
        $this->assertTrue($submission->submitted_at->equalTo($submittedAt));
    }

    public function test_answer_posted_with_next_right_at_the_deadline_is_kept(): void
    {
        $quiz = $this->publishedQuizWithQuestions(2);

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $attempt = QuizAttempt::sole();
        $entry = $attempt->currentEntry();

        $this->travelTo($attempt->deadline_at->copy()->addSeconds(5));

        $this->actingAs($this->student)->post(route('student.quizzes.next', $quiz), [
            'step' => 0,
            'answers' => [$entry['question'] => $entry['options'][0]],
        ]);

        $this->assertTrue($attempt->fresh()->isSubmitted());
        $this->assertDatabaseHas('quiz_answers', [
            'quiz_attempt_id' => $attempt->id,
            'quiz_question_id' => $entry['question'],
            'quiz_option_id' => $entry['options'][0],
        ]);
    }

    public function test_answers_stay_hidden_while_a_classmate_is_still_working(): void
    {
        $quiz = $this->publishedQuizWithQuestions(1);
        $classmate = $this->makeStudent($this->class, 'Budi', '24002');

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $this->actingAs($classmate)->post(route('student.quizzes.start', $quiz));
        $this->actingAs($this->student)->post(route('student.quizzes.submit', $quiz));

        // The teacher closes the quiz early while the classmate's own deadline is still running.
        $quiz->update(['closes_at' => now()->subMinute()]);
        $finished = QuizAttempt::where('student_id', $this->student->id)->sole();

        $this->assertFalse($finished->canReviewAnswers($quiz->fresh()));

        $this->travel(40)->minutes();
        $this->assertTrue($finished->canReviewAnswers($quiz->fresh()));
    }

    public function test_a_closed_quiz_with_attempts_cannot_be_reopened(): void
    {
        $quiz = $this->publishedQuizWithQuestions(1);
        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $this->actingAs($this->student)->post(route('student.quizzes.submit', $quiz));
        $quiz->update(['opens_at' => now()->subDay(), 'closes_at' => now()->subHour()]);

        $this->actingAs($this->teacher)->put(route('teacher.quizzes.update', $quiz), [
            'teaching' => "{$this->subject->id}-{$this->class->id}",
            'title' => $quiz->title,
            'opens_at' => now()->subDay()->format('Y-m-d\TH:i'),
            'closes_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'duration_minutes' => 30,
        ])->assertSessionHasErrors('closes_at');

        $this->assertTrue($quiz->fresh()->hasClosed());
    }

    public function test_rejected_grade_only_fills_the_form_it_came_from(): void
    {
        $assignment = $this->makeAssignment($this->subject, $this->class, $this->teacher, ['max_score' => 100]);
        $first = $this->makeSubmission($assignment, $this->student);
        $this->makeSubmission($assignment, $this->makeStudent($this->class, 'Budi', '24002'));
        $show = route('teacher.assignments.show', $assignment);

        $this->actingAs($this->teacher)->from($show)
            ->put(route('teacher.submissions.grade', [$assignment, $first]), ['score' => 999, 'feedback' => 'Salah ketik', 'submission_id' => $first->id])
            ->assertSessionHasErrors('score');

        $page = $this->actingAs($this->teacher)->get($show)->assertOk()->getContent();

        $this->assertSame(1, substr_count($page, 'value="999"'));
        $this->assertSame(1, substr_count($page, 'Salah ketik'));
    }

    public function test_password_forms_state_the_real_rule(): void
    {
        $this->actingAs($this->student)->get(route('password.edit'))
            ->assertSee('Minimal 8 karakter, berisi huruf dan angka.')
            ->assertDontSee('Minimal 6 karakter');

        $this->actingAs($this->admin())->get(route('admin.students.create'))
            ->assertSee('Minimal 8 karakter, berisi huruf dan angka.');
    }

    public function test_the_active_academic_year_cannot_just_be_switched_off(): void
    {
        $year = $this->class->academicYear;

        $this->actingAs($this->admin())->put(route('admin.academic-years.update', $year), [
            'name' => $year->name,
            'start_date' => '2025-07-14',
            'end_date' => '2026-06-30',
        ])->assertSessionHas('error');

        $this->assertTrue($year->fresh()->is_active);
    }

    private function publishedQuizWithQuestions(int $count): Quiz
    {
        $quiz = Quiz::factory()->create([
            'subject_id' => $this->subject->id,
            'class_id' => $this->class->id,
            'teacher_id' => $this->teacher->id,
        ]);

        QuizQuestion::factory()->count($count)->withOptions(3)->create(['quiz_id' => $quiz->id]);

        return $quiz;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }
}
