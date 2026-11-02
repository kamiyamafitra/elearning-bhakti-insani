<?php

namespace Tests\Feature;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class QuizPresentationTest extends TestCase
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

        Storage::fake('local');

        $this->class = $this->makeClass('X TKJ 1');
        $this->subject = $this->makeSubject('Jaringan', 'JAR');
        $this->teacher = $this->makeTeacher();
        $this->assignTeacher($this->teacher, $this->class, $this->subject);
        $this->student = $this->makeStudent($this->class, 'Andi', '24001');
    }

    public function test_lists_have_a_dedicated_button_instead_of_a_title_link(): void
    {
        $quiz = $this->makeQuiz();
        $this->addQuestion($quiz);
        $showUrl = route('teacher.quizzes.show', $quiz);
        $studentUrl = route('student.quizzes.show', $quiz);

        $this->actingAs($this->teacher)->get(route('teacher.quizzes.index'))
            ->assertSee("<a href=\"{$showUrl}\" class=\"btn btn-sm btn-primary\">Buka</a>", false);

        $this->actingAs($this->student)->get(route('student.quizzes.index'))
            ->assertSee("<a href=\"{$studentUrl}\" class=\"btn btn-sm btn-primary\">Kerjakan</a>", false);
    }

    public function test_student_button_follows_their_progress(): void
    {
        $quiz = $this->makeQuiz();
        $this->addQuestion($quiz);

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $this->actingAs($this->student)->get(route('student.quizzes.index'))->assertSee('Lanjutkan');

        $this->actingAs($this->student)->post(route('student.quizzes.submit', $quiz));
        $this->actingAs($this->student)->get(route('student.quizzes.index'))->assertSee('Lihat Nilai');
    }

    public function test_only_the_current_question_is_sent_to_the_student(): void
    {
        $quiz = $this->makeQuiz();
        $first = QuizQuestion::factory()->withOptions()->create(['quiz_id' => $quiz->id, 'question' => 'Pertanyaan pertama', 'position' => 1]);
        QuizQuestion::factory()->withOptions()->create(['quiz_id' => $quiz->id, 'question' => 'Pertanyaan kedua', 'position' => 2]);
        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));

        $this->actingAs($this->student)->get(route('student.quizzes.attempt', $quiz))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Soal 1 dari 2')
            ->assertSee('Pertanyaan pertama')
            ->assertDontSee('Pertanyaan kedua')
            ->assertSee('Berikutnya')
            ->assertDontSee('Sebelumnya')
            ->assertDontSee('Kumpulkan Jawaban');

        $this->actingAs($this->student)->post(route('student.quizzes.next', $quiz), ['step' => 0, 'answers' => [$first->id => $first->options->first()->id]]);

        $this->actingAs($this->student)->get(route('student.quizzes.attempt', $quiz))
            ->assertSee('Soal 2 dari 2')
            ->assertSee('Pertanyaan kedua')
            ->assertDontSee('Pertanyaan pertama')
            ->assertSee('Kumpulkan Jawaban');
    }

    public function test_earlier_answers_cannot_be_changed_by_any_route(): void
    {
        $quiz = $this->makeQuiz();
        $first = $this->addQuestion($quiz);
        $second = $this->addQuestion($quiz);
        $wrong = $first->options->firstWhere('is_correct', false)->id;
        $correct = $first->options->firstWhere('is_correct', true)->id;

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $this->actingAs($this->student)->post(route('student.quizzes.next', $quiz), ['step' => 0, 'answers' => [$first->id => $wrong]]);

        $this->actingAs($this->student)->postJson(route('student.quizzes.answer', $quiz), ['question_id' => $first->id, 'option_id' => $correct])->assertStatus(422);
        $this->actingAs($this->student)->post(route('student.quizzes.next', $quiz), ['step' => 0, 'answers' => [$first->id => $correct]]);
        $this->actingAs($this->student)->post(route('student.quizzes.submit', $quiz), ['answers' => [$first->id => $correct]]);

        $this->assertSame(0.0, QuizAttempt::sole()->score);
        $this->assertDatabaseHas('quiz_answers', ['quiz_question_id' => $first->id, 'quiz_option_id' => $wrong]);
        $this->assertNotNull($second->id);
    }

    public function test_later_questions_cannot_be_answered_early(): void
    {
        $quiz = $this->makeQuiz();
        $this->addQuestion($quiz);
        $second = $this->addQuestion($quiz);
        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));

        $this->actingAs($this->student)->postJson(route('student.quizzes.answer', $quiz), [
            'question_id' => $second->id,
            'option_id' => $second->options->first()->id,
        ])->assertStatus(422);
    }

    public function test_double_click_on_next_moves_only_one_question_and_skipping_is_allowed(): void
    {
        $quiz = $this->makeQuiz();
        $this->addQuestion($quiz);
        $this->addQuestion($quiz);
        $this->addQuestion($quiz);
        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));

        $this->actingAs($this->student)->post(route('student.quizzes.next', $quiz), ['step' => 0]);
        $this->actingAs($this->student)->post(route('student.quizzes.next', $quiz), ['step' => 0]);

        $this->assertSame(1, QuizAttempt::sole()->current_step);

        $this->actingAs($this->student)->post(route('student.quizzes.next', $quiz), ['step' => 1]);
        $this->actingAs($this->student)->post(route('student.quizzes.next', $quiz), ['step' => 2]);

        $this->assertSame(2, QuizAttempt::sole()->current_step);
        $this->actingAs($this->student)->get(route('student.quizzes.attempt', $quiz))->assertSee('Soal 3 dari 3');
    }

    public function test_next_after_the_deadline_finishes_the_attempt(): void
    {
        $quiz = $this->makeQuiz(['duration_minutes' => 10]);
        $this->addQuestion($quiz);
        $this->addQuestion($quiz);
        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));

        $this->travel(11)->minutes();

        $this->actingAs($this->student)->post(route('student.quizzes.next', $quiz), ['step' => 0])->assertRedirect(route('student.quizzes.show', $quiz));

        $this->assertTrue(QuizAttempt::sole()->isSubmitted());
    }

    public function test_image_of_a_later_question_stays_hidden_until_the_student_reaches_it(): void
    {
        $quiz = $this->makeQuiz();
        $this->addQuestion($quiz);
        $second = $this->addQuestion($quiz, 'lms/quiz-questions/kedua.png');
        $imageUrl = route('student.quizzes.questions.image', [$quiz, $second]);

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $this->actingAs($this->student)->get($imageUrl)->assertNotFound();

        $this->actingAs($this->student)->post(route('student.quizzes.next', $quiz), ['step' => 0]);
        $this->actingAs($this->student)->get($imageUrl)->assertOk();
    }

    public function test_teacher_can_add_replace_and_remove_a_question_image(): void
    {
        $quiz = $this->makeQuiz(['status' => Quiz::STATUS_DRAFT]);
        $payload = ['question' => 'Perhatikan gambar', 'options' => ['A', 'B'], 'correct_option' => 0];

        $this->actingAs($this->teacher)->post(route('teacher.quizzes.questions.store', $quiz), ['questions' => [$payload + [
            'image' => UploadedFile::fake()->image('topologi.png', 200, 200),
        ]]])->assertRedirect(route('teacher.quizzes.show', $quiz));

        $question = QuizQuestion::sole();
        $firstImage = $question->image_path;
        Storage::disk('local')->assertExists($firstImage);

        $this->actingAs($this->teacher)->get(route('teacher.quizzes.questions.image', [$quiz, $question]))->assertOk();
        $this->actingAs($this->teacher)->get(route('teacher.quizzes.show', $quiz))->assertSee('Gambar soal 1');

        $this->actingAs($this->teacher)->put(route('teacher.quizzes.questions.update', [$quiz, $question]), $payload)->assertRedirect();
        $this->assertSame($firstImage, $question->fresh()->image_path);

        $this->actingAs($this->teacher)->put(route('teacher.quizzes.questions.update', [$quiz, $question]), $payload + [
            'image' => UploadedFile::fake()->image('baru.png', 200, 200),
        ]);
        $secondImage = $question->fresh()->image_path;
        Storage::disk('local')->assertMissing($firstImage);

        $this->actingAs($this->teacher)->put(route('teacher.quizzes.questions.update', [$quiz, $question]), $payload + ['remove_image' => '1']);
        Storage::disk('local')->assertMissing($secondImage);
        $this->assertNull($question->fresh()->image_path);
    }

    public function test_teacher_saves_several_questions_at_once_in_order_with_their_own_images(): void
    {
        $quiz = $this->makeQuiz(['status' => Quiz::STATUS_DRAFT]);
        $this->addQuestion($quiz);

        $this->actingAs($this->teacher)->post(route('teacher.quizzes.questions.store', $quiz), ['questions' => [
            3 => ['question' => 'Soal kedua', 'options' => ['A', 'B'], 'correct_option' => 1],
            7 => ['question' => 'Soal ketiga', 'options' => ['C', 'D', 'E'], 'correct_option' => 2, 'image' => UploadedFile::fake()->image('soal.png', 100, 100)],
        ]])->assertRedirect(route('teacher.quizzes.show', $quiz))->assertSessionHas('success', '2 soal berhasil ditambahkan.');

        $questions = $quiz->questions()->with('options')->get();

        $this->assertSame(['Soal kedua', 'Soal ketiga'], $questions->slice(1)->pluck('question')->values()->all());
        $this->assertSame([1, 2, 3], $questions->pluck('position')->all());
        $this->assertNull($questions[1]->image_path);
        Storage::disk('local')->assertExists($questions[2]->image_path);
        $this->assertSame('B', $questions[1]->options->firstWhere('is_correct')->text);
        $this->assertSame('E', $questions[2]->options->firstWhere('is_correct')->text);
    }

    public function test_one_bad_question_saves_nothing_and_keeps_what_was_typed(): void
    {
        $quiz = $this->makeQuiz(['status' => Quiz::STATUS_DRAFT]);
        $createUrl = route('teacher.quizzes.questions.create', $quiz);

        $response = $this->actingAs($this->teacher)->from($createUrl)->followingRedirects()->post(route('teacher.quizzes.questions.store', $quiz), ['questions' => [
            0 => ['question' => 'Soal baik', 'options' => ['A', 'B'], 'correct_option' => 0],
            4 => ['question' => 'Soal rusak', 'options' => ['A', ''], 'correct_option' => 0],
        ]]);

        $this->assertSame(0, $quiz->questions()->count());

        $response->assertSee('Soal belum disimpan')
            ->assertSee('Soal baik')
            ->assertSee('Soal rusak')
            ->assertSee('Isi minimal 2 pilihan jawaban.');
    }

    public function test_server_rejection_names_the_question_with_the_bad_image(): void
    {
        $quiz = $this->makeQuiz(['status' => Quiz::STATUS_DRAFT]);
        $createUrl = route('teacher.quizzes.questions.create', $quiz);

        $response = $this->actingAs($this->teacher)->from($createUrl)->followingRedirects()->post(route('teacher.quizzes.questions.store', $quiz), ['questions' => [
            2 => ['question' => 'Soal pertama', 'options' => ['A', 'B'], 'correct_option' => 0],
            8 => ['question' => 'Soal kedua', 'options' => ['C', 'D'], 'correct_option' => 1, 'image' => UploadedFile::fake()->create('besar.png', 3000, 'image/png')],
        ]]);

        $response->assertSee('Soal pertama')
            ->assertSee('Soal kedua')
            ->assertSee('href="#question-card-8"', false)
            ->assertSee('id="question-card-8"', false)
            ->assertSeeInOrder(['Soal 2</a>:', 'Ukuran gambar maksimal 2 MB.'], false);

        $this->assertSame(0, $quiz->questions()->count());
        $this->assertSame(2, substr_count($response->getContent(), 'Ukuran gambar maksimal 2 MB.'));
    }

    public function test_too_many_questions_at_once_are_rejected(): void
    {
        $quiz = $this->makeQuiz(['status' => Quiz::STATUS_DRAFT]);
        $question = ['question' => 'Soal', 'options' => ['A', 'B'], 'correct_option' => 0];

        $this->actingAs($this->teacher)->post(route('teacher.quizzes.questions.store', $quiz), ['questions' => array_fill(0, 21, $question)])
            ->assertSessionHasErrors('questions');
    }

    public function test_question_works_without_an_image_and_rejects_unsafe_files(): void
    {
        $quiz = $this->makeQuiz(['status' => Quiz::STATUS_DRAFT]);
        $payload = ['question' => 'Soal', 'options' => ['A', 'B'], 'correct_option' => 0];

        $this->actingAs($this->teacher)->post(route('teacher.quizzes.questions.store', $quiz), ['questions' => [$payload]])->assertSessionHasNoErrors();

        $this->actingAs($this->teacher)->post(route('teacher.quizzes.questions.store', $quiz), ['questions' => [$payload + [
            'image' => UploadedFile::fake()->createWithContent('gambar.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ]]])->assertSessionHasErrors('questions.0.image');
    }

    public function test_deleting_a_question_or_quiz_removes_its_image(): void
    {
        $quiz = $this->makeQuiz(['status' => Quiz::STATUS_DRAFT]);
        $first = $this->addQuestion($quiz, 'lms/quiz-questions/satu.png');
        $second = $this->addQuestion($quiz, 'lms/quiz-questions/dua.png');

        $this->actingAs($this->teacher)->delete(route('teacher.quizzes.questions.destroy', [$quiz, $first]));
        Storage::disk('local')->assertMissing($first->image_path);

        $this->actingAs($this->teacher)->delete(route('teacher.quizzes.destroy', $quiz));
        Storage::disk('local')->assertMissing($second->image_path);
    }

    public function test_student_sees_an_image_only_during_their_own_attempt(): void
    {
        $quiz = $this->makeQuiz();
        $question = $this->addQuestion($quiz, 'lms/quiz-questions/soal.png');
        $otherQuestion = $this->addQuestion($this->makeQuiz(), 'lms/quiz-questions/lain.png');
        $imageUrl = route('student.quizzes.questions.image', [$quiz, $question]);

        $this->actingAs($this->student)->get($imageUrl)->assertNotFound();

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $this->actingAs($this->student)->get(route('student.quizzes.attempt', $quiz))->assertSee('Gambar soal 1');
        $this->actingAs($this->student)->get($imageUrl)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->student)->get(route('student.quizzes.questions.image', [$quiz, $otherQuestion]))->assertNotFound();

        $outsider = $this->makeStudent($this->makeClass('X TKJ 2'), 'Bunga', '24002');
        $this->actingAs($outsider)->get($imageUrl)->assertForbidden();

        $this->actingAs($this->student)->post(route('student.quizzes.submit', $quiz));
        $this->actingAs($this->student)->get($imageUrl)->assertNotFound();
    }

    public function test_review_shows_right_wrong_and_unanswered_after_the_quiz_closes(): void
    {
        $quiz = $this->makeQuiz();
        $right = QuizQuestion::factory()->withOptions()->create(['quiz_id' => $quiz->id, 'question' => 'Soal dijawab benar', 'position' => 1]);
        $wrong = QuizQuestion::factory()->withOptions()->create(['quiz_id' => $quiz->id, 'question' => 'Soal dijawab salah', 'position' => 2]);
        QuizQuestion::factory()->withOptions()->create(['quiz_id' => $quiz->id, 'question' => 'Soal dilewati', 'position' => 3]);

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $this->actingAs($this->student)->post(route('student.quizzes.next', $quiz), ['step' => 0, 'answers' => [$right->id => $right->options->firstWhere('is_correct', true)->id]]);
        $this->actingAs($this->student)->post(route('student.quizzes.next', $quiz), ['step' => 1, 'answers' => [$wrong->id => $wrong->options->firstWhere('is_correct', false)->id]]);
        $this->actingAs($this->student)->post(route('student.quizzes.submit', $quiz));

        $this->travelTo($quiz->closes_at->copy()->addMinute());

        $this->actingAs($this->student)->get(route('student.quizzes.show', $quiz))
            ->assertOk()
            ->assertSeeInOrder(['Soal dijawab benar', 'Soal dijawab salah', 'Soal dilewati'])
            ->assertSee('Jawaban benar')
            ->assertSee('Jawaban Anda')
            ->assertSee('bi-person-check-fill', false)
            ->assertSee('bi-person-x-fill', false)
            ->assertViewHas('review', fn (Collection $review) => $review->pluck('status')->all() === ['correct', 'wrong', 'unanswered']);
    }

    public function test_correct_answers_stay_hidden_while_the_quiz_is_open(): void
    {
        $quiz = $this->makeQuiz();
        $question = QuizQuestion::factory()->withOptions()->create(['quiz_id' => $quiz->id, 'question' => 'Soal rahasia']);

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $this->actingAs($this->student)->post(route('student.quizzes.submit', $quiz), ['answers' => [$question->id => $question->options->first()->id]]);

        $this->actingAs($this->student)->get(route('student.quizzes.show', $quiz))
            ->assertSee('100,0')
            ->assertSee('dapat dilihat setelah kuis ditutup')
            ->assertDontSee('Soal rahasia')
            ->assertDontSee('bi-check-circle-fill', false)
            ->assertDontSee('bi-person-check-fill', false)
            ->assertDontSee('bi-person-x-fill', false)
            ->assertViewHas('review', null);
    }

    public function test_reopening_a_quiz_hides_the_review_again(): void
    {
        $quiz = $this->makeQuiz();
        $this->addQuestion($quiz);
        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $this->actingAs($this->student)->post(route('student.quizzes.submit', $quiz));

        $this->travelTo($quiz->closes_at->copy()->addMinute());
        $this->actingAs($this->student)->get(route('student.quizzes.show', $quiz))->assertViewHas('review', fn ($review) => $review !== null);

        $quiz->update(['closes_at' => now()->addHour()]);
        $this->actingAs($this->student)->get(route('student.quizzes.show', $quiz))->assertViewHas('review', null);
    }

    public function test_review_can_show_images_but_only_to_students_who_took_the_quiz(): void
    {
        $quiz = $this->makeQuiz();
        $question = $this->addQuestion($quiz, 'lms/quiz-questions/ulasan.png');
        $imageUrl = route('student.quizzes.questions.image', [$quiz, $question]);
        $other = $this->makeStudent($this->class, 'Bunga', '24002');

        $this->actingAs($this->student)->post(route('student.quizzes.start', $quiz));
        $this->actingAs($this->student)->post(route('student.quizzes.submit', $quiz));
        $this->actingAs($this->student)->get($imageUrl)->assertNotFound();

        $this->travelTo($quiz->closes_at->copy()->addMinute());

        $this->actingAs($this->student)->get($imageUrl)->assertOk();
        $this->actingAs($other)->get($imageUrl)->assertForbidden();
    }

    public function test_results_are_a_leaderboard_with_shared_ranks_and_unfinished_students_last(): void
    {
        $quiz = $this->makeQuiz();
        $this->addQuestion($quiz);
        $low = $this->makeStudent($this->class, 'Citra', '24003');
        $top = $this->makeStudent($this->class, 'Dewi', '24004');
        $tied = $this->makeStudent($this->class, 'Eka', '24005');
        $working = $this->makeStudent($this->class, 'Fajar', '24006');

        $this->finishAttempt($quiz, $low, 40, now()->subMinutes(30));
        $this->finishAttempt($quiz, $top, 90, now()->subMinutes(20));
        $this->finishAttempt($quiz, $tied, 90, now()->subMinutes(25));
        QuizAttempt::create(['quiz_id' => $quiz->id, 'student_id' => $working->id, 'question_order' => [], 'started_at' => now(), 'deadline_at' => now()->addMinutes(10)]);

        $this->actingAs($this->teacher)->get(route('teacher.quizzes.show', $quiz))
            ->assertSee('Peringkat Siswa')
            ->assertSeeInOrder(['Eka', 'Dewi', 'Citra', 'Fajar', 'Andi'])
            ->assertViewHas('leaderboard', function (Collection $rows): bool {
                return $rows->pluck('rank')->all() === [1, 1, 3, null, null]
                    && $rows->pluck('student.name')->all() === ['Eka', 'Dewi', 'Citra', 'Fajar', 'Andi'];
            });
    }

    private function finishAttempt(Quiz $quiz, User $student, float $score, mixed $submittedAt): void
    {
        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'question_order' => [],
            'started_at' => now()->subHour(),
            'deadline_at' => now()->addHour(),
            'submitted_at' => $submittedAt,
            'score' => $score,
        ]);
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

    private function addQuestion(Quiz $quiz, ?string $imagePath = null): QuizQuestion
    {
        if ($imagePath !== null) {
            Storage::disk('local')->put($imagePath, 'gambar');
        }

        return QuizQuestion::factory()->withOptions()->create(['quiz_id' => $quiz->id, 'image_path' => $imagePath]);
    }
}
