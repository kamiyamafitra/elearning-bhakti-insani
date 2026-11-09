<?php

namespace Tests\Feature;

use App\Models\Classes;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class FilePreviewTest extends TestCase
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

        Storage::fake('local');
        $this->class = $this->makeClass('X TKJ 1');
        $this->subject = $this->makeSubject('Jaringan Dasar', 'JD');
        $this->teacher = $this->makeTeacher();
        $this->assignTeacher($this->teacher, $this->class, $this->subject);
        $this->student = $this->makeStudent($this->class, 'Andi', '24001');
    }

    public function test_pdf_material_opens_inline_for_preview_and_still_downloads_normally(): void
    {
        $material = $this->materialWithFile('materials/modul.pdf', 'Modul Jaringan.pdf', '%PDF-1.4 isi');
        $url = route('student.materials.download', [$this->subject, $material]);

        $this->actingAs($this->student)->get(route('student.subjects.show', $this->subject))
            ->assertSee('data-file-preview="pdf"', false)
            ->assertSee('data-preview-url="'.$url.'?inline=1"', false);

        $preview = $this->actingAs($this->student)->get($url.'?inline=1');
        $preview->assertOk();
        $this->assertSame('application/pdf', $preview->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline;', $preview->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $preview->headers->get('X-Content-Type-Options'));

        $download = $this->actingAs($this->student)->get($url);
        $download->assertOk();
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
    }

    public function test_text_files_are_shown_as_plain_text_inside_a_sandbox(): void
    {
        $material = $this->materialWithFile('materials/catatan.txt', 'catatan.txt', '<script>alert(1)</script>');

        $response = $this->actingAs($this->student)->get(route('student.materials.download', [$this->subject, $material]).'?inline=1');

        $response->assertOk();
        $this->assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
    }

    public function test_word_and_spreadsheet_files_get_their_own_preview(): void
    {
        $word = $this->materialWithFile('materials/modul.docx', 'Modul.docx', 'isi');
        $sheet = $this->materialWithFile('materials/nilai.xlsx', 'Nilai.xlsx', 'isi');

        $this->actingAs($this->student)->get(route('student.subjects.show', $this->subject))
            ->assertSee('data-file-preview="word"', false)
            ->assertSee('data-file-preview="sheet"', false);

        $response = $this->actingAs($this->student)->get(route('student.materials.download', [$this->subject, $sheet]).'?inline=1');
        $response->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));

        $download = $this->actingAs($this->student)->get(route('student.materials.download', [$this->subject, $word]));
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
    }

    public function test_presentations_have_no_preview_button_and_always_download(): void
    {
        $material = $this->materialWithFile('materials/modul.pptx', 'Modul.pptx', 'isi');
        $url = route('student.materials.download', [$this->subject, $material]);

        $this->actingAs($this->student)->get(route('student.materials.show', [$this->subject, $material]))
            ->assertOk()
            ->assertDontSee('data-file-preview="', false)
            ->assertSee('Unduh Materi');

        $response = $this->actingAs($this->student)->get($url.'?inline=1');
        $this->assertStringStartsWith('attachment;', $response->headers->get('Content-Disposition'));
    }

    public function test_preview_keeps_the_same_access_rules_as_download(): void
    {
        $material = $this->materialWithFile('materials/modul.pdf', 'Modul.pdf', '%PDF-1.4');
        $outsider = $this->makeStudent($this->makeClass('XI TKJ 1'), 'Budi', '24002');

        $this->actingAs($outsider)
            ->get(route('student.materials.download', [$this->subject, $material]).'?inline=1')
            ->assertNotFound();
    }

    public function test_teacher_previews_an_image_submission_and_students_can_submit_photos(): void
    {
        $assignment = $this->makeAssignment($this->subject, $this->class, $this->teacher);

        $this->actingAs($this->student)->post(route('student.assignments.submit', [$this->subject, $assignment]), [
            'file' => UploadedFile::fake()->image('foto-tugas.jpg', 800, 600),
        ])->assertSessionHasNoErrors();

        $submission = $assignment->submissions()->firstOrFail();
        $this->assertStringEndsWith('.jpg', $submission->file_path);

        $url = route('teacher.submissions.download', [$assignment, $submission]);

        $this->actingAs($this->teacher)->get(route('teacher.assignments.show', $assignment))
            ->assertSee('data-file-preview="image"', false);

        $response = $this->actingAs($this->teacher)->get($url.'?inline=1');
        $response->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
    }

    private function materialWithFile(string $path, string $name, string $contents)
    {
        Storage::disk('local')->put($path, $contents);

        return $this->makeMaterial($this->subject, $this->class, $this->teacher, [
            'file_path' => $path,
            'file_original_name' => $name,
            'file_size' => strlen($contents),
        ]);
    }
}
