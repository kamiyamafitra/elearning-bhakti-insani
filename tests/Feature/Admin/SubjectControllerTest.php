<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Classes;
use App\Models\ClassSchedule;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.subjects.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_user_receives_forbidden_response(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);

        $response = $this->actingAs($student)->get(route('admin.subjects.index'));

        $response->assertForbidden();
    }

    public function test_index_displays_subject_cards_with_dynamic_total_count(): void
    {
        $matematika = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => 'Mata pelajaran matematika.',
        ]);

        $bahasa = Subject::create([
            'code' => 'BINDO',
            'name' => 'Bahasa Indonesia',
            'description' => null,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.subjects.index'));

        $response->assertOk();
        $response->assertSee('2 Mata Pelajaran');
        $response->assertSee('subject-card__header', false);
        $response->assertDontSee('<table', false);
        $response->assertSee('Matematika');
        $response->assertSee('MTK');
        $response->assertSee('Mata pelajaran matematika.');
        $response->assertSee('Bahasa Indonesia');
        $response->assertSee('BINDO');
        $response->assertSee('subject-card__value--description', false);
        $response->assertSee(route('admin.subjects.create'), false);
        $response->assertSee(route('admin.subjects.show', $matematika), false);
        $response->assertSee(route('admin.subjects.edit', $bahasa), false);
    }

    public function test_admin_subject_cards_keep_the_management_actions(): void
    {
        $subject = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => 'Mata pelajaran matematika.',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.subjects.index'));

        $response->assertOk();
        $response->assertSee('subject-card__actions', false);
        $response->assertSee('subject-card__action--edit', false);
        $response->assertSee('subject-card__action--delete', false);
        $response->assertSee(route('admin.subjects.edit', $subject), false);
        $response->assertSee(route('admin.subjects.destroy', $subject), false);
        $response->assertSee('Guru Pengajar');
        $response->assertSee('Jadwal Kelas');
    }

    public function test_subjects_can_be_searched_by_name(): void
    {
        Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        Subject::create([
            'code' => 'BINDO',
            'name' => 'Bahasa Indonesia',
            'description' => null,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.subjects.index', ['q' => 'Matematika']));

        $response->assertOk();
        $response->assertSee('Matematika');
        $response->assertDontSee('Bahasa Indonesia');
        $response->assertSee('value="Matematika"', false);
    }

    public function test_subjects_can_be_searched_by_code(): void
    {
        Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        Subject::create([
            'code' => 'BINDO',
            'name' => 'Bahasa Indonesia',
            'description' => null,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.subjects.index', ['q' => 'BINDO']));

        $response->assertOk();
        $response->assertSee('Bahasa Indonesia');
        $response->assertDontSee('Matematika');
    }

    public function test_empty_state_is_shown_when_no_subject_matches_search(): void
    {
        Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.subjects.index', ['q' => 'TidakAda']));

        $response->assertOk();
        $response->assertSee('Mata pelajaran tidak ditemukan.');
        $response->assertSee('Coba gunakan kata kunci lain');
        $response->assertSee('Tampilkan Semua');
    }

    public function test_pagination_links_preserve_search_query(): void
    {
        foreach (range(1, 11) as $index) {
            Subject::create([
                'code' => 'S'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'name' => 'Subject '.$index,
                'description' => null,
            ]);
        }

        $response = $this->actingAs($this->admin())
            ->get(route('admin.subjects.index', ['q' => 'Subject']));

        $response->assertOk();
        $response->assertSee('page=2', false);
        $response->assertSee('q=Subject', false);
    }

    public function test_create_page_renders_the_existing_form(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.subjects.create'));

        $response->assertOk();
        $response->assertSee(route('admin.subjects.store'), false);
        $response->assertSee('Kode Mata Pelajaran');
        $response->assertSee('Nama Mata Pelajaran');
    }

    public function test_subject_can_be_created(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.subjects.store'), [
            'code' => 'FIS',
            'name' => 'Fisika',
            'description' => 'Mata pelajaran fisika.',
        ]);

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('subjects', [
            'code' => 'FIS',
            'name' => 'Fisika',
            'description' => 'Mata pelajaran fisika.',
        ]);
        $this->assertDatabaseCount('class_schedules', 0);
    }

    public function test_store_validation_rejects_invalid_input(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.subjects.store'), []);

        $response->assertSessionHasErrors(['code', 'name']);
        $this->assertDatabaseCount('subjects', 0);
    }

    public function test_show_page_displays_the_requested_subject(): void
    {
        $subject = Subject::create([
            'code' => 'KIM',
            'name' => 'Kimia',
            'description' => 'Mata pelajaran kimia.',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.subjects.show', $subject));

        $response->assertOk();
        $response->assertSee('Kimia');
        $response->assertSee('KIM');
        $response->assertSee('Mata pelajaran kimia.');
    }

    public function test_edit_page_displays_the_requested_subject(): void
    {
        $subject = Subject::create([
            'code' => 'BIO',
            'name' => 'Biologi',
            'description' => null,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.subjects.edit', $subject));

        $response->assertOk();
        $response->assertSee(route('admin.subjects.update', $subject), false);
        $response->assertSee('Biologi');
        $response->assertSee('BIO');
    }

    public function test_subject_can_be_updated(): void
    {
        $subject = Subject::create([
            'code' => 'EKO',
            'name' => 'Ekonomi',
            'description' => null,
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.subjects.update', $subject), [
            'code' => 'EKO',
            'name' => 'Ekonomi Bisnis',
            'description' => 'Deskripsi baru.',
        ]);

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'name' => 'Ekonomi Bisnis',
            'description' => 'Deskripsi baru.',
        ]);
    }

    public function test_subject_can_be_deleted(): void
    {
        $subject = Subject::create([
            'code' => 'SOS',
            'name' => 'Sosiologi',
            'description' => null,
        ]);

        $response = $this->actingAs($this->admin())
            ->delete(route('admin.subjects.destroy', $subject));

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHas('success');
        $this->assertModelMissing($subject);
    }

    public function test_subject_cards_display_schedule_class_day_and_time(): void
    {
        $subject = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => 'Materi matematika.',
        ]);

        $class = $this->makeClass('X TKJ 1');

        $this->createSchedule($subject, $class, 1, '07:30', '09:00');

        $response = $this->actingAs($this->admin())->get(route('admin.subjects.index'));

        $response->assertOk();
        $response->assertSee('Jadwal Kelas');
        $response->assertSee('Senin');
        $response->assertSee('07:30–09:00');
        $response->assertSee('X TKJ 1');
    }

    public function test_subject_without_schedule_shows_empty_schedule_state(): void
    {
        Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.subjects.index'));

        $response->assertOk();
        $response->assertSee('Belum ada jadwal');
    }

    public function test_subject_with_multiple_schedules_displays_every_assignment(): void
    {
        $subject = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        $firstClass = $this->makeClass('X TKJ 1');
        $secondClass = $this->makeClass('XI TKJ 2');

        $this->createSchedule($subject, $firstClass, 1, '07:30', '09:00');
        $this->createSchedule($subject, $secondClass, 3, '10:00', '11:30');

        $response = $this->actingAs($this->admin())->get(route('admin.subjects.index'));

        $response->assertOk();
        $response->assertSee('Senin');
        $response->assertSee('Rabu');
        $response->assertSee('07:30–09:00');
        $response->assertSee('10:00–11:30');
        $response->assertSee('X TKJ 1');
        $response->assertSee('XI TKJ 2');
        $response->assertSeeInOrder(['Senin', 'Rabu']);
    }

    public function test_subjects_can_be_filtered_by_class(): void
    {
        $firstClass = $this->makeClass('X TKJ 1');
        $secondClass = $this->makeClass('XI TKJ 2');

        $matematika = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        $biologi = Subject::create([
            'code' => 'BIO',
            'name' => 'Biologi',
            'description' => null,
        ]);

        $this->createSchedule($matematika, $firstClass, 1, '07:30', '09:00');
        $this->createSchedule($biologi, $secondClass, 2, '09:00', '10:30');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.subjects.index', ['class_id' => $firstClass->id]));

        $response->assertOk();
        $response->assertSee('Matematika');
        $response->assertDontSee('Biologi');
        $response->assertSee('value="'.$firstClass->id.'" selected', false);
    }

    public function test_subjects_can_be_filtered_by_class_through_teaching_assignment(): void
    {
        $class = $this->makeClass('X TKJ 1');

        $subject = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        Subject::create([
            'code' => 'BIO',
            'name' => 'Biologi',
            'description' => null,
        ]);

        TeachingAssignment::create([
            'academic_year_id' => $class->academic_year_id,
            'teacher_id' => User::factory()->create(['role' => 'teacher', 'is_active' => true])->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.subjects.index', ['class_id' => $class->id]));

        $response->assertOk();
        $response->assertSee('Matematika');
        $response->assertDontSee('Biologi');
    }

    public function test_class_filter_can_be_combined_with_search(): void
    {
        $firstClass = $this->makeClass('X TKJ 1');
        $secondClass = $this->makeClass('XI TKJ 2');

        $matematika = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        $biologi = Subject::create([
            'code' => 'BIO',
            'name' => 'Biologi',
            'description' => null,
        ]);

        $matematikaLanjut = Subject::create([
            'code' => 'MTK2',
            'name' => 'Matematika Lanjut',
            'description' => null,
        ]);

        $this->createSchedule($matematika, $firstClass, 1, '07:30', '09:00');
        $this->createSchedule($biologi, $firstClass, 2, '09:00', '10:30');
        $this->createSchedule($matematikaLanjut, $secondClass, 3, '10:00', '11:30');

        $response = $this->actingAs($this->admin())->get(route('admin.subjects.index', [
            'q' => 'Matematika',
            'class_id' => $firstClass->id,
        ]));

        $response->assertOk();
        $response->assertSee('Matematika');
        $response->assertDontSee('Matematika Lanjut');
        $response->assertDontSee('Biologi');
    }

    public function test_resetting_class_filter_restores_all_subjects(): void
    {
        $class = $this->makeClass('X TKJ 1');

        $matematika = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        $biologi = Subject::create([
            'code' => 'BIO',
            'name' => 'Biologi',
            'description' => null,
        ]);

        $this->createSchedule($matematika, $class, 1, '07:30', '09:00');

        $filtered = $this->actingAs($this->admin())
            ->get(route('admin.subjects.index', ['class_id' => $class->id]));

        $filtered->assertDontSee('Biologi');

        $response = $this->actingAs($this->admin())->get(route('admin.subjects.index'));

        $response->assertOk();
        $response->assertSee('Matematika');
        $response->assertSee('Biologi');
    }

    public function test_subjects_can_be_sorted_by_name_descending(): void
    {
        $class = $this->makeClass('X TKJ 1');

        $first = Subject::create(['code' => 'BIO', 'name' => 'Biologi', 'description' => null]);
        $second = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'description' => null]);

        $this->createSchedule($first, $class, 1, '07:30', '09:00');
        $this->createSchedule($second, $class, 2, '08:00', '09:00');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.subjects.index', ['sort' => 'name_desc']));

        $response->assertOk();
        $response->assertSeeInOrder(['Matematika', 'Biologi']);
    }

    public function test_subjects_can_be_sorted_by_scheduled_day(): void
    {
        $class = $this->makeClass('X TKJ 1');

        $fridaySubject = Subject::create(['code' => 'FIS', 'name' => 'Fisika', 'description' => null]);
        $mondaySubject = Subject::create(['code' => 'KIM', 'name' => 'Kimia', 'description' => null]);

        $this->createSchedule($fridaySubject, $class, 5, '07:30', '09:00');
        $this->createSchedule($mondaySubject, $class, 1, '09:00', '10:30');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.subjects.index', ['sort' => 'schedule_asc']));

        $response->assertOk();
        $response->assertSeeInOrder(['Kimia', 'Fisika']);
    }

    public function test_subjects_can_be_sorted_by_class_name(): void
    {
        $lowerClass = $this->makeClass('X TKJ 1');
        $higherClass = $this->makeClass('XI TKJ 2');

        $matematika = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'description' => null]);
        $biologi = Subject::create(['code' => 'BIO', 'name' => 'Biologi', 'description' => null]);

        $this->createSchedule($matematika, $lowerClass, 1, '07:30', '09:00');
        $this->createSchedule($biologi, $higherClass, 2, '09:00', '10:30');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.subjects.index', ['sort' => 'class_asc']));

        $response->assertOk();
        $response->assertSeeInOrder(['Matematika', 'Biologi']);
    }

    public function test_pagination_links_preserve_search_class_filter_and_sort(): void
    {
        $class = $this->makeClass('X TKJ 1');

        foreach (range(1, 11) as $index) {
            $subject = Subject::create([
                'code' => 'S'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'name' => 'Subject '.$index,
                'description' => null,
            ]);

            $start = date('H:i', strtotime('06:00 + '.(($index - 1) * 15).' minutes'));
            $end = date('H:i', strtotime($start.' + 10 minutes'));

            $this->createSchedule($subject, $class, 1, $start, $end);
        }

        $response = $this->actingAs($this->admin())->get(route('admin.subjects.index', [
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

    public function test_edit_page_displays_existing_schedules_and_class_options(): void
    {
        $subject = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        $class = $this->makeClass('X TKJ 1');

        $this->createSchedule($subject, $class, 1, '07:30', '09:00');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.subjects.edit', $subject));

        $response->assertOk();
        $response->assertSee('Jadwal Kelas');
        $response->assertSee('X TKJ 1');
        $response->assertSee('value="07:30"', false);
        $response->assertSee('value="09:00"', false);
        $response->assertSee('data-schedule-rows', false);
    }

    public function test_schedules_can_be_saved_when_updating_a_subject(): void
    {
        $subject = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        $class = $this->makeClass('X TKJ 1');

        $response = $this->actingAs($this->admin())->put(route('admin.subjects.update', $subject), [
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
            'schedules' => [
                [
                    'class_id' => $class->id,
                    'day_of_week' => 1,
                    'start_time' => '07:30',
                    'end_time' => '09:00',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('class_schedules', 1);
        $this->assertDatabaseHas('class_schedules', [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'day_of_week' => 1,
        ]);

        $show = $this->actingAs($this->admin())
            ->get(route('admin.subjects.show', $subject));

        $show->assertSee('07:30–09:00');
        $show->assertSee('Senin');
    }

    public function test_empty_schedule_row_is_ignored_when_updating_a_subject(): void
    {
        $subject = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.subjects.update', $subject), [
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
            'schedules' => [
                ['class_id' => '', 'day_of_week' => '', 'start_time' => '', 'end_time' => ''],
            ],
        ]);

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('class_schedules', 0);
    }

    public function test_removed_schedule_rows_are_deleted_when_updating_a_subject(): void
    {
        $subject = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        $class = $this->makeClass('X TKJ 1');
        $schedule = $this->createSchedule($subject, $class, 1, '07:30', '09:00');

        $response = $this->actingAs($this->admin())->put(route('admin.subjects.update', $subject), [
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        $response->assertRedirect(route('admin.subjects.index'));
        $this->assertModelMissing($schedule);
    }

    public function test_schedule_validation_rejects_invalid_time_range(): void
    {
        $subject = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        $class = $this->makeClass('X TKJ 1');

        $response = $this->actingAs($this->admin())->put(route('admin.subjects.update', $subject), [
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
            'schedules' => [
                [
                    'class_id' => $class->id,
                    'day_of_week' => 1,
                    'start_time' => '09:00',
                    'end_time' => '08:00',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('schedules.0.end_time');
        $this->assertDatabaseCount('class_schedules', 0);
    }

    public function test_schedule_validation_rejects_invalid_day_and_class(): void
    {
        $subject = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.subjects.update', $subject), [
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
            'schedules' => [
                [
                    'class_id' => 9999,
                    'day_of_week' => 9,
                    'start_time' => '07:30',
                    'end_time' => '09:00',
                ],
            ],
        ]);

        $response->assertSessionHasErrors([
            'schedules.0.class_id',
            'schedules.0.day_of_week',
        ]);
        $this->assertDatabaseCount('class_schedules', 0);
    }

    public function test_schedule_validation_rejects_conflicting_class_schedule(): void
    {
        $class = $this->makeClass('X TKJ 1');

        $existingSubject = Subject::create([
            'code' => 'BIO',
            'name' => 'Biologi',
            'description' => null,
        ]);

        $this->createSchedule($existingSubject, $class, 1, '08:00', '09:00');

        $subject = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.subjects.update', $subject), [
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
            'schedules' => [
                [
                    'class_id' => $class->id,
                    'day_of_week' => 1,
                    'start_time' => '08:30',
                    'end_time' => '09:30',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('schedules.0.start_time');
        $this->assertDatabaseCount('class_schedules', 1);
    }

    public function test_deleting_a_subject_removes_its_schedules(): void
    {
        $subject = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        $class = $this->makeClass('X TKJ 1');
        $schedule = $this->createSchedule($subject, $class, 1, '07:30', '09:00');

        $this->actingAs($this->admin())
            ->delete(route('admin.subjects.destroy', $subject))
            ->assertRedirect(route('admin.subjects.index'));

        $this->assertDatabaseMissing('class_schedules', ['id' => $schedule->id]);
    }

    public function test_create_page_displays_schedule_fields_and_class_options(): void
    {
        $class = $this->makeClass('X TKJ 1');

        $response = $this->actingAs($this->admin())->get(route('admin.subjects.create'));

        $response->assertOk();
        $response->assertSee('Informasi Mata Pelajaran');
        $response->assertSee('Jadwal Kelas');
        $response->assertSee('Tambah Jadwal');
        $response->assertSee('X TKJ 1');
        $response->assertSee('data-schedule-rows', false);
        $response->assertSee('name="schedules[0][class_id]"', false);
        $response->assertSee('name="schedules[0][day_of_week]"', false);
        $response->assertSee('name="schedules[0][start_time]"', false);
        $response->assertSee('name="schedules[0][end_time]"', false);
    }

    public function test_subject_can_be_created_with_class_and_schedule_in_one_submission(): void
    {
        $class = $this->makeClass('X TKJ 1');

        $response = $this->actingAs($this->admin())->post(route('admin.subjects.store'), [
            'code' => 'FIS',
            'name' => 'Fisika',
            'description' => 'Mata pelajaran fisika.',
            'schedules' => [
                [
                    'class_id' => $class->id,
                    'day_of_week' => 1,
                    'start_time' => '07:30',
                    'end_time' => '09:00',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHas('success');

        $subject = Subject::where('code', 'FIS')->firstOrFail();

        $this->assertDatabaseHas('class_schedules', [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'day_of_week' => 1,
        ]);

        $index = $this->actingAs($this->admin())->get(route('admin.subjects.index'));
        $index->assertSee('Fisika');
        $index->assertSee('Senin');
        $index->assertSee('07:30–09:00');
        $index->assertSee('X TKJ 1');

        $edit = $this->actingAs($this->admin())->get(route('admin.subjects.edit', $subject));
        $edit->assertSee('value="07:30"', false);
        $edit->assertSee('value="09:00"', false);
        $edit->assertSee('value="'.$class->id.'" selected', false);
    }

    public function test_subject_can_be_created_with_multiple_schedules(): void
    {
        $firstClass = $this->makeClass('X TKJ 1');
        $secondClass = $this->makeClass('XI TKJ 2');

        $response = $this->actingAs($this->admin())->post(route('admin.subjects.store'), [
            'code' => 'KIM',
            'name' => 'Kimia',
            'description' => null,
            'schedules' => [
                [
                    'class_id' => $firstClass->id,
                    'day_of_week' => 1,
                    'start_time' => '07:30',
                    'end_time' => '09:00',
                ],
                [
                    'class_id' => $secondClass->id,
                    'day_of_week' => 3,
                    'start_time' => '10:00',
                    'end_time' => '11:30',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseCount('class_schedules', 2);
        $this->assertDatabaseHas('class_schedules', [
            'class_id' => $firstClass->id,
            'day_of_week' => 1,
        ]);
        $this->assertDatabaseHas('class_schedules', [
            'class_id' => $secondClass->id,
            'day_of_week' => 3,
        ]);
    }

    public function test_store_rejects_invalid_schedule_range_without_creating_the_subject(): void
    {
        $class = $this->makeClass('X TKJ 1');

        $response = $this->actingAs($this->admin())->post(route('admin.subjects.store'), [
            'code' => 'FIS',
            'name' => 'Fisika',
            'description' => null,
            'schedules' => [
                [
                    'class_id' => $class->id,
                    'day_of_week' => 1,
                    'start_time' => '09:00',
                    'end_time' => '08:00',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('schedules.0.end_time');
        $this->assertDatabaseCount('subjects', 0);
        $this->assertDatabaseCount('class_schedules', 0);
    }

    public function test_store_rejects_unknown_class_without_creating_the_subject(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.subjects.store'), [
            'code' => 'FIS',
            'name' => 'Fisika',
            'description' => null,
            'schedules' => [
                [
                    'class_id' => 9999,
                    'day_of_week' => 1,
                    'start_time' => '07:30',
                    'end_time' => '09:00',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('schedules.0.class_id');
        $this->assertDatabaseCount('subjects', 0);
        $this->assertDatabaseCount('class_schedules', 0);
    }

    public function test_store_rejects_conflicting_class_schedule_without_creating_the_subject(): void
    {
        $class = $this->makeClass('X TKJ 1');

        $existingSubject = Subject::create([
            'code' => 'BIO',
            'name' => 'Biologi',
            'description' => null,
        ]);

        $this->createSchedule($existingSubject, $class, 1, '08:00', '09:00');

        $response = $this->actingAs($this->admin())->post(route('admin.subjects.store'), [
            'code' => 'FIS',
            'name' => 'Fisika',
            'description' => null,
            'schedules' => [
                [
                    'class_id' => $class->id,
                    'day_of_week' => 1,
                    'start_time' => '08:30',
                    'end_time' => '09:30',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('schedules.0.start_time');
        $this->assertDatabaseMissing('subjects', ['code' => 'FIS']);
        $this->assertDatabaseCount('class_schedules', 1);
    }

    public function test_create_form_preserves_schedule_values_after_validation_error(): void
    {
        $class = $this->makeClass('X TKJ 1');

        $this->actingAs($this->admin())->post(route('admin.subjects.store'), [
            'code' => '',
            'name' => 'Fisika',
            'description' => null,
            'schedules' => [
                [
                    'class_id' => $class->id,
                    'day_of_week' => 2,
                    'start_time' => '07:30',
                    'end_time' => '09:00',
                ],
            ],
        ])->assertSessionHasErrors('code');

        $response = $this->actingAs($this->admin())->get(route('admin.subjects.create'));

        $response->assertOk();
        $response->assertSee('value="Fisika"', false);
        $response->assertSee('value="07:30"', false);
        $response->assertSee('value="09:00"', false);
        $response->assertSee('value="'.$class->id.'" selected', false);
    }

    public function test_updating_a_subject_preserves_its_existing_schedule(): void
    {
        $subject = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
        ]);

        $class = $this->makeClass('X TKJ 1');
        $schedule = $this->createSchedule($subject, $class, 1, '07:30', '09:00');

        $response = $this->actingAs($this->admin())->put(route('admin.subjects.update', $subject), [
            'code' => 'MTK',
            'name' => 'Matematika Lanjut',
            'description' => 'Deskripsi baru.',
            'schedules' => [
                [
                    'id' => $schedule->id,
                    'class_id' => $class->id,
                    'day_of_week' => 1,
                    'start_time' => '07:30',
                    'end_time' => '09:00',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseCount('class_schedules', 1);
        $this->assertDatabaseHas('class_schedules', [
            'id' => $schedule->id,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'day_of_week' => 1,
        ]);
        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'name' => 'Matematika Lanjut',
        ]);
    }

    public function test_create_page_displays_teacher_options_from_database(): void
    {
        $this->makeClass('X TKJ 1');
        $this->makeTeacher('Budi Santoso');
        User::factory()->create([
            'name' => 'Siswa Contoh',
            'role' => 'student',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.subjects.create'));

        $response->assertOk();
        $response->assertSee('Guru Pengajar');
        $response->assertSee('Pilih Guru Pengajar');
        $response->assertSee('name="schedules[0][teacher_id]"', false);
        $response->assertSee('Budi Santoso');
        $response->assertDontSee('Siswa Contoh');
    }

    public function test_subject_can_be_created_with_teacher_assignment(): void
    {
        $class = $this->makeClass('X TKJ 1');
        $teacher = $this->makeTeacher('Budi Santoso');

        $response = $this->actingAs($this->admin())->post(route('admin.subjects.store'), [
            'code' => 'FIS',
            'name' => 'Fisika',
            'description' => 'Mata pelajaran fisika.',
            'schedules' => [
                [
                    'class_id' => $class->id,
                    'day_of_week' => 1,
                    'start_time' => '07:30',
                    'end_time' => '09:00',
                    'teacher_id' => $teacher->id,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHasNoErrors();

        $subject = Subject::where('code', 'FIS')->firstOrFail();

        $this->assertDatabaseHas('teaching_assignments', [
            'academic_year_id' => $class->academic_year_id,
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);

        $index = $this->actingAs($this->admin())->get(route('admin.subjects.index'));
        $index->assertSee('Guru Pengajar');
        $index->assertSee('Budi Santoso');
        $index->assertSee('X TKJ 1');

        $show = $this->actingAs($this->admin())->get(route('admin.subjects.show', $subject));
        $show->assertSee('Guru Pengajar');
        $show->assertSee('Budi Santoso');
    }

    public function test_subject_without_teacher_shows_empty_teacher_state(): void
    {
        $class = $this->makeClass('X TKJ 1');

        $this->actingAs($this->admin())->post(route('admin.subjects.store'), [
            'code' => 'FIS',
            'name' => 'Fisika',
            'description' => null,
            'schedules' => [
                [
                    'class_id' => $class->id,
                    'day_of_week' => 1,
                    'start_time' => '07:30',
                    'end_time' => '09:00',
                    'teacher_id' => '',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $subject = Subject::where('code', 'FIS')->firstOrFail();

        $this->assertDatabaseCount('teaching_assignments', 0);
        $this->assertDatabaseCount('class_schedules', 1);

        $index = $this->actingAs($this->admin())->get(route('admin.subjects.index'));
        $index->assertSee('Belum ada guru');

        $show = $this->actingAs($this->admin())->get(route('admin.subjects.show', $subject));
        $show->assertSee('Belum ada guru');
    }

    public function test_edit_page_displays_the_currently_assigned_teacher(): void
    {
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'description' => null]);
        $class = $this->makeClass('X TKJ 1');
        $teacher = $this->makeTeacher('Budi Santoso');

        $this->createSchedule($subject, $class, 1, '07:30', '09:00');

        TeachingAssignment::create([
            'academic_year_id' => $class->academic_year_id,
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.subjects.edit', $subject));

        $response->assertOk();
        $response->assertSee('Guru Pengajar');
        $response->assertSee('name="schedules[0][teacher_id]"', false);
        $response->assertSee('value="'.$teacher->id.'" selected', false);
    }

    public function test_teacher_assignment_can_be_changed_without_losing_the_schedule(): void
    {
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'description' => null]);
        $class = $this->makeClass('X TKJ 1');
        $firstTeacher = $this->makeTeacher('Budi Santoso');
        $secondTeacher = $this->makeTeacher('Siti Aminah');

        $schedule = $this->createSchedule($subject, $class, 1, '07:30', '09:00');

        TeachingAssignment::create([
            'academic_year_id' => $class->academic_year_id,
            'teacher_id' => $firstTeacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.subjects.update', $subject), [
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
            'schedules' => [
                [
                    'id' => $schedule->id,
                    'class_id' => $class->id,
                    'day_of_week' => 1,
                    'start_time' => '07:30',
                    'end_time' => '09:00',
                    'teacher_id' => $secondTeacher->id,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseCount('teaching_assignments', 1);
        $this->assertDatabaseHas('teaching_assignments', [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'teacher_id' => $secondTeacher->id,
        ]);
        $this->assertDatabaseHas('class_schedules', [
            'id' => $schedule->id,
            'class_id' => $class->id,
            'day_of_week' => 1,
        ]);
    }

    public function test_teacher_assignment_is_removed_when_the_teacher_is_cleared(): void
    {
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'description' => null]);
        $class = $this->makeClass('X TKJ 1');
        $teacher = $this->makeTeacher('Budi Santoso');

        $schedule = $this->createSchedule($subject, $class, 1, '07:30', '09:00');

        TeachingAssignment::create([
            'academic_year_id' => $class->academic_year_id,
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.subjects.update', $subject), [
            'code' => 'MTK',
            'name' => 'Matematika',
            'description' => null,
            'schedules' => [
                [
                    'id' => $schedule->id,
                    'class_id' => $class->id,
                    'day_of_week' => 1,
                    'start_time' => '07:30',
                    'end_time' => '09:00',
                    'teacher_id' => '',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.subjects.index'));
        $this->assertDatabaseCount('teaching_assignments', 0);
        $this->assertDatabaseCount('class_schedules', 1);
    }

    public function test_subject_supports_different_teachers_for_different_classes(): void
    {
        $firstClass = $this->makeClass('X TKJ 1');
        $secondClass = $this->makeClass('XI TKJ 2');
        $firstTeacher = $this->makeTeacher('Budi Santoso');
        $secondTeacher = $this->makeTeacher('Siti Aminah');

        $this->actingAs($this->admin())->post(route('admin.subjects.store'), [
            'code' => 'KIM',
            'name' => 'Kimia',
            'description' => null,
            'schedules' => [
                [
                    'class_id' => $firstClass->id,
                    'day_of_week' => 1,
                    'start_time' => '07:30',
                    'end_time' => '09:00',
                    'teacher_id' => $firstTeacher->id,
                ],
                [
                    'class_id' => $secondClass->id,
                    'day_of_week' => 3,
                    'start_time' => '10:00',
                    'end_time' => '11:30',
                    'teacher_id' => $secondTeacher->id,
                ],
            ],
        ])->assertSessionHasNoErrors();

        $subject = Subject::where('code', 'KIM')->firstOrFail();

        $this->assertDatabaseCount('teaching_assignments', 2);
        $this->assertDatabaseHas('teaching_assignments', [
            'subject_id' => $subject->id,
            'class_id' => $firstClass->id,
            'teacher_id' => $firstTeacher->id,
        ]);
        $this->assertDatabaseHas('teaching_assignments', [
            'subject_id' => $subject->id,
            'class_id' => $secondClass->id,
            'teacher_id' => $secondTeacher->id,
        ]);

        $index = $this->actingAs($this->admin())->get(route('admin.subjects.index'));
        $index->assertSee('Budi Santoso');
        $index->assertSee('Siti Aminah');
    }

    public function test_store_rejects_an_unknown_teacher_without_creating_the_subject(): void
    {
        $class = $this->makeClass('X TKJ 1');

        $response = $this->actingAs($this->admin())->post(route('admin.subjects.store'), [
            'code' => 'FIS',
            'name' => 'Fisika',
            'description' => null,
            'schedules' => [
                [
                    'class_id' => $class->id,
                    'day_of_week' => 1,
                    'start_time' => '07:30',
                    'end_time' => '09:00',
                    'teacher_id' => 9999,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('schedules.0.teacher_id');
        $this->assertDatabaseCount('subjects', 0);
        $this->assertDatabaseCount('teaching_assignments', 0);
    }

    public function test_store_rejects_a_user_who_is_not_a_teacher(): void
    {
        $class = $this->makeClass('X TKJ 1');
        $student = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.subjects.store'), [
            'code' => 'FIS',
            'name' => 'Fisika',
            'description' => null,
            'schedules' => [
                [
                    'class_id' => $class->id,
                    'day_of_week' => 1,
                    'start_time' => '07:30',
                    'end_time' => '09:00',
                    'teacher_id' => $student->id,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('schedules.0.teacher_id');
        $this->assertDatabaseCount('subjects', 0);
        $this->assertDatabaseCount('teaching_assignments', 0);
    }

    public function test_store_rejects_different_teachers_for_the_same_class(): void
    {
        $class = $this->makeClass('X TKJ 1');
        $firstTeacher = $this->makeTeacher('Budi Santoso');
        $secondTeacher = $this->makeTeacher('Siti Aminah');

        $response = $this->actingAs($this->admin())->post(route('admin.subjects.store'), [
            'code' => 'FIS',
            'name' => 'Fisika',
            'description' => null,
            'schedules' => [
                [
                    'class_id' => $class->id,
                    'day_of_week' => 1,
                    'start_time' => '07:30',
                    'end_time' => '09:00',
                    'teacher_id' => $firstTeacher->id,
                ],
                [
                    'class_id' => $class->id,
                    'day_of_week' => 3,
                    'start_time' => '10:00',
                    'end_time' => '11:30',
                    'teacher_id' => $secondTeacher->id,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('schedules.1.teacher_id');
        $this->assertDatabaseCount('subjects', 0);
        $this->assertDatabaseCount('teaching_assignments', 0);
    }

    public function test_edit_form_preserves_the_selected_teacher_after_a_validation_error(): void
    {
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'description' => null]);
        $class = $this->makeClass('X TKJ 1');
        $firstTeacher = $this->makeTeacher('Budi Santoso');
        $secondTeacher = $this->makeTeacher('Siti Aminah');

        $this->createSchedule($subject, $class, 1, '07:30', '09:00');

        TeachingAssignment::create([
            'academic_year_id' => $class->academic_year_id,
            'teacher_id' => $firstTeacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs($this->admin())->put(route('admin.subjects.update', $subject), [
            'code' => '',
            'name' => 'Matematika',
            'description' => null,
            'schedules' => [
                [
                    'class_id' => $class->id,
                    'day_of_week' => 1,
                    'start_time' => '07:30',
                    'end_time' => '09:00',
                    'teacher_id' => $secondTeacher->id,
                ],
            ],
        ])->assertSessionHasErrors('code');

        $response = $this->actingAs($this->admin())->get(route('admin.subjects.edit', $subject));

        $response->assertOk();
        $response->assertSee('value="'.$secondTeacher->id.'" selected', false);
    }

    public function test_deleting_a_subject_removes_its_teaching_assignments(): void
    {
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'description' => null]);
        $class = $this->makeClass('X TKJ 1');
        $teacher = $this->makeTeacher('Budi Santoso');

        $this->createSchedule($subject, $class, 1, '07:30', '09:00');

        TeachingAssignment::create([
            'academic_year_id' => $class->academic_year_id,
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);

        $response = $this->actingAs($this->admin())
            ->delete(route('admin.subjects.destroy', $subject));

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHas('success');
        $this->assertModelMissing($subject);
        $this->assertDatabaseCount('teaching_assignments', 0);
    }

    private function makeClass(string $name = 'X TKJ 1'): Classes
    {
        $academicYear = AcademicYear::firstOrCreate(
            ['name' => '2025/2026'],
            [
                'start_date' => '2025-07-14',
                'end_date' => '2026-06-30',
                'is_active' => true,
            ],
        );

        return Classes::create([
            'academic_year_id' => $academicYear->id,
            'name' => $name,
            'code' => 'CLS-'.substr(md5($name), 0, 6),
        ]);
    }

    private function createSchedule(
        Subject $subject,
        Classes $class,
        int $dayOfWeek,
        string $startTime,
        string $endTime,
    ): ClassSchedule {
        return $subject->schedules()->create([
            'class_id' => $class->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    private function makeTeacher(string $name = 'Budi Santoso'): User
    {
        return User::factory()->create([
            'name' => $name,
            'role' => 'teacher',
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
