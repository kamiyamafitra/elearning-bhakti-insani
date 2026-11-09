<?php

namespace Tests\Feature;

use App\Models\Classes;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\ReportCardSetting;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class ReportCardTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    private Classes $class;

    private Subject $subject;

    private User $teacher;

    private User $andi;

    private User $bunga;

    /**
     * Andi: tugas 80/100 and 45/50 (average 85), kuis 70 -> 85 x 60% + 70 x 40% = 79,0 (C, tuntas).
     * Bunga: tugas missed (0) and 25/50 (average 25), kuis missed (0) -> 15,0 (D, belum tuntas).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->class = $this->makeClass('X TKJ 1');
        $this->subject = $this->makeSubject('Jaringan Dasar', 'JD');
        $this->teacher = $this->makeTeacher();
        $this->assignTeacher($this->teacher, $this->class, $this->subject);
        $this->andi = $this->makeStudent($this->class, 'Andi Saputra', '24001');
        $this->bunga = $this->makeStudent($this->class, 'Bunga Lestari', '24002');

        $topology = $this->makeAssignment($this->subject, $this->class, $this->teacher, ['title' => 'Topologi', 'max_score' => 100, 'due_at' => now()->subDays(3)]);
        $cabling = $this->makeAssignment($this->subject, $this->class, $this->teacher, ['title' => 'Kabel', 'max_score' => 50, 'due_at' => now()->subDays(2)]);
        $this->makeAssignment($this->subject, $this->class, $this->teacher, ['title' => 'Subnet', 'due_at' => now()->addDays(3)]);

        $this->makeSubmission($topology, $this->andi, ['score' => 80, 'feedback' => 'Rapi sekali.', 'graded_at' => now()]);
        $this->makeSubmission($cabling, $this->andi, ['score' => 45, 'graded_at' => now()]);
        $this->makeSubmission($cabling, $this->bunga, ['score' => 25, 'graded_at' => now()]);

        $quiz = Quiz::factory()->create([
            'subject_id' => $this->subject->id,
            'class_id' => $this->class->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Bab Satu',
            'opens_at' => now()->subDays(2),
            'closes_at' => now()->subDay(),
        ]);

        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $this->andi->id,
            'question_order' => [],
            'started_at' => now()->subDays(2),
            'deadline_at' => now()->subDays(2)->addMinutes(30),
            'submitted_at' => now()->subDays(2)->addMinutes(20),
            'score' => 70,
        ]);
    }

    public function test_final_score_weighs_categories_and_hands_an_empty_categorys_weight_to_the_other(): void
    {
        $setting = new ReportCardSetting(['assignment_weight' => 60, 'quiz_weight' => 40, 'passing_score' => 75]);

        $this->assertSame(79.0, $setting->finalScore(85.0, 70.0));
        $this->assertSame(85.0, $setting->finalScore(85.0, null));
        $this->assertSame(70.0, $setting->finalScore(null, 70.0));
        $this->assertNull($setting->finalScore(null, null));

        $quizOnly = new ReportCardSetting(['assignment_weight' => 0, 'quiz_weight' => 100, 'passing_score' => 75]);

        $this->assertSame(70.0, $quizOnly->finalScore(85.0, 70.0));
        $this->assertNull($quizOnly->finalScore(85.0, null));
    }

    public function test_predicate_splits_the_range_above_the_kkm_into_three_equal_steps(): void
    {
        $setting = new ReportCardSetting(['passing_score' => 75]);

        $this->assertSame('A', $setting->predicate(100));
        $this->assertSame('A', $setting->predicate(91.7));
        $this->assertSame('B', $setting->predicate(91.6));
        $this->assertSame('B', $setting->predicate(83.4));
        $this->assertSame('C', $setting->predicate(83.3));
        $this->assertSame('C', $setting->predicate(75));
        $this->assertSame('D', $setting->predicate(74.9));
        $this->assertSame(['A' => 91.7, 'B' => 83.3, 'C' => 75.0], $setting->predicateScale());
    }

    public function test_teacher_sees_weighted_report_card_with_missed_work_counted_as_zero(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.grades.show', [$this->subject, $this->class]));

        $response->assertOk();
        $response->assertViewHas('subjectRecap', function (array $recap): bool {
            $andi = $recap['students'][$this->andi->id]['report'];
            $bunga = $recap['students'][$this->bunga->id]['report'];

            return $recap['setting']->assignment_weight === 60
                && $recap['setting']->quiz_weight === 40
                && $andi === ['assignment_average' => 85.0, 'quiz_average' => 70.0, 'final' => 79.0, 'predicate' => 'C', 'passed' => true, 'ungraded' => 0]
                && $bunga === ['assignment_average' => 25.0, 'quiz_average' => 0.0, 'final' => 15.0, 'predicate' => 'D', 'passed' => false, 'ungraded' => 0];
        });
        $response->assertSee('79,0');
        $response->assertSee('15,0');
        $response->assertSee('Belum tuntas');
        $response->assertSee('Tugas 60% · Kuis 40% · KKM 75');
    }

    public function test_teacher_overview_links_to_each_report_card_and_lists_work_waiting_for_a_grade(): void
    {
        $essay = $this->makeAssignment($this->subject, $this->class, $this->teacher, ['title' => 'Esai Jaringan']);
        $this->makeSubmission($essay, $this->bunga);

        $this->actingAs($this->teacher)->get(route('teacher.grades.index'))
            ->assertOk()
            ->assertSee(route('teacher.grades.show', [$this->subject, $this->class]), false)
            ->assertSee('1 Pengumpulan')
            ->assertSee('Esai Jaringan')
            ->assertSee('Bunga Lestari')
            ->assertSee(route('teacher.assignments.show', $essay), false);
    }

    public function test_teacher_can_change_the_weights_and_kkm(): void
    {
        $this->actingAs($this->teacher)
            ->put(route('teacher.grades.settings', [$this->subject, $this->class]), ['assignment_weight' => 30, 'passing_score' => 80])
            ->assertRedirect(route('teacher.grades.show', [$this->subject, $this->class]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('report_card_settings', [
            'subject_id' => $this->subject->id,
            'class_id' => $this->class->id,
            'assignment_weight' => 30,
            'quiz_weight' => 70,
            'passing_score' => 80,
        ]);

        // 85 x 30% + 70 x 70% = 74,5, below the new KKM of 80.
        $this->actingAs($this->teacher)->get(route('teacher.grades.show', [$this->subject, $this->class]))
            ->assertViewHas('subjectRecap', fn (array $recap) => $recap['students'][$this->andi->id]['report']['final'] === 74.5
                && $recap['students'][$this->andi->id]['report']['passed'] === false);
    }

    public function test_settings_must_be_whole_percentages_and_a_real_kkm(): void
    {
        $this->actingAs($this->teacher)
            ->from(route('teacher.grades.show', [$this->subject, $this->class]))
            ->put(route('teacher.grades.settings', [$this->subject, $this->class]), ['assignment_weight' => 120, 'passing_score' => 0])
            ->assertSessionHasErrors(['assignment_weight', 'passing_score']);

        $this->assertDatabaseCount('report_card_settings', 0);
    }

    public function test_teacher_cannot_open_report_cards_for_classes_they_do_not_teach(): void
    {
        $otherTeacher = $this->makeTeacher('Citra Dewi', '198501012010012002');
        $otherClass = $this->makeClass('X TKJ 2');

        $this->actingAs($otherTeacher)->get(route('teacher.grades.show', [$this->subject, $this->class]))->assertNotFound();
        $this->actingAs($otherTeacher)->get(route('teacher.grades.export', [$this->subject, $this->class]))->assertNotFound();
        $this->actingAs($otherTeacher)->put(route('teacher.grades.settings', [$this->subject, $this->class]), ['assignment_weight' => 50, 'passing_score' => 70])->assertNotFound();
        $this->actingAs($this->teacher)->get(route('teacher.grades.show', [$this->subject, $otherClass]))->assertNotFound();
        $this->actingAs($this->andi)->get(route('teacher.grades.show', [$this->subject, $this->class]))->assertForbidden();
    }

    public function test_teacher_can_download_the_report_card_as_excel(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.grades.export', [$this->subject, $this->class]));

        $response->assertOk();
        $response->assertDownload('nilai-rapor-jaringan-dasar-x-tkj-1.xlsx');

        $sheets = $this->readSheets($response->getFile()->getPathname());

        $this->assertSame(['Nilai Rapor', 'Rincian'], array_keys($sheets));
        $this->assertSame(['No', 'NIS', 'Nama Siswa', 'Rata-rata Tugas', 'Rata-rata Kuis', 'Nilai Rapor', 'Predikat', 'Keterangan'], $sheets['Nilai Rapor'][2]);
        $this->assertSame([1, '24001', 'Andi Saputra', 85, 70, 79, 'C', 'Tuntas'], $sheets['Nilai Rapor'][3]);
        $this->assertSame([2, '24002', 'Bunga Lestari', 25, 0, 15, 'D', 'Belum tuntas'], $sheets['Nilai Rapor'][4]);
        $this->assertSame([2, '24002', 'Bunga Lestari', 'Tidak mengumpulkan', 25, 'Tidak mengumpulkan', '-'], $sheets['Rincian'][3]);
    }

    public function test_student_sees_only_their_own_report_card_with_feedback(): void
    {
        $response = $this->actingAs($this->andi)->get(route('student.grades.index'));

        $response->assertOk();
        $response->assertViewHas('subjects', fn ($subjects) => $subjects->count() === 1
            && $subjects[0]['result']['report']['final'] === 79.0);
        $response->assertSee('Jaringan Dasar');
        $response->assertSee('79,0');
        $response->assertSee('Tuntas');
        $response->assertSee('Rapi sekali.');
        $response->assertSee('Kuis: Bab Satu');
        $response->assertDontSee('Bunga Lestari');
        $response->assertDontSee('15,0');

        $this->actingAs($this->bunga)->get(route('student.grades.index'))
            ->assertSee('15,0')
            ->assertSee('Belum tuntas')
            ->assertSee('Tidak dikerjakan');
    }

    public function test_admin_subject_recap_shows_the_report_card_grade(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.grades.index', ['class_id' => $this->class->id, 'subject_id' => $this->subject->id]))
            ->assertOk()
            ->assertSee('Nilai Rapor')
            ->assertSee('79,0')
            ->assertSee('Tugas 60% · Kuis 40%, KKM 75');
    }

    /**
     * @return array<string, list<list<mixed>>>
     */
    private function readSheets(string $path): array
    {
        $reader = new XlsxReader;
        $reader->open($path);
        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }

        $reader->close();

        return $sheets;
    }
}
