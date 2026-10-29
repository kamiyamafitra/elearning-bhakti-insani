<?php

namespace Tests\Feature\Student;

use App\Models\Announcement;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class StudentAreaTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
    }

    public function test_non_student_user_receives_forbidden_response(): void
    {
        $teacher = $this->makeTeacher();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($teacher)->get(route('student.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('student.dashboard'))->assertForbidden();
    }

    public function test_dashboard_displays_the_authenticated_students_real_data(): void
    {
        $class = $this->makeClass('10 PPLG');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $teacher = $this->makeTeacher('Wahyudi', '91289018590');

        $this->makeSchedule($subject, $class);
        $this->assignTeacher($teacher, $class, $subject);
        $this->makeAnnouncement(['title' => 'Ujian Tengah Semester']);
        $this->makeAnnouncement(['title' => 'Pengumuman Kelas Lain', 'visibility' => Announcement::VISIBILITY_CLASS, 'class_id' => 999]);

        $response = $this->actingAs($student)->get(route('student.dashboard'));

        $response->assertOk();
        $response->assertSee('Halo, Mulyadi');
        $response->assertSee('10 PPLG');
        $response->assertSee('Bahasa Jepang');
        $response->assertSee('Wahyudi');
        $response->assertSee('Ujian Tengah Semester');
        $response->assertDontSee('Pengumuman Kelas Lain');
        $response->assertSee(route('student.subjects.index'), false);
    }

    public function test_subject_page_only_lists_subjects_for_the_students_class(): void
    {
        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');
        $student = $this->makeStudent($class);
        $teacher = $this->makeTeacher('Wahyudi');

        $ownSubject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $otherSubject = $this->makeSubject('Matematika', 'MTK');

        $this->makeSchedule($ownSubject, $class);
        $this->assignTeacher($teacher, $class, $ownSubject);
        $this->makeSchedule($otherSubject, $otherClass);

        $response = $this->actingAs($student)->get(route('student.subjects.index'));

        $response->assertOk();
        $response->assertSee('Bahasa Jepang');
        $response->assertSee('BJPN');
        $response->assertSee('Wahyudi');
        $response->assertDontSee('Matematika');
        $response->assertDontSee('11 PPLG');
    }

    public function test_subject_details_can_be_opened_for_the_students_class(): void
    {
        $class = $this->makeClass('10 PPLG');
        $student = $this->makeStudent($class);
        $teacher = $this->makeTeacher('Wahyudi');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN', 'Mata pelajaran bahasa Jepang.');

        $this->makeSchedule($subject, $class, 2, '11:00', '11:45');
        $this->assignTeacher($teacher, $class, $subject);

        $response = $this->actingAs($student)->get(route('student.subjects.show', $subject));

        $response->assertOk();
        $response->assertSee('Bahasa Jepang');
        $response->assertSee('Mata pelajaran bahasa Jepang.');
        $response->assertSee('Wahyudi');
        $response->assertSee('Selasa');
        $response->assertSee('11:00–11:45');
        $response->assertSee('10 PPLG');
    }

    public function test_student_cannot_open_a_subject_from_another_class(): void
    {
        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');
        $student = $this->makeStudent($class);
        $otherSubject = $this->makeSubject('Matematika', 'MTK');

        $this->makeSchedule($otherSubject, $otherClass);

        $this->actingAs($student)
            ->get(route('student.subjects.show', $otherSubject))
            ->assertNotFound();
    }

    public function test_student_without_a_class_sees_a_guided_empty_state(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        StudentProfile::create(['user_id' => $student->id, 'nis' => '111']);

        $response = $this->actingAs($student)->get(route('student.subjects.index'));

        $response->assertOk();
        $response->assertSee('Kelas Anda belum ditetapkan oleh administrator');
        $response->assertSee('Belum ada mata pelajaran');
    }

    public function test_subject_page_uses_the_shared_card_design_with_student_data(): void
    {
        $class = $this->makeClass('10 PPLG');
        $student = $this->makeStudent($class);
        $teacher = $this->makeTeacher('Wahyudi');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN', 'Mata pelajaran bahasa Jepang.');

        $this->makeSchedule($subject, $class, 2, '11:00', '11:45');
        $this->assignTeacher($teacher, $class, $subject);

        $response = $this->actingAs($student)->get(route('student.subjects.index'));

        $response->assertOk();
        $response->assertSee('1 Mata Pelajaran');
        $response->assertSee('subject-card__header', false);
        $response->assertSee('subject-card__code', false);
        $response->assertSee('Jadwal Kelas');
        $response->assertSee('Guru Pengajar');
        $response->assertSee('Kelas Anda');
        $response->assertSee('Mata pelajaran bahasa Jepang.');
        $response->assertSee('Wahyudi');
        $response->assertSee('10 PPLG');
        $response->assertSee('Selasa');
        $response->assertSee('11:00–11:45');
        $response->assertSee(route('student.subjects.show', $subject), false);
        $response->assertDontSee('Tambah Mata Pelajaran');
        $response->assertDontSee('subject-card__action--edit', false);
        $response->assertDontSee('subject-card__action--delete', false);
        $response->assertDontSee(route('admin.subjects.index'), false);
    }

    public function test_subject_page_search_is_limited_to_the_students_class(): void
    {
        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');
        $student = $this->makeStudent($class);

        $ownSubject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $otherClassSubject = $this->makeSubject('Bahasa Jepang Lanjut', 'BJPN2');
        $unrelatedSubject = $this->makeSubject('Matematika', 'MTK');

        $this->makeSchedule($ownSubject, $class, 1, '07:30', '09:00');
        $this->makeSchedule($unrelatedSubject, $class, 3, '09:00', '10:30');
        $this->makeSchedule($otherClassSubject, $otherClass);

        $response = $this->actingAs($student)
            ->get(route('student.subjects.index', ['q' => 'Bahasa Jepang']));

        $response->assertOk();
        $response->assertSee('2 Mata Pelajaran');
        $response->assertSee('Bahasa Jepang');
        $response->assertDontSee('Bahasa Jepang Lanjut');
        $response->assertDontSee('Matematika');
        $response->assertSee('value="Bahasa Jepang"', false);
    }

    public function test_subject_page_search_by_code_only_matches_authorized_subjects(): void
    {
        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');
        $student = $this->makeStudent($class);

        $ownSubject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $otherClassSubject = $this->makeSubject('Biologi', 'BJPN2');

        $this->makeSchedule($ownSubject, $class);
        $this->makeSchedule($otherClassSubject, $otherClass);

        $response = $this->actingAs($student)
            ->get(route('student.subjects.index', ['q' => 'BJPN']));

        $response->assertOk();
        $response->assertSee('Bahasa Jepang');
        $response->assertDontSee('Biologi');
    }

    public function test_subject_page_search_without_matches_shows_a_guided_empty_state(): void
    {
        $class = $this->makeClass('10 PPLG');
        $student = $this->makeStudent($class);
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');

        $this->makeSchedule($subject, $class);

        $response = $this->actingAs($student)
            ->get(route('student.subjects.index', ['q' => 'TidakAda']));

        $response->assertOk();
        $response->assertSee('Mata pelajaran tidak ditemukan.');
        $response->assertSee('Tampilkan Semua');
        $response->assertSee(route('student.subjects.index'), false);
    }

    public function test_subject_page_can_be_sorted_by_schedule(): void
    {
        $class = $this->makeClass('10 PPLG');
        $student = $this->makeStudent($class);

        $fridaySubject = $this->makeSubject('Fisika', 'FIS');
        $mondaySubject = $this->makeSubject('Kimia', 'KIM');

        $this->makeSchedule($fridaySubject, $class, 5, '07:30', '09:00');
        $this->makeSchedule($mondaySubject, $class, 1, '09:00', '10:30');

        $response = $this->actingAs($student)
            ->get(route('student.subjects.index', ['sort' => 'schedule_asc']));

        $response->assertOk();
        $response->assertSeeInOrder(['Kimia', 'Fisika']);
        $response->assertSee('value="schedule_asc" selected', false);
    }

    public function test_subject_page_sorts_subjects_without_a_schedule_last(): void
    {
        $class = $this->makeClass('10 PPLG');
        $student = $this->makeStudent($class);
        $teacher = $this->makeTeacher('Wahyudi');

        $unscheduledSubject = $this->makeSubject('Agama', 'AGM');
        $scheduledSubject = $this->makeSubject('Biologi', 'BIO');

        $this->assignTeacher($teacher, $class, $unscheduledSubject);
        $this->makeSchedule($scheduledSubject, $class, 1, '07:30', '09:00');

        $response = $this->actingAs($student)
            ->get(route('student.subjects.index', ['sort' => 'schedule_asc']));

        $response->assertOk();
        $response->assertSeeInOrder(['Biologi', 'Agama']);
    }

    public function test_subject_cards_handle_missing_description_and_schedule_gracefully(): void
    {
        $class = $this->makeClass('10 PPLG');
        $student = $this->makeStudent($class);
        $teacher = $this->makeTeacher('Wahyudi');
        $subject = $this->makeSubject('Seni Budaya', 'SB');

        $this->assignTeacher($teacher, $class, $subject);

        $response = $this->actingAs($student)->get(route('student.subjects.index'));

        $response->assertOk();
        $response->assertSee('Seni Budaya');
        $response->assertSee('Belum ada deskripsi mata pelajaran.');
        $response->assertSee('Belum ada jadwal');
        $response->assertSee('Wahyudi');
    }

    public function test_subject_page_pagination_preserves_search_and_sort(): void
    {
        $class = $this->makeClass('10 PPLG');
        $student = $this->makeStudent($class);

        foreach (range(1, 10) as $index) {
            $subject = $this->makeSubject('Subject '.$index, 'S'.str_pad((string) $index, 2, '0', STR_PAD_LEFT));
            $start = date('H:i', strtotime('06:00 + '.(($index - 1) * 15).' minutes'));
            $end = date('H:i', strtotime($start.' + 10 minutes'));

            $this->makeSchedule($subject, $class, 1, $start, $end);
        }

        $response = $this->actingAs($student)->get(route('student.subjects.index', [
            'q' => 'Subject',
            'sort' => 'schedule_asc',
        ]));

        $response->assertOk();
        $response->assertSee('page=2', false);
        $response->assertSee('q=Subject', false);
        $response->assertSee('sort=schedule_asc', false);
    }

    public function test_announcements_are_scoped_to_the_student_role_class_and_publication_state(): void
    {
        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');
        $student = $this->makeStudent($class);

        $this->makeAnnouncement(['title' => 'Untuk Semua']);
        $this->makeAnnouncement([
            'title' => 'Untuk Siswa',
            'visibility' => Announcement::VISIBILITY_ROLE,
            'target_role' => 'student',
        ]);
        $this->makeAnnouncement([
            'title' => 'Untuk Kelas Saya',
            'visibility' => Announcement::VISIBILITY_CLASS,
            'class_id' => $class->id,
        ]);
        $this->makeAnnouncement([
            'title' => 'Untuk Guru',
            'visibility' => Announcement::VISIBILITY_ROLE,
            'target_role' => 'teacher',
        ]);
        $this->makeAnnouncement([
            'title' => 'Kelas Lain',
            'visibility' => Announcement::VISIBILITY_CLASS,
            'class_id' => $otherClass->id,
        ]);
        $this->makeAnnouncement(['title' => 'Masih Draf', 'is_published' => false, 'published_at' => null]);
        $this->makeAnnouncement(['title' => 'Terbit Nanti', 'published_at' => now()->addDay()]);

        $response = $this->actingAs($student)->get(route('student.announcements.index'));

        $response->assertOk();
        $response->assertSee('Untuk Semua');
        $response->assertSee('Untuk Siswa');
        $response->assertSee('Untuk Kelas Saya');
        $response->assertDontSee('Untuk Guru');
        $response->assertDontSee('Kelas Lain');
        $response->assertDontSee('Masih Draf');
        $response->assertDontSee('Terbit Nanti');
    }

    public function test_student_cannot_open_an_announcement_that_is_not_visible_to_them(): void
    {
        $class = $this->makeClass('10 PPLG');
        $student = $this->makeStudent($class);
        $announcement = $this->makeAnnouncement([
            'title' => 'Khusus Guru',
            'visibility' => Announcement::VISIBILITY_ROLE,
            'target_role' => 'teacher',
        ]);

        $this->actingAs($student)
            ->get(route('student.announcements.show', $announcement))
            ->assertNotFound();
    }

    public function test_profile_page_displays_student_identity_and_protected_fields(): void
    {
        $class = $this->makeClass('10 PPLG');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');

        $response = $this->actingAs($student)->get(route('student.profile.edit'));

        $response->assertOk();
        $response->assertSee('Mulyadi');
        $response->assertSee('32587389573');
        $response->assertSee('10 PPLG');
        $response->assertSee('NIS, NISN, kelas, dan peran hanya dapat diubah oleh administrator.');
    }

    public function test_profile_update_changes_permitted_fields_and_ignores_protected_fields(): void
    {
        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');

        $response = $this->actingAs($student)->put(route('student.profile.update'), [
            'name' => 'Mulyadi Baru',
            'gender' => 'L',
            'birth_date' => '2009-01-15',
            'phone' => '08123456789',
            'address' => 'Bogor',
            'parent_name' => 'Bapak Mulyadi',
            'parent_phone' => '08987654321',
            'nis' => '999999',
            'nisn' => '999999',
            'class_id' => $otherClass->id,
            'role' => 'admin',
        ]);

        $response->assertRedirect(route('student.profile.edit'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => 'Mulyadi Baru',
            'role' => 'student',
        ]);

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'nis' => '32587389573',
            'class_id' => $class->id,
            'phone' => '08123456789',
            'parent_name' => 'Bapak Mulyadi',
        ]);
    }

    public function test_profile_update_requires_a_name(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent($class);

        $this->actingAs($student)
            ->put(route('student.profile.update'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_module_pages_are_reachable_and_show_empty_states(): void
    {
        $class = $this->makeClass('10 PPLG');
        $student = $this->makeStudent($class);
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $this->makeSchedule($subject, $class);

        $quizzes = $this->actingAs($student)->get(route('student.quizzes.index'));
        $quizzes->assertOk();
        $quizzes->assertSee('Belum ada kuis atau ujian yang tersedia.');

        $grades = $this->actingAs($student)->get(route('student.grades.index'));
        $grades->assertOk();
        $grades->assertSee('Belum ada nilai yang dipublikasikan.');

        $discussions = $this->actingAs($student)->get(route('student.discussions.index'));
        $discussions->assertOk();
        $discussions->assertSee('Belum ada diskusi yang tersedia.');
    }
}
