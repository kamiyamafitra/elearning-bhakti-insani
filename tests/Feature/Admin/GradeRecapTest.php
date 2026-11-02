<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Classes;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;
use ZipArchive;

class GradeRecapTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    private Classes $class;

    private Subject $networking;

    private Subject $database;

    private User $teacher;

    private User $andi;

    private User $bunga;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = $this->makeClass('X TKJ 1');
        $this->networking = $this->makeSubject('Jaringan Dasar', 'JD');
        $this->database = $this->makeSubject('Basis Data', 'BD');
        $this->teacher = $this->makeTeacher();
        $this->andi = $this->makeStudent($this->class, 'Andi Saputra', '24001');
        $this->bunga = $this->makeStudent($this->class, 'Bunga Lestari', '24002');
    }

    public function test_non_admins_cannot_see_grade_recaps(): void
    {
        $this->get(route('admin.grades.index'))->assertRedirect(route('login'));

        $this->actingAs($this->teacher)->get(route('admin.grades.index'))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('admin.grades.export', ['class_id' => $this->class->id]))->assertForbidden();
    }

    public function test_sidebar_link_is_enabled_and_page_asks_for_a_class(): void
    {
        $this->actingAs($this->admin())->get(route('admin.grades.index'))
            ->assertOk()
            ->assertSee(route('admin.grades.index'), false)
            ->assertSee('Pilih kelas untuk melihat rekap nilai.')
            ->assertViewHas('class', null);
    }

    public function test_subject_detail_shows_each_assignment_result(): void
    {
        $graded = $this->makeAssignment($this->networking, $this->class, $this->teacher, ['title' => 'Topologi', 'max_score' => 50, 'due_at' => now()->subDays(2)]);
        $pastDue = $this->makeAssignment($this->networking, $this->class, $this->teacher, ['title' => 'Kabel', 'due_at' => now()->subDay()]);
        $upcoming = $this->makeAssignment($this->networking, $this->class, $this->teacher, ['title' => 'Subnet', 'due_at' => now()->addDays(3)]);
        $this->makeAssignment($this->networking, $this->class, $this->teacher, ['title' => 'Draf', 'status' => 'draft']);

        $this->makeSubmission($graded, $this->andi, ['score' => 40, 'graded_at' => now()]);
        $this->makeSubmission($pastDue, $this->andi);

        $response = $this->actingAs($this->admin())->get(route('admin.grades.index', [
            'class_id' => $this->class->id,
            'subject_id' => $this->networking->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('selectedSubject', function (array $subject) use ($graded, $pastDue, $upcoming): bool {
            $andi = $subject['students'][$this->andi->id];
            $bunga = $subject['students'][$this->bunga->id];

            return $subject['assignments']->pluck('title')->all() === ['Topologi', 'Kabel', 'Subnet']
                && $andi['cells']["assignment-{$graded->id}"]['status'] === 'graded'
                && $andi['cells']["assignment-{$pastDue->id}"]['status'] === 'ungraded'
                && $andi['cells']["assignment-{$upcoming->id}"]['status'] === 'pending'
                && $andi['average'] === 80.0
                && $andi['missing'] === 0
                && $bunga['cells']["assignment-{$graded->id}"]['status'] === 'missing'
                && $bunga['average'] === null
                && $bunga['missing'] === 2;
        });
        $response->assertSee('Belum dinilai');
        $response->assertSee('Tidak ada');
        $response->assertDontSee('Draf');
    }

    public function test_summary_averages_each_subject_and_overall_on_a_0_to_100_scale(): void
    {
        $networkingTask = $this->makeAssignment($this->networking, $this->class, $this->teacher, ['max_score' => 100]);
        $databaseTask = $this->makeAssignment($this->database, $this->class, $this->teacher, ['max_score' => 20]);

        $this->makeSubmission($networkingTask, $this->andi, ['score' => 90, 'graded_at' => now()]);
        $this->makeSubmission($databaseTask, $this->andi, ['score' => 14, 'graded_at' => now()]);

        $response = $this->actingAs($this->admin())->get(route('admin.grades.index', ['class_id' => $this->class->id]));

        $response->assertViewHas('subjects', fn (Collection $subjects) => $subjects->pluck('subject.name')->all() === ['Basis Data', 'Jaringan Dasar']
            && $subjects[0]['students'][$this->andi->id]['average'] === 70.0
            && $subjects[1]['students'][$this->andi->id]['average'] === 90.0);
        $response->assertViewHas('students', fn (Collection $students) => $students->firstWhere('id', $this->andi->id)['average'] === 80.0
            && $students->firstWhere('id', $this->bunga->id)['average'] === null);
        $response->assertSee('80,0');
    }

    public function test_promoted_students_still_appear_in_their_old_class(): void
    {
        $nextYear = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-13', 'end_date' => '2027-06-30', 'is_active' => false]);
        $nextClass = $this->makeClass('XI TKJ 1', $nextYear);

        $this->andi->studentProfile->update(['class_id' => $nextClass->id]);

        $this->actingAs($this->admin())->get(route('admin.grades.index', ['class_id' => $this->class->id]))
            ->assertViewHas('students', fn (Collection $students) => $students->pluck('name')->all() === ['Andi Saputra', 'Bunga Lestari']);
    }

    public function test_excel_export_has_a_summary_and_one_sheet_per_subject(): void
    {
        $task = $this->makeAssignment($this->networking, $this->class, $this->teacher, ['title' => 'Topologi', 'max_score' => 100, 'due_at' => now()->subDay()]);
        $this->makeSubmission($task, $this->andi, ['score' => 85, 'graded_at' => now()]);
        $this->assignTeacher($this->teacher, $this->class, $this->database);

        $response = $this->actingAs($this->admin())->get(route('admin.grades.export', ['class_id' => $this->class->id]));

        $response->assertOk();
        $response->assertDownload('rekap-nilai-x-tkj-1.xlsx');

        $sheets = $this->readSheets($response->getFile()->getPathname());

        $this->assertSame(['Ringkasan', 'Basis Data', 'Jaringan Dasar'], array_keys($sheets));
        $this->assertSame(['No', 'NIS', 'Nama Siswa', 'Basis Data', 'Jaringan Dasar', 'Rata-rata', 'Tidak Mengumpulkan'], $sheets['Ringkasan'][1]);
        $this->assertSame([1, '24001', 'Andi Saputra', '-', 85, 85, 0], $sheets['Ringkasan'][2]);
        $this->assertSame([2, '24002', 'Bunga Lestari', 'Tidak mengumpulkan', '-', 1], [
            $sheets['Jaringan Dasar'][2][0], $sheets['Jaringan Dasar'][2][1], $sheets['Jaringan Dasar'][2][2],
            $sheets['Jaringan Dasar'][2][3], $sheets['Jaringan Dasar'][2][4], $sheets['Jaringan Dasar'][2][5],
        ]);
    }

    public function test_excel_export_is_styled_and_highlights_missing_work(): void
    {
        $this->makeAssignment($this->networking, $this->class, $this->teacher, ['title' => 'Topologi', 'max_score' => 100, 'due_at' => now()->subDay()]);

        $response = $this->actingAs($this->admin())->get(route('admin.grades.export', ['class_id' => $this->class->id]));

        $zip = new ZipArchive;
        $zip->open($response->getFile()->getPathname());
        $styles = $zip->getFromName('xl/styles.xml');
        $summarySheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        foreach (['1E40AF', '2563EB', 'FEE2E2'] as $color) {
            $this->assertStringContainsString($color, $styles);
        }

        $this->assertStringContainsString('<mergeCell ref="A1:F1"/>', $summarySheet);
        $this->assertStringContainsString('ySplit="2"', $summarySheet);
        $this->assertStringContainsString('<cols>', $summarySheet);
    }

    public function test_export_requires_a_valid_class(): void
    {
        $this->actingAs($this->admin())->get(route('admin.grades.export'))->assertSessionHasErrors('class_id');
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

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
