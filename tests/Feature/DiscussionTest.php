<?php

namespace Tests\Feature;

use App\Models\Classes;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class DiscussionTest extends TestCase
{
    use CreatesAcademicData, RefreshDatabase;

    private Classes $class;

    private Subject $subject;

    private User $teacher;

    private User $student;

    private string $room;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->class = $this->makeClass('X TKJ 1');
        $this->subject = $this->makeSubject('Jaringan Dasar', 'JD');
        $this->teacher = $this->makeTeacher();
        $this->student = $this->makeStudent($this->class, 'Andi', '24001');
        $this->assignTeacher($this->teacher, $this->class, $this->subject);
        $this->room = "{$this->class->id}:{$this->subject->id}";
    }

    public function test_student_and_teacher_can_start_a_discussion_with_an_image(): void
    {
        $this->actingAs($this->student)
            ->post(route('student.discussions.store'), [
                'room' => $this->room,
                'title' => 'Apa itu subnet?',
                'body' => 'Mohon penjelasannya.',
                'image' => UploadedFile::fake()->image('soal.png'),
            ])
            ->assertRedirect();

        $discussion = Discussion::sole();

        $this->assertSame($this->student->id, $discussion->user_id);
        Storage::disk('local')->assertExists($discussion->image_path);

        $this->actingAs($this->teacher)->get(route('teacher.discussions.index'))
            ->assertOk()
            ->assertSee('Apa itu subnet?');

        $this->actingAs($this->teacher)->get(route('teacher.discussions.image', $discussion))->assertOk();

        $this->actingAs($this->teacher)
            ->post(route('teacher.discussions.store'), ['room' => $this->room, 'title' => 'Topik guru', 'body' => 'Isi'])
            ->assertRedirect();

        $this->assertSame(2, Discussion::count());
    }

    public function test_discussions_stay_inside_their_class_and_subject(): void
    {
        $discussion = $this->makeDiscussion(['title' => 'Topik rahasia kelas']);

        $otherClass = $this->makeClass('X TKJ 2');
        $outsider = $this->makeStudent($otherClass, 'Bunga', '24002');
        $otherTeacher = $this->makeTeacher('Siti', '198001012005011002');
        $this->assignTeacher($otherTeacher, $otherClass, $this->subject);

        $this->actingAs($outsider)->get(route('student.discussions.index'))->assertDontSee('Topik rahasia kelas');
        $this->actingAs($outsider)->get(route('student.discussions.show', $discussion))->assertForbidden();
        $this->actingAs($outsider)->get(route('student.discussions.image', $discussion))->assertForbidden();
        $this->actingAs($outsider)->post(route('student.discussions.replies.store', $discussion), ['body' => 'Halo'])->assertForbidden();

        $this->actingAs($otherTeacher)->get(route('teacher.discussions.show', $discussion))->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('student.discussions.store'), ['room' => $this->room, 'title' => 'Masuk', 'body' => 'Isi'])
            ->assertForbidden();

        $this->assertSame(1, Discussion::count());
    }

    public function test_student_cannot_post_to_a_subject_not_taught_in_their_class(): void
    {
        $otherSubject = $this->makeSubject('Basis Data', 'BD');

        $this->actingAs($this->student)
            ->post(route('student.discussions.store'), ['room' => "{$this->class->id}:{$otherSubject->id}", 'title' => 'Judul', 'body' => 'Isi'])
            ->assertForbidden();
    }

    public function test_room_filter_narrows_the_list(): void
    {
        $otherSubject = $this->makeSubject('Basis Data', 'BD');
        $this->assignTeacher($this->teacher, $this->class, $otherSubject);

        $this->makeDiscussion(['title' => 'Topik jaringan']);
        $this->makeDiscussion(['title' => 'Topik basis data', 'subject_id' => $otherSubject->id]);

        $this->actingAs($this->student)->get(route('student.discussions.index', ['room' => $this->room]))
            ->assertSee('Topik jaringan')
            ->assertDontSee('Topik basis data');
    }

    public function test_a_reply_moves_the_discussion_up_and_pinned_stays_first(): void
    {
        $this->travel(-2)->hours();
        $pinned = $this->makeDiscussion(['title' => 'Topik tersemat', 'is_pinned' => true]);
        $quiet = $this->makeDiscussion(['title' => 'Topik sepi']);
        $this->travel(1)->hours();
        $active = $this->makeDiscussion(['title' => 'Topik ramai']);
        $this->travelBack();

        $this->actingAs($this->student)->post(route('student.discussions.replies.store', $quiet), ['body' => 'Balasan baru'])->assertRedirect();

        $this->actingAs($this->student)->get(route('student.discussions.index'))
            ->assertSeeInOrder([$pinned->title, $quiet->title, $active->title]);
    }

    public function test_only_the_teacher_can_pin_close_and_choose_the_best_answer(): void
    {
        $discussion = $this->makeDiscussion();
        $reply = $this->makeReply($discussion);

        $this->actingAs($this->student)->patch(route('student.discussions.pin', $discussion))->assertForbidden();
        $this->actingAs($this->student)->patch(route('student.discussions.close', $discussion))->assertForbidden();
        $this->actingAs($this->student)->patch(route('student.discussions.replies.best', [$discussion, $reply]))->assertForbidden();

        $this->actingAs($this->teacher)->patch(route('teacher.discussions.pin', $discussion))->assertRedirect();
        $this->assertTrue($discussion->fresh()->is_pinned);
    }

    public function test_only_one_reply_can_be_the_best_and_it_is_listed_first(): void
    {
        $discussion = $this->makeDiscussion();
        $first = $this->makeReply($discussion, ['body' => 'Balasan pertama']);
        $second = $this->makeReply($discussion, ['body' => 'Balasan kedua']);

        $this->actingAs($this->teacher)->patch(route('teacher.discussions.replies.best', [$discussion, $first]));
        $this->actingAs($this->teacher)->patch(route('teacher.discussions.replies.best', [$discussion, $second]));

        $this->assertFalse($first->fresh()->is_best);
        $this->assertTrue($second->fresh()->is_best);

        $this->actingAs($this->student)->get(route('student.discussions.show', $discussion))
            ->assertSee('Jawaban terbaik')
            ->assertSeeInOrder(['Balasan kedua', 'Balasan pertama']);

        $this->actingAs($this->teacher)->patch(route('teacher.discussions.replies.best', [$discussion, $second]));
        $this->assertFalse($second->fresh()->is_best);
    }

    public function test_a_reply_is_shown_directly_below_the_reply_it_answers(): void
    {
        $discussion = $this->makeDiscussion();
        $original = $this->makeReply($discussion, ['body' => 'Pertanyaan lanjutan dari Andi']);
        $this->makeReply($discussion, ['body' => 'Balasan lain yang lebih dulu']);

        $this->actingAs($this->teacher)
            ->post(route('teacher.discussions.replies.store', $discussion), ['body' => 'Jawaban untuk Andi', 'parent_id' => $original->id])
            ->assertRedirectContains('#reply-');

        $answer = DiscussionReply::latest('id')->first();

        $this->assertSame($original->id, $answer->parent_id);

        $this->actingAs($this->student)->get(route('student.discussions.show', $discussion))
            ->assertSee('data-reply-to="'.$original->id.'"', false)
            ->assertSee('data-reply-children', false)
            ->assertSeeInOrder(['Pertanyaan lanjutan dari Andi', 'Jawaban untuk Andi', 'Balasan lain yang lebih dulu', 'Kirim Balasan']);
    }

    public function test_a_reply_cannot_answer_a_reply_from_another_discussion(): void
    {
        $discussion = $this->makeDiscussion();
        $elsewhere = $this->makeReply($this->makeDiscussion());

        $this->actingAs($this->student)
            ->post(route('student.discussions.replies.store', $discussion), ['body' => 'Halo', 'parent_id' => $elsewhere->id])
            ->assertSessionHasErrors('parent_id');

        $this->assertSame(0, $discussion->replies()->count());
    }

    public function test_deleting_a_reply_keeps_the_answers_to_it(): void
    {
        $discussion = $this->makeDiscussion();
        $original = $this->makeReply($discussion);
        $answer = $this->makeReply($discussion, ['parent_id' => $original->id, 'body' => 'Tetap ada']);

        $this->actingAs($this->student)->delete(route('student.discussions.replies.destroy', [$discussion, $original]))->assertRedirect();

        $this->assertNull($answer->fresh()->parent_id);
        $this->actingAs($this->student)->get(route('student.discussions.show', $discussion))->assertSee('Tetap ada');
    }

    public function test_images_are_framed_as_squares_that_open_the_full_picture(): void
    {
        $discussion = $this->makeDiscussion(['image_path' => 'discussion-images/topik.png']);
        $reply = $this->makeReply($discussion, ['image_path' => 'discussion-images/balasan.png']);

        $this->actingAs($this->student)->get(route('student.discussions.show', $discussion))
            ->assertSee('alt="Gambar diskusi" class="rounded bg-light mt-3" style="width: 100%; max-width: 280px; aspect-ratio: 1 / 1;', false)
            ->assertSee('alt="Gambar balasan" class="rounded bg-light mt-3" style="width: 100%; max-width: 280px; aspect-ratio: 1 / 1;', false)
            ->assertSee('<a href="'.route('student.discussions.image', $discussion).'" target="_blank"', false)
            ->assertSee('<a href="'.route('student.discussions.replies.image', [$discussion, $reply]).'" target="_blank"', false);
    }

    public function test_a_closed_discussion_refuses_replies_until_reopened(): void
    {
        $discussion = $this->makeDiscussion();

        $this->actingAs($this->teacher)->patch(route('teacher.discussions.close', $discussion));

        $this->actingAs($this->student)->get(route('student.discussions.show', $discussion))
            ->assertSee('Diskusi ini sudah ditutup.')
            ->assertDontSee('Kirim Balasan');
        $this->actingAs($this->student)->post(route('student.discussions.replies.store', $discussion), ['body' => 'Halo'])->assertForbidden();

        $this->actingAs($this->teacher)->patch(route('teacher.discussions.close', $discussion));
        $this->actingAs($this->student)->post(route('student.discussions.replies.store', $discussion), ['body' => 'Halo'])->assertRedirect();

        $this->assertSame(1, $discussion->replies()->count());
    }

    public function test_students_delete_only_their_own_posts_and_teachers_delete_any(): void
    {
        $classmate = $this->makeStudent($this->class, 'Citra', '24003');
        $discussion = $this->makeDiscussion();
        $ownReply = $this->makeReply($discussion, ['user_id' => $classmate->id]);
        $studentReply = $this->makeReply($discussion);

        $this->actingAs($classmate)->delete(route('student.discussions.destroy', $discussion))->assertForbidden();
        $this->actingAs($classmate)->delete(route('student.discussions.replies.destroy', [$discussion, $studentReply]))->assertForbidden();

        $this->actingAs($classmate)->delete(route('student.discussions.replies.destroy', [$discussion, $ownReply]))->assertRedirect();
        $this->actingAs($this->teacher)->delete(route('teacher.discussions.replies.destroy', [$discussion, $studentReply]))->assertRedirect();

        $this->assertSame(0, $discussion->replies()->count());
    }

    public function test_deleting_a_discussion_removes_its_replies_and_images(): void
    {
        $discussionImage = UploadedFile::fake()->image('a.png')->store(Discussion::IMAGE_DIRECTORY, 'local');
        $replyImage = UploadedFile::fake()->image('b.png')->store(Discussion::IMAGE_DIRECTORY, 'local');
        $discussion = $this->makeDiscussion(['image_path' => $discussionImage]);
        $this->makeReply($discussion, ['image_path' => $replyImage]);

        $this->actingAs($this->student)->delete(route('student.discussions.destroy', $discussion))->assertRedirect();

        $this->assertSame(0, DiscussionReply::count());
        Storage::disk('local')->assertMissing($discussionImage);
        Storage::disk('local')->assertMissing($replyImage);
    }

    public function test_reply_from_another_discussion_is_not_reachable(): void
    {
        $discussion = $this->makeDiscussion();
        $reply = $this->makeReply($this->makeDiscussion());

        $this->actingAs($this->teacher)->patch(route('teacher.discussions.replies.best', [$discussion, $reply]))->assertNotFound();
        $this->actingAs($this->teacher)->delete(route('teacher.discussions.replies.destroy', [$discussion, $reply]))->assertNotFound();
    }

    public function test_posts_survive_when_their_author_is_deleted(): void
    {
        $discussion = $this->makeDiscussion();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->delete(route('admin.students.destroy', $this->student->studentProfile))->assertRedirect();

        $this->actingAs($this->teacher)->get(route('teacher.discussions.show', $discussion))
            ->assertOk()
            ->assertSee('Pengguna dihapus');
    }

    public function test_oversized_image_is_rejected_and_keeps_the_written_text(): void
    {
        $this->actingAs($this->student)
            ->from(route('student.discussions.create'))
            ->post(route('student.discussions.store'), [
                'room' => $this->room,
                'title' => 'Judul panjang',
                'body' => 'Isi',
                'image' => UploadedFile::fake()->image('besar.png')->size(3000),
            ])
            ->assertRedirect(route('student.discussions.create'))
            ->assertSessionHasErrors(['image' => 'Ukuran gambar maksimal 2 MB.']);

        $this->assertSame('Judul panjang', session()->getOldInput('title'));
        $this->assertSame(0, Discussion::count());
    }

    public function test_admins_have_no_discussion_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('student.discussions.index'))->assertForbidden();
    }

    public function test_class_and_subject_with_discussions_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->makeDiscussion();

        $this->actingAs($admin)->delete(route('admin.subjects.destroy', $this->subject))->assertSessionHas('error');

        $this->assertModelExists($this->subject);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeDiscussion(array $attributes = []): Discussion
    {
        return Discussion::factory()->create($attributes + [
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'user_id' => $this->student->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeReply(Discussion $discussion, array $attributes = []): DiscussionReply
    {
        return DiscussionReply::factory()->create($attributes + [
            'discussion_id' => $discussion->id,
            'user_id' => $this->student->id,
        ]);
    }
}
