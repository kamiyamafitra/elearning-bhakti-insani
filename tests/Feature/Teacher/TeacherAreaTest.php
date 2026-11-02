<?php

namespace Tests\Feature\Teacher;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class TeacherAreaTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('teacher.dashboard'))->assertRedirect(route('login'));
    }

    public function test_non_teacher_user_receives_forbidden_response(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent($class);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($student)->get(route('teacher.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('teacher.dashboard'))->assertForbidden();
    }

    public function test_dashboard_displays_real_teaching_statistics_and_data(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman', '2159081309581');
        $otherTeacher = $this->makeTeacher('Guru Lain', '999');

        $firstClass = $this->makeClass('10 PPLG');
        $secondClass = $this->makeClass('11 PPLG');
        $unrelatedClass = $this->makeClass('12 PPLG');

        $firstSubject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $secondSubject = $this->makeSubject('Matematika', 'MTK');
        $unrelatedSubject = $this->makeSubject('Biologi', 'BIO');

        $this->makeSchedule($firstSubject, $firstClass, 2, '11:00', '11:45');
        $this->makeSchedule($secondSubject, $secondClass, 3, '13:15', '14:00');
        $this->makeSchedule($unrelatedSubject, $unrelatedClass, 4, '08:00', '09:00');

        $this->assignTeacher($teacher, $firstClass, $firstSubject);
        $this->assignTeacher($teacher, $secondClass, $secondSubject);
        $this->assignTeacher($otherTeacher, $unrelatedClass, $unrelatedSubject);

        $this->makeStudent($firstClass, 'Siswa Satu', '1001');
        $this->makeStudent($secondClass, 'Siswa Dua', '1002');
        $this->makeStudent($unrelatedClass, 'Siswa Tiga', '1003');

        $response = $this->actingAs($teacher)->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertSee('Halo, Tatang Surtaman');
        $response->assertSee('2159081309581');
        $response->assertSee('Bahasa Jepang');
        $response->assertSee('Matematika');
        $response->assertSee('10 PPLG');
        $response->assertSee('11 PPLG');
        $response->assertSee('Selasa');
        $response->assertSee('11:00–11:45');
        $response->assertDontSee('Biologi');
        $response->assertDontSee('12 PPLG');

        $response->assertViewHas('totalClasses', 2);
        $response->assertViewHas('totalSubjects', 2);
        $response->assertViewHas('totalStudents', 2);
    }

    public function test_subject_page_only_lists_subjects_assigned_to_the_teacher(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $otherTeacher = $this->makeTeacher('Guru Lain', '999');

        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');

        $ownSubject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $otherSubject = $this->makeSubject('Matematika', 'MTK');

        $this->makeSchedule($ownSubject, $class);
        $this->makeSchedule($otherSubject, $otherClass);

        $this->assignTeacher($teacher, $class, $ownSubject);
        $this->assignTeacher($otherTeacher, $otherClass, $otherSubject);

        $response = $this->actingAs($teacher)->get(route('teacher.subjects.index'));

        $response->assertOk();
        $response->assertSee('Bahasa Jepang');
        $response->assertSee('BJPN');
        $response->assertSee('10 PPLG');
        $response->assertDontSee('Matematika');
        $response->assertDontSee('11 PPLG');
    }

    public function test_teacher_can_open_an_assigned_subject_and_see_its_students(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $student = $this->makeStudent($class, 'Mulyadi', '32587389573');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN', 'Bahasa Jepang dasar.');

        $this->makeSchedule($subject, $class, 2, '11:00', '11:45');
        $this->assignTeacher($teacher, $class, $subject);

        $response = $this->actingAs($teacher)->get(route('teacher.subjects.show', $subject));

        $response->assertOk();
        $response->assertSee('Bahasa Jepang');
        $response->assertSee('Bahasa Jepang dasar.');
        $response->assertSee('10 PPLG');
        $response->assertSee('Selasa');
        $response->assertSee('11:00–11:45');
        $response->assertSee('Mulyadi');
        $response->assertSee('32587389573');
    }

    public function test_teacher_cannot_open_a_subject_they_are_not_assigned_to(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $otherTeacher = $this->makeTeacher('Guru Lain', '999');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Matematika', 'MTK');

        $this->makeSchedule($subject, $class);
        $this->assignTeacher($otherTeacher, $class, $subject);

        $this->actingAs($teacher)
            ->get(route('teacher.subjects.show', $subject))
            ->assertNotFound();
    }

    public function test_subject_page_uses_the_shared_card_design_with_teacher_data(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman', '2159081309581');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN', 'Bahasa Jepang dasar.');

        $this->makeSchedule($subject, $class, 2, '11:00', '11:45');
        $this->assignTeacher($teacher, $class, $subject);

        $response = $this->actingAs($teacher)->get(route('teacher.subjects.index'));

        $response->assertOk();
        $response->assertSee('1 Mata Pelajaran');
        $response->assertSee('subject-card__header', false);
        $response->assertSee('subject-card__code', false);
        $response->assertSee('Jadwal Kelas');
        $response->assertSee('Kelas yang Diajar');
        $response->assertSee('Bahasa Jepang dasar.');
        $response->assertSee('NIP 2159081309581');
        $response->assertSee('10 PPLG');
        $response->assertSee('Selasa');
        $response->assertSee('11:00–11:45');
        $response->assertSee(route('teacher.subjects.show', $subject), false);
        $response->assertDontSee('Tambah Mata Pelajaran');
        $response->assertDontSee('subject-card__action--edit', false);
        $response->assertDontSee('subject-card__action--delete', false);
        $response->assertDontSee(route('admin.subjects.index'), false);
    }

    public function test_subject_page_search_only_matches_subjects_assigned_to_the_teacher(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $otherTeacher = $this->makeTeacher('Guru Lain', '999');

        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');

        $ownSubject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $otherSubject = $this->makeSubject('Bahasa Jepang Lanjut', 'BJPN2');
        $unrelatedSubject = $this->makeSubject('Matematika', 'MTK');

        $this->makeSchedule($ownSubject, $class, 1, '07:30', '09:00');
        $this->makeSchedule($unrelatedSubject, $class, 3, '09:00', '10:30');
        $this->makeSchedule($otherSubject, $otherClass);

        $this->assignTeacher($teacher, $class, $ownSubject);
        $this->assignTeacher($teacher, $class, $unrelatedSubject);
        $this->assignTeacher($otherTeacher, $otherClass, $otherSubject);

        $response = $this->actingAs($teacher)
            ->get(route('teacher.subjects.index', ['q' => 'Bahasa Jepang']));

        $response->assertOk();
        $response->assertSee('2 Mata Pelajaran');
        $response->assertSee('Bahasa Jepang');
        $response->assertDontSee('Bahasa Jepang Lanjut');
        $response->assertDontSee('Matematika');
        $response->assertSee('value="Bahasa Jepang"', false);
    }

    public function test_subject_page_can_be_filtered_by_a_class_the_teacher_teaches(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $otherTeacher = $this->makeTeacher('Guru Lain', '999');

        $firstClass = $this->makeClass('10 PPLG');
        $secondClass = $this->makeClass('11 PPLG');
        $unrelatedClass = $this->makeClass('12 PPLG');

        $firstSubject = $this->makeSubject('Bahasa Jepang', 'BJPN');
        $secondSubject = $this->makeSubject('Matematika', 'MTK');
        $unrelatedSubject = $this->makeSubject('Biologi', 'BIO');

        $this->makeSchedule($firstSubject, $firstClass);
        $this->makeSchedule($secondSubject, $secondClass);
        $this->makeSchedule($unrelatedSubject, $unrelatedClass);

        $this->assignTeacher($teacher, $firstClass, $firstSubject);
        $this->assignTeacher($teacher, $secondClass, $secondSubject);
        $this->assignTeacher($otherTeacher, $unrelatedClass, $unrelatedSubject);

        $response = $this->actingAs($teacher)
            ->get(route('teacher.subjects.index', ['class_id' => $firstClass->id]));

        $response->assertOk();
        $response->assertSee('Bahasa Jepang');
        $response->assertDontSee('Matematika');
        $response->assertDontSee('Biologi');
        $response->assertSee('value="'.$firstClass->id.'" selected', false);
        $response->assertSee('value="'.$secondClass->id.'"', false);
        $response->assertDontSee('12 PPLG');
    }

    public function test_subject_page_search_without_matches_shows_a_guided_empty_state(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');

        $this->makeSchedule($subject, $class);
        $this->assignTeacher($teacher, $class, $subject);

        $response = $this->actingAs($teacher)
            ->get(route('teacher.subjects.index', ['q' => 'TidakAda']));

        $response->assertOk();
        $response->assertSee('Mata pelajaran tidak ditemukan.');
        $response->assertSee('Tampilkan Semua');
        $response->assertSee(route('teacher.subjects.index'), false);
    }

    public function test_subject_page_can_be_sorted_by_schedule(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');

        $fridaySubject = $this->makeSubject('Fisika', 'FIS');
        $mondaySubject = $this->makeSubject('Kimia', 'KIM');

        $this->makeSchedule($fridaySubject, $class, 5, '07:30', '09:00');
        $this->makeSchedule($mondaySubject, $class, 1, '09:00', '10:30');

        $this->assignTeacher($teacher, $class, $fridaySubject);
        $this->assignTeacher($teacher, $class, $mondaySubject);

        $response = $this->actingAs($teacher)
            ->get(route('teacher.subjects.index', ['sort' => 'schedule_asc']));

        $response->assertOk();
        $response->assertSeeInOrder(['Kimia', 'Fisika']);
        $response->assertSee('value="schedule_asc" selected', false);
    }

    public function test_subject_cards_handle_missing_description_and_schedule_gracefully(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Seni Budaya', 'SB');

        $this->assignTeacher($teacher, $class, $subject);

        $response = $this->actingAs($teacher)->get(route('teacher.subjects.index'));

        $response->assertOk();
        $response->assertSee('Seni Budaya');
        $response->assertSee('Belum ada deskripsi mata pelajaran.');
        $response->assertSee('Belum ada jadwal');
        $response->assertSee('10 PPLG');
    }

    public function test_subject_page_pagination_preserves_search_class_filter_and_sort(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');

        foreach (range(1, 10) as $index) {
            $subject = $this->makeSubject('Subject '.$index, 'S'.str_pad((string) $index, 2, '0', STR_PAD_LEFT));
            $start = date('H:i', strtotime('06:00 + '.(($index - 1) * 15).' minutes'));
            $end = date('H:i', strtotime($start.' + 10 minutes'));

            $this->makeSchedule($subject, $class, 1, $start, $end);
            $this->assignTeacher($teacher, $class, $subject);
        }

        $response = $this->actingAs($teacher)->get(route('teacher.subjects.index', [
            'q' => 'Subject',
            'class_id' => $class->id,
            'sort' => 'schedule_asc',
        ]));

        $response->assertOk();
        $response->assertSee('page=2', false);
        $response->assertSee('q=Subject', false);
        $response->assertSee('class_id='.$class->id, false);
        $response->assertSee('sort=schedule_asc', false);
    }

    public function test_announcements_are_scoped_to_the_teacher_role_and_taught_classes(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');

        $this->makeSchedule($subject, $class);
        $this->assignTeacher($teacher, $class, $subject);

        $this->makeAnnouncement(['title' => 'Untuk Semua']);
        $this->makeAnnouncement([
            'title' => 'Untuk Guru',
            'visibility' => Announcement::VISIBILITY_ROLE,
            'target_role' => 'teacher',
        ]);
        $this->makeAnnouncement([
            'title' => 'Untuk Kelas Saya',
            'visibility' => Announcement::VISIBILITY_CLASS,
            'class_id' => $class->id,
        ]);
        $this->makeAnnouncement([
            'title' => 'Untuk Siswa',
            'visibility' => Announcement::VISIBILITY_ROLE,
            'target_role' => 'student',
        ]);
        $this->makeAnnouncement([
            'title' => 'Kelas Lain',
            'visibility' => Announcement::VISIBILITY_CLASS,
            'class_id' => $otherClass->id,
        ]);
        $this->makeAnnouncement(['title' => 'Masih Draf', 'is_published' => false, 'published_at' => null]);

        $response = $this->actingAs($teacher)->get(route('teacher.announcements.index'));

        $response->assertOk();
        $response->assertSee('Untuk Semua');
        $response->assertSee('Untuk Guru');
        $response->assertSee('Untuk Kelas Saya');
        $response->assertDontSee('Untuk Siswa');
        $response->assertDontSee('Kelas Lain');
        $response->assertDontSee('Masih Draf');
    }

    public function test_profile_page_displays_teacher_identity(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman', '2159081309581');

        $response = $this->actingAs($teacher)->get(route('teacher.profile.edit'));

        $response->assertOk();
        $response->assertSee('Tatang Surtaman');
        $response->assertSee('2159081309581');
        $response->assertSee('NIP dan peran hanya dapat diubah oleh administrator.');
    }

    public function test_profile_update_changes_permitted_fields_and_ignores_protected_fields(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman', '2159081309581');

        $response = $this->actingAs($teacher)->put(route('teacher.profile.update'), [
            'name' => 'Tatang Surtaman Baru',
            'phone' => '08111111111',
            'address' => 'Bogor Baru',
            'nip' => '999999',
            'role' => 'admin',
        ]);

        $response->assertRedirect(route('teacher.profile.edit'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $teacher->id,
            'name' => 'Tatang Surtaman Baru',
            'role' => 'teacher',
        ]);

        $this->assertDatabaseHas('teacher_profiles', [
            'user_id' => $teacher->id,
            'nip' => '2159081309581',
            'phone' => '08111111111',
            'address' => 'Bogor Baru',
        ]);
    }

    public function test_profile_update_requires_a_name(): void
    {
        $teacher = $this->makeTeacher();

        $this->actingAs($teacher)
            ->put(route('teacher.profile.update'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_module_pages_are_reachable_and_show_empty_states(): void
    {
        $teacher = $this->makeTeacher();
        $class = $this->makeClass('10 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');

        $this->makeSchedule($subject, $class);
        $this->assignTeacher($teacher, $class, $subject);

        $quizzes = $this->actingAs($teacher)->get(route('teacher.quizzes.index'));
        $quizzes->assertOk();
        $quizzes->assertSee('Belum ada kuis yang dibuat.');

        $grades = $this->actingAs($teacher)->get(route('teacher.grades.index'));
        $grades->assertOk();
        $grades->assertSee('Belum ada pengumpulan tugas yang perlu dinilai.');
        $grades->assertSee('10 PPLG');

        $discussions = $this->actingAs($teacher)->get(route('teacher.discussions.index'));
        $discussions->assertOk();
        $discussions->assertSee('Belum ada diskusi.');
    }

    public function test_teacher_cannot_access_student_area(): void
    {
        $teacher = $this->makeTeacher();

        $this->actingAs($teacher)->get(route('student.dashboard'))->assertForbidden();
        $this->actingAs($teacher)->get(route('student.grades.index'))->assertForbidden();
    }

    public function test_teacher_statistics_do_not_include_classes_taught_by_other_teachers(): void
    {
        $teacher = $this->makeTeacher('Tatang Surtaman');
        $otherTeacher = $this->makeTeacher('Guru Lain', '999');
        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');
        $subject = $this->makeSubject('Bahasa Jepang', 'BJPN');

        $this->assignTeacher($teacher, $class, $subject);
        $this->assignTeacher($otherTeacher, $otherClass, $subject);
        $this->makeStudent($otherClass, 'Siswa Lain', '2001');

        $response = $this->actingAs($teacher)->get(route('teacher.dashboard'));

        $response->assertViewHas('totalClasses', 1);
        $response->assertViewHas('totalStudents', 0);
    }
}
