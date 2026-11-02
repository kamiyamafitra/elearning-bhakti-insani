<?php

namespace Tests\Feature;

use App\Http\Requests\ConvertToPdfRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PdfConverterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function roles(): array
    {
        return [
            'teacher' => ['teacher', 'teacher.pdf-converter'],
            'student' => ['student', 'student.pdf-converter'],
        ];
    }

    #[DataProvider('roles')]
    public function test_sidebar_links_to_the_converter_instead_of_the_profile(string $role, string $routeName): void
    {
        $this->actingAs($this->user($role))->get(route("{$routeName}.index"))
            ->assertOk()
            ->assertSee('Konversi PDF')
            ->assertSee(route("{$routeName}.store"), false)
            ->assertDontSee('<i class="bi bi-person"></i> Profil Saya', false);
    }

    public function test_other_roles_and_guests_cannot_use_it(): void
    {
        $this->get(route('student.pdf-converter.index'))->assertRedirect();
        $this->actingAs($this->user('admin'))->get(route('student.pdf-converter.index'))->assertForbidden();
        $this->actingAs($this->user('student'))->get(route('teacher.pdf-converter.index'))->assertForbidden();
    }

    public function test_images_become_one_pdf_with_a_page_each(): void
    {
        $response = $this->convert([
            UploadedFile::fake()->image('Foto Tugas.png', 400, 300),
            UploadedFile::fake()->image('halaman-2.jpg', 300, 400),
            UploadedFile::fake()->image('halaman-3.webp', 200, 200),
        ]);

        $response->assertOk()->assertDownload('foto-tugas.pdf');

        $pdf = file_get_contents($response->baseResponse->getFile()->getPathname());

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('/Count 3', $pdf);
        $this->assertStringContainsString('/MediaBox [0 0 841.89 595.28]', $pdf);
        $this->assertStringContainsString('/MediaBox [0 0 595.28 841.89]', $pdf);
        $this->assertCrossReferenceTableIsAccurate($pdf);
    }

    /**
     * @param  callable(): array<int, UploadedFile>  $makeFiles
     */
    #[DataProvider('rejectedUploads')]
    public function test_invalid_uploads_are_rejected(callable $makeFiles): void
    {
        $this->convert($makeFiles())->assertSessionHasErrors();
    }

    /**
     * @return array<string, array{callable(): array<int, UploadedFile>}>
     */
    public static function rejectedUploads(): array
    {
        return [
            'unsupported type' => [fn () => [UploadedFile::fake()->create('virus.exe', 10)]],
            'office document' => [fn () => [UploadedFile::fake()->create('tugas.docx', 10)]],
            'file over the size limit' => [fn () => [UploadedFile::fake()->create('besar.png', ConvertToPdfRequest::MAX_FILE_KILOBYTES + 1)]],
            'too many images' => [fn () => array_map(fn (int $number) => UploadedFile::fake()->image("{$number}.png"), range(0, ConvertToPdfRequest::MAX_FILES))],
            'text renamed as an image' => [fn () => [UploadedFile::fake()->createWithContent('palsu.png', 'bukan gambar')]],
        ];
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function convert(array $files): TestResponse
    {
        return $this->actingAs($this->user('student'))
            ->post(route('student.pdf-converter.store'), ['files' => $files]);
    }

    private function assertCrossReferenceTableIsAccurate(string $pdf): void
    {
        preg_match('/startxref\n(\d+)\n/', $pdf, $start);
        $this->assertSame('xref', substr($pdf, (int) $start[1], 4));

        preg_match_all('/^(\d{10}) 00000 n $/m', substr($pdf, (int) $start[1]), $entries);

        foreach ($entries[1] as $index => $offset) {
            $this->assertStringStartsWith(($index + 1).' 0 obj', substr($pdf, (int) $offset, 20));
        }
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }
}
