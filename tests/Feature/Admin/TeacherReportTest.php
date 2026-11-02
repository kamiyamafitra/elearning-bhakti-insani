<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class TeacherReportTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    public function test_non_admins_cannot_see_the_report(): void
    {
        $this->get(route('admin.reports.teachers'))->assertRedirect(route('login'));

        $teacher = $this->makeTeacher();

        $this->actingAs($teacher)->get(route('admin.reports.teachers'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.reports.teachers.export'))->assertForbidden();
    }

    public function test_empty_report_explains_what_it_contains(): void
    {
        $this->makeAcademicYear();

        $this->actingAs($this->admin())->get(route('admin.reports.teachers'))
            ->assertOk()
            ->assertSee('Belum ada data aktivitas guru.');
    }

    public function test_report_counts_activity_per_teacher_class_and_subject(): void
    {
        $class = $this->makeClass('X TKJ 1');
        $otherClass = $this->makeClass('X TKJ 2');
        $networking = $this->makeSubject('Jaringan Dasar', 'JD');
        $database = $this->makeSubject('Basis Data', 'BD');
        $teacher = $this->makeTeacher('Budi Santoso', '111');
        $idleTeacher = $this->makeTeacher('Ani Lestari', '222');
        $first = $this->makeStudent($class, 'Andi', '24001');
        $second = $this->makeStudent($class, 'Bunga', '24002');

        $this->assignTeacher($teacher, $class, $networking);
        $this->assignTeacher($teacher, $otherClass, $database);
        $this->assignTeacher($idleTeacher, $class, $database);

        $this->makeMaterial($networking, $class, $teacher);
        $this->makeMaterial($networking, $class, $teacher, ['is_published' => false]);
        $assignment = $this->makeAssignment($networking, $class, $teacher);
        $this->makeSubmission($assignment, $first, ['score' => 80, 'graded_at' => now()]);
        $this->makeSubmission($assignment, $second);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.teachers'));

        $response->assertOk();
        $response->assertViewHas('teachers', function (Collection $teachers) use ($teacher, $idleTeacher): bool {
            [$ani, $budi] = [$teachers[0], $teachers[1]];
            $networkingRow = $budi['rows']->firstWhere('subject', 'Jaringan Dasar');
            $databaseRow = $budi['rows']->firstWhere('subject', 'Basis Data');

            return $ani['teacher']->is($idleTeacher)
                && $ani['materials'] === 0
                && $budi['teacher']->is($teacher)
                && $budi['rows']->count() === 2
                && $budi['materials'] === 2
                && $budi['published_materials'] === 1
                && $budi['assignments'] === 1
                && $budi['submissions'] === 2
                && $budi['graded'] === 1
                && $networkingRow['class'] === 'X TKJ 1'
                && $databaseRow['class'] === 'X TKJ 2'
                && $databaseRow['materials'] === 0;
        });
        $response->assertViewHas('totals', fn (array $totals) => $totals['teachers'] === 2 && $totals['inactiveTeachers'] === 1);
        $response->assertSee('Belum ada aktivitas');
        $response->assertSee('1 / 2');
    }

    public function test_report_is_limited_to_the_selected_academic_year(): void
    {
        $currentClass = $this->makeClass('X TKJ 1');
        $oldYear = AcademicYear::create(['name' => '2024/2025', 'start_date' => '2024-07-15', 'end_date' => '2025-06-30', 'is_active' => false]);
        $oldClass = $this->makeClass('X TKJ Lama', $oldYear);
        $subject = $this->makeSubject('Jaringan Dasar', 'JD');
        $teacher = $this->makeTeacher();

        $this->makeMaterial($subject, $currentClass, $teacher);
        $this->makeMaterial($subject, $oldClass, $teacher);
        $this->makeMaterial($subject, $oldClass, $teacher);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.reports.teachers'))
            ->assertViewHas('totals', fn (array $totals) => $totals['materials'] === 1);

        $this->actingAs($admin)->get(route('admin.reports.teachers', ['academic_year_id' => $oldYear->id]))
            ->assertViewHas('totals', fn (array $totals) => $totals['materials'] === 2);
    }

    public function test_date_range_and_search_filters(): void
    {
        $class = $this->makeClass();
        $subject = $this->makeSubject('Jaringan Dasar', 'JD');
        $budi = $this->makeTeacher('Budi Santoso', '111');
        $ani = $this->makeTeacher('Ani Lestari', '222');

        $this->makeMaterial($subject, $class, $budi)->forceFill(['created_at' => '2025-08-01 10:00:00'])->save();
        $this->makeMaterial($subject, $class, $budi)->forceFill(['created_at' => '2025-10-01 10:00:00'])->save();
        $this->makeMaterial($subject, $class, $ani);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.reports.teachers', ['from' => '2025-07-01', 'to' => '2025-08-31']))
            ->assertViewHas('totals', fn (array $totals) => $totals['materials'] === 1);

        $this->actingAs($admin)->get(route('admin.reports.teachers', ['q' => 'budi']))
            ->assertViewHas('teachers', fn (Collection $teachers) => $teachers->pluck('name')->all() === ['Budi Santoso']);

        $this->actingAs($admin)->get(route('admin.reports.teachers', ['from' => '2025-09-01', 'to' => '2025-08-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_excel_export_contains_summary_and_detail_sheets(): void
    {
        $class = $this->makeClass('X TKJ 1');
        $subject = $this->makeSubject('Jaringan Dasar', 'JD');
        $teacher = $this->makeTeacher('Budi Santoso', '198001012005011001');
        $this->assignTeacher($teacher, $class, $subject);
        $this->makeMaterial($subject, $class, $teacher);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.teachers.export'));

        $response->assertOk();
        $response->assertDownload('laporan-aktivitas-guru-20252026.xlsx');

        $sheets = $this->readSheets($response->getFile()->getPathname());

        $this->assertSame(['Ringkasan', 'Rincian'], array_keys($sheets));
        $summaryRow = collect($sheets['Ringkasan'])->first(fn (array $row) => ($row[0] ?? null) === 'Budi Santoso');
        $detailRow = collect($sheets['Rincian'])->first(fn (array $row) => ($row[0] ?? null) === 'Budi Santoso');

        $this->assertSame('Laporan Aktivitas Guru - Tahun ajaran 2025/2026', $sheets['Ringkasan'][0][0]);
        $this->assertSame(['Budi Santoso', '198001012005011001', 1, 1, 1, 0, 0, 0], array_slice($summaryRow, 0, 8));
        $this->assertSame(['Budi Santoso', '198001012005011001', 'X TKJ 1', 'Jaringan Dasar', 1], array_slice($detailRow, 0, 5));
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
