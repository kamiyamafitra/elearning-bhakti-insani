<?php

namespace Tests\Feature;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class QuizTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private mixed $class;

    private mixed $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = $this->makeClass('X TKJ 1');
        $this->subject = $this->makeSubject('Jaringan', 'JAR');
        $this->teacher = $this->makeTeacher();
        $this->assignTeacher($this->teacher, $this->class, $this->subject);
        $this->student = $this->makeStudent($this->class, 'Andi', '24001');
    }

    public function test_teacher_builds_and_publishes_a_quiz(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.quizzes.store'), [
            'teaching' => "{$this->subject->id}-{$this->class->id}",
            'title' => 'Kuis Topologi',
            'opens_at' => now()->format('Y-m-d\TH:i'),
            'closes_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'duration_minutes' => 30,
            'shuffles' => '1',
        ])->assertRedirect();

        $quiz = Quiz::sole();
        $this->assertSame(Quiz::STATUS_DRAFT, $quiz->status);
        $this->assertTrue($quiz->shuffles);

        $this->actingAs($this->teacher)->patch(route('teacher.quizzes.publish', $quiz))->assertSessionHas('error');

        $this->actingAs($this->teacher)->post(route('teacher.quizzes.questions.store', $quiz), ['questions' => [[
            'question' => 'Topologi paling umum?',
            'options' => ['Bintang', '', 'Cincin', null, null],
            'correct_option' => 2,
        ]]])->assertRedirect(route('teacher.quizzes.show', $quiz));

        $question = $quiz->questions()->with('options')->sole();
        $this->assertSame(['Bintang', 'Cincin'], $question->options->pluck('text')->all());
        $this->assertSame('Cincin', $question->options->firstWhere('is_correct')->text);

        $this->actingAs($this->teacher)->patch(route('teacher.quizzes.publish', $quiz))->assertSessionHas('success');
        $this->assertTrue($quiz->fresh()->isPublished());
    }

    public function test_question_needs_two_options_and_a_filled_correct_answer(): void
    {
        $quiz = $this->makeQuiz();

        $this->actingAs($this->teacher)->post(route('teacher.quizzes.questions.store', $quiz), ['questions' => [[
            'question' => 'Soal',
            'options' => ['Satu', ''],
            'correct_option' => 1,
        ]]])->assertSessionHasErrors(['questions.0.options', 'questions.0.correct_option']);
    }

    public function test_teacher_cannot_use_a_class_they_do_not_teach_or_open_another_teachers_quiz(): void
    {
        $otherClass = $this->makeClass('X TKJ 2');

        $this->actingAs($this->teacher)->post(route('teacher.quizzes.store'), [
            'teaching' => "{$this->subject->id}-{$otherClass->id}",
            'title' => 'Kuis',
            'opens_at' => now()->format('Y-m-d\TH:i'),
            'closes_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'duration_minutes' => 30,
        ])->assertSessionHasErrors('teaching');

        $this->actingAs($this->makeTeacher('Guru Lain', '199001012015011002'))
            ->get(route('teacher.quizzes.show', $this->makeQuiz()))
            ->assertForbidden();
    }

    /**
     * @return array<string, array{int, int, float}>
     */
    public static function equalScoreCases(): array
    {
        return [
            'one of four' => [4, 1, 25.0],
            'three of four' => [3 + 1, 3, 75.0],
            'one of five' => [5, 1, 20.0],
            'one of six' => [6, 1, 16.67],
            'five of six' => [6, 5, 83.33],
            'all of three' => [3, 3, 100.0],
            'all of six' => [6, 6, 100.0],
            'none of four' => [4, 0, 0.0],
        ];
    }

    #[DataProvider('equalScoreCases')]
    public function test_every_question_is_worth_the_same_and_the_maximum_is_100(int $questionCount, int $correctCount, float $expectedScore): void
    {
        $quiz = $this->makeQuiz();
        $questions = Collection::times($questionCount, fn () => $this->addQuestion($quiz));

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $attempt = QuizAttempt::sole();

        foreach ($questions->take($correctCount) as $question) {
            $attempt->saveAnswer($question->id, $question->options->firstWhere('is_correct', true)->id);
        }

        $attempt->finalize();

        $this->assertSame($expectedScore, $attempt->score);
    }

    public function test_teacher_sees_the_value_of_each_question_adapt_to_the_question_count(): void
    {
        $quiz = $this->makeQuiz();
        Collection::times(4, fn () => $this->addQuestion($quiz));

        $this->actingAs($this->teacher)->get(route('teacher.quizzes.show', $quiz))->assertSee('masing-masing bernilai 25)');

        $this->addQuestion($quiz);
        $this->actingAs($this->teacher)->get(route('teacher.quizzes.show', $quiz))->assertSee('masing-masing bernilai 20)');

        $this->addQuestion($quiz);
        $this->actingAs($this->teacher)->get(route('teacher.quizzes.show', $quiz))->assertSee('masing-masing bernilai 16,67)');
    }

    public function test_student_takes_a_quiz_and_is_graded(): void
    {
        $quiz = $this->makeQuiz();
        $easy = $this->addQuestion($quiz);
        $hard = $this->addQuestion($quiz);

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz))->assertRedirect(route('student.quizzes.attempt', $quiz));

        $this->actingAs($this->student)->post(route('student.quizzes.next', $quiz), [
            'step' => 0,
            'answers' => [$easy->id => $easy->options->firstWhere('is_correct', false)->id],
        ])->assertRedirect(route('student.quizzes.attempt', $quiz));

        $this->actingAs($this->student)->postJson(route('student.quizzes.answer', $quiz), [
            'question_id' => $hard->id,
            'option_id' => $hard->options->firstWhere('is_correct', true)->id,
        ])->assertOk();

        $this->actingAs($this->student)->post(route('student.quizzes.submit', $quiz))->assertRedirect(route('student.quizzes.show', $quiz));

        $this->assertSame(50.0, QuizAttempt::sole()->score);

        $this->actingAs($this->student)->get(route('student.quizzes.show', $quiz))->assertSee('50,0');
    }

    public function test_student_page_never_contains_the_correct_answer_marker(): void
    {
        $quiz = $this->makeQuiz();
        $this->addQuestion($quiz);
        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));

        $response = $this->actingAs($this->student)->get(route('student.quizzes.attempt', $quiz));

        $response->assertOk()->assertSee('Pilihan 1');
        $response->assertDontSee('is_correct');
        $response->assertDontSee('Jawaban benar');
    }

    public function test_only_one_attempt_is_allowed_and_a_submitted_quiz_cannot_be_changed(): void
    {
        $quiz = $this->makeQuiz();
        $question = $this->addQuestion($quiz);

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $this->assertSame(1, QuizAttempt::count());

        $this->actingAs($this->student)->post(route('student.quizzes.submit', $quiz), [
            'answers' => [$question->id => $question->options->firstWhere('is_correct', false)->id],
        ]);
        $this->assertSame(0.0, QuizAttempt::sole()->score);

        $correct = $question->options->firstWhere('is_correct', true)->id;

        $this->actingAs($this->student)->postJson(route('student.quizzes.answer', $quiz), ['question_id' => $question->id, 'option_id' => $correct])->assertStatus(409);
        $this->actingAs($this->student)->post(route('student.quizzes.submit', $quiz), ['answers' => [$question->id => $correct]]);

        $this->assertSame(0.0, QuizAttempt::sole()->score);
    }

    public function test_answers_after_the_deadline_are_ignored_and_saved_work_is_graded(): void
    {
        $quiz = $this->makeQuiz(['duration_minutes' => 10]);
        $first = $this->addQuestion($quiz);
        $second = $this->addQuestion($quiz);

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $this->actingAs($this->student)->postJson(route('student.quizzes.answer', $quiz), [
            'question_id' => $first->id,
            'option_id' => $first->options->firstWhere('is_correct', true)->id,
        ]);
        $this->actingAs($this->student)->post(route('student.quizzes.next', $quiz), ['step' => 0]);

        $this->travel(11)->minutes();

        $this->actingAs($this->student)->postJson(route('student.quizzes.answer', $quiz), [
            'question_id' => $second->id,
            'option_id' => $second->options->firstWhere('is_correct', true)->id,
        ])->assertStatus(409);

        $this->actingAs($this->student)->post(route('student.quizzes.submit', $quiz), [
            'answers' => [$second->id => $second->options->firstWhere('is_correct', true)->id],
        ]);

        $this->assertSame(50.0, QuizAttempt::sole()->score);
    }

    public function test_abandoned_attempt_is_graded_when_someone_next_views_it(): void
    {
        $quiz = $this->makeQuiz(['duration_minutes' => 10]);
        $question = $this->addQuestion($quiz);

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $this->actingAs($this->student)->postJson(route('student.quizzes.answer', $quiz), [
            'question_id' => $question->id,
            'option_id' => $question->options->firstWhere('is_correct', true)->id,
        ]);

        $this->travel(11)->minutes();

        $this->actingAs($this->teacher)->get(route('teacher.quizzes.show', $quiz))->assertOk()->assertSee('100,0');

        $attempt = QuizAttempt::sole();
        $this->assertSame(100.0, $attempt->score);
        $this->assertTrue($attempt->submitted_at->equalTo($attempt->deadline_at));
    }

    public function test_deadline_is_capped_by_the_closing_time(): void
    {
        $quiz = $this->makeQuiz(['duration_minutes' => 60, 'closes_at' => now()->addMinutes(5)]);
        $this->addQuestion($quiz);

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));

        $this->assertTrue(QuizAttempt::sole()->deadline_at->equalTo($quiz->closes_at));
    }

    public function test_quiz_cannot_be_started_outside_its_window_or_while_draft(): void
    {
        $upcoming = $this->makeQuiz(['opens_at' => now()->addHour()]);
        $closed = $this->makeQuiz(['opens_at' => now()->subDays(2), 'closes_at' => now()->subDay()]);
        $draft = $this->makeQuiz(['status' => Quiz::STATUS_DRAFT]);

        foreach ([$upcoming, $closed, $draft] as $quiz) {
            $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz))->assertForbidden();
        }

        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_scheduled_quiz_opens_and_closes_by_itself_without_any_teacher_action(): void
    {
        $quiz = $this->makeQuiz(['title' => 'Kuis Terjadwal', 'opens_at' => now()->addHour(), 'closes_at' => now()->addHours(2)]);
        $this->addQuestion($quiz);

        $this->assertSame('Terjadwal', $quiz->availabilityLabel());
        $this->actingAs($this->student)->get(route('student.quizzes.index'))->assertDontSee('Kuis Terjadwal');
        $this->actingAs($this->student)->get(route('student.quizzes.show', $quiz))->assertForbidden();

        $this->travel(90)->minutes();

        $this->assertSame('Dibuka', $quiz->fresh()->availabilityLabel());
        $this->actingAs($this->student)->get(route('student.quizzes.index'))->assertSee('Kuis Terjadwal')->assertSee('Kerjakan');

        $this->travel(1)->hours();

        $this->assertSame('Draf', $quiz->fresh()->availabilityLabel());
        $this->actingAs($this->student)->get(route('student.quizzes.index'))->assertDontSee('Kuis Terjadwal');
        $this->actingAs($this->student)->get(route('student.quizzes.show', $quiz))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('teacher.quizzes.show', $quiz))->assertSee('Waktu kuis sudah berakhir')->assertDontSee('Batalkan Jadwal');
    }

    public function test_unscheduled_draft_never_goes_live_when_its_time_comes(): void
    {
        $quiz = $this->makeQuiz(['title' => 'Kuis Setengah Jadi', 'status' => Quiz::STATUS_DRAFT]);
        $this->addQuestion($quiz);

        $this->actingAs($this->student)->get(route('student.quizzes.index'))->assertDontSee('Kuis Setengah Jadi');
        $this->assertFalse($quiz->isOpen());
    }

    public function test_student_keeps_their_score_after_closing_and_it_stays_in_the_recap(): void
    {
        $quiz = $this->makeQuiz(['title' => 'Kuis Selesai']);
        $question = $this->addQuestion($quiz);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $this->actingAs($this->student)->post(route('student.quizzes.submit', $quiz), [
            'answers' => [$question->id => $question->options->firstWhere('is_correct', true)->id],
        ]);

        $this->travelTo($quiz->closes_at->copy()->addMinute());

        $this->actingAs($this->student)->get(route('student.quizzes.index'))->assertSee('Kuis Selesai')->assertSee('Lihat Nilai');
        $this->actingAs($this->student)->get(route('student.quizzes.show', $quiz))->assertOk()->assertSee('100,0');
        $this->actingAs($admin)->get(route('admin.grades.index', ['class_id' => $this->class->id, 'subject_id' => $this->subject->id]))->assertSee('Kuis: Kuis Selesai');
    }

    public function test_closed_quiz_can_be_reopened_by_changing_the_schedule(): void
    {
        $quiz = $this->makeQuiz(['title' => 'Kuis Ulang', 'opens_at' => now()->subDays(2), 'closes_at' => now()->subDay()]);
        $this->addQuestion($quiz);

        $this->actingAs($this->teacher)->put(route('teacher.quizzes.update', $quiz), [
            'teaching' => "{$this->subject->id}-{$this->class->id}",
            'title' => 'Kuis Ulang',
            'opens_at' => now()->subMinute()->format('Y-m-d\TH:i'),
            'closes_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'duration_minutes' => 30,
        ])->assertRedirect();

        $this->actingAs($this->student)->get(route('student.quizzes.index'))->assertSee('Kuis Ulang');
    }

    public function test_draft_past_its_closing_time_cannot_be_scheduled(): void
    {
        $quiz = $this->makeQuiz(['status' => Quiz::STATUS_DRAFT, 'opens_at' => now()->subDays(2), 'closes_at' => now()->subDay()]);
        $this->addQuestion($quiz);

        $this->actingAs($this->teacher)->patch(route('teacher.quizzes.publish', $quiz))->assertSessionHas('error');

        $this->assertFalse($quiz->fresh()->isPublished());
    }

    public function test_student_from_another_class_is_blocked(): void
    {
        $outsider = $this->makeStudent($this->makeClass('X TKJ 2'), 'Bunga', '24002');

        $this->actingAs($outsider)->get(route('student.quizzes.show', $this->makeQuiz()))->assertForbidden();
    }

    public function test_answer_from_another_question_is_rejected(): void
    {
        $quiz = $this->makeQuiz();
        $question = $this->addQuestion($quiz);
        $foreign = $this->addQuestion($this->makeQuiz());

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));

        $this->actingAs($this->student)->postJson(route('student.quizzes.answer', $quiz), [
            'question_id' => $question->id,
            'option_id' => $foreign->options->first()->id,
        ])->assertStatus(422);
    }

    public function test_shuffled_order_stays_the_same_for_a_student_and_keeps_every_question(): void
    {
        $quiz = $this->makeQuiz(['shuffles' => true]);
        $questions = Collection::times(5, fn () => $this->addQuestion($quiz, options: 4));

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $attempt = QuizAttempt::sole();

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));

        $this->assertSame($attempt->question_order, QuizAttempt::sole()->question_order);
        $this->assertEqualsCanonicalizing($questions->pluck('id')->all(), array_column($attempt->question_order, 'question'));
    }

    public function test_questions_and_publishing_lock_once_a_student_has_started(): void
    {
        $quiz = $this->makeQuiz();
        $question = $this->addQuestion($quiz);
        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));

        $this->actingAs($this->teacher)->delete(route('teacher.quizzes.questions.destroy', [$quiz, $question]))->assertSessionHas('error');
        $this->actingAs($this->teacher)->patch(route('teacher.quizzes.publish', $quiz))->assertSessionHas('error');
        $this->actingAs($this->teacher)->delete(route('teacher.quizzes.destroy', $quiz))->assertSessionHas('error');

        $this->assertModelExists($question);
        $this->assertTrue($quiz->fresh()->isPublished());
    }

    public function test_score_appears_in_the_grade_recap_and_absent_students_count_as_missing(): void
    {
        $quiz = $this->makeQuiz(['title' => 'Bab Satu']);
        $question = $this->addQuestion($quiz);
        $absent = $this->makeStudent($this->class, 'Bunga', '24002');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $this->actingAs($this->student)->post(route('student.quizzes.submit', $quiz), [
            'answers' => [$question->id => $question->options->firstWhere('is_correct', true)->id],
        ]);

        $this->travelTo($quiz->closes_at->copy()->addMinute());

        $this->actingAs($admin)->get(route('admin.grades.index', ['class_id' => $this->class->id, 'subject_id' => $this->subject->id]))
            ->assertOk()
            ->assertSee('Kuis: Bab Satu')
            ->assertViewHas('students', fn (Collection $students) => $students->firstWhere('id', $this->student->id)['average'] === 100.0
                && $students->firstWhere('id', $absent->id)['missing'] === 1);

        $this->actingAs($admin)->get(route('admin.grades.export', ['class_id' => $this->class->id]))->assertDownload();
    }

    public function test_admin_sees_quizzes_read_only_and_others_cannot(): void
    {
        $quiz = $this->makeQuiz(['title' => 'Kuis Akhir']);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.quizzes.index'))->assertOk()->assertSee('Kuis Akhir');
        $this->actingAs($admin)->get(route('teacher.quizzes.show', $quiz))->assertForbidden();
        $this->actingAs($this->student)->get(route('admin.quizzes.index'))->assertForbidden();
    }

    public function test_teacher_subject_and_class_with_quizzes_get_a_message_instead_of_a_crash(): void
    {
        $this->makeQuiz();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->delete(route('admin.teachers.destroy', $this->teacher->teacherProfile))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('admin.subjects.destroy', $this->subject))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('admin.classes.destroy', $this->class))->assertSessionHas('error');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeQuiz(array $attributes = []): Quiz
    {
        return Quiz::factory()->create(array_merge([
            'subject_id' => $this->subject->id,
            'class_id' => $this->class->id,
            'teacher_id' => $this->teacher->id,
        ], $attributes));
    }

    private function addQuestion(Quiz $quiz, int $options = 3): QuizQuestion
    {
        return QuizQuestion::factory()->withOptions($options)->create(['quiz_id' => $quiz->id])->load('options');
    }
}
