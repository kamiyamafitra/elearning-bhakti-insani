<?php

namespace Tests\Feature\Admin;

use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Once;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class SchoolSettingTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_defaults_are_used_until_settings_are_saved(): void
    {
        $this->assertDatabaseCount('school_settings', 0);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('SMK Bhakti Insani')
            ->assertSee('Bogor');
    }

    public function test_admin_can_update_settings_and_they_appear_everywhere(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.settings.update'), $this->payload([
            'name' => 'SMK Harapan Bangsa',
            'city' => 'Depok',
            'address' => 'Jl. Merdeka No. 1',
            'phone' => '021-123456',
            'semester' => 'genap',
        ]))->assertRedirect(route('admin.settings.edit'));

        $this->assertDatabaseCount('school_settings', 1);
        $this->assertDatabaseHas('activity_logs', ['action' => 'created', 'description' => 'menambahkan pengaturan sekolah "SMK Harapan Bangsa"']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertSee('SMK Harapan Bangsa Depok')
            ->assertDontSee('SMK Bhakti Insani')
            ->assertSee('Semester Genap');

        auth()->logout();
        $this->get(route('login'))
            ->assertSee('SMK Harapan Bangsa')
            ->assertSee('Jl. Merdeka No. 1')
            ->assertDontSee('SMK Bhakti Insani');
    }

    public function test_saving_twice_keeps_one_row(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.settings.update'), $this->payload(['name' => 'Pertama']));
        Once::flush();
        $this->actingAs($admin)->put(route('admin.settings.update'), $this->payload(['name' => 'Kedua']));

        $this->assertDatabaseCount('school_settings', 1);
        $this->assertDatabaseHas('school_settings', ['name' => 'Kedua']);
    }

    public function test_logo_can_be_uploaded_replaced_served_publicly_and_removed(): void
    {
        $admin = $this->admin();
        $disk = Storage::disk('local');

        $this->actingAs($admin)->put(route('admin.settings.update'), $this->payload(['logo' => UploadedFile::fake()->image('logo.png', 100, 100)]));
        $firstLogo = SchoolSetting::first()->logo_path;
        $disk->assertExists($firstLogo);

        auth()->logout();
        $this->get(route('school-logo'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('login'))->assertSee('/school-logo', false);

        $this->actingAs($admin)->put(route('admin.settings.update'), $this->payload(['logo' => UploadedFile::fake()->image('baru.png', 100, 100)]));
        $secondLogo = SchoolSetting::first()->logo_path;
        $this->assertNotSame($firstLogo, $secondLogo);
        $disk->assertMissing($firstLogo);

        $this->actingAs($admin)->put(route('admin.settings.update'), $this->payload(['remove_logo' => '1']));
        $disk->assertMissing($secondLogo);
        $this->assertNull(SchoolSetting::first()->logo_path);
        $this->get(route('school-logo'))->assertNotFound();
    }

    public function test_unsafe_or_oversized_logos_and_bad_values_are_rejected(): void
    {
        $admin = $this->admin();

        foreach ([
            UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->create('logo.png', 2048, 'image/png'),
        ] as $logo) {
            $this->actingAs($admin)->put(route('admin.settings.update'), $this->payload(['logo' => $logo]))
                ->assertSessionHasErrors('logo');
        }

        $this->actingAs($admin)->put(route('admin.settings.update'), $this->payload(['name' => '', 'semester' => 'pendek']))
            ->assertSessionHasErrors(['name', 'semester']);

        $this->assertDatabaseCount('school_settings', 0);
    }

    public function test_non_admins_cannot_change_settings(): void
    {
        $teacher = $this->makeTeacher();

        $this->actingAs($teacher)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($teacher)->put(route('admin.settings.update'), $this->payload())->assertForbidden();

        $this->assertDatabaseCount('school_settings', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'SMK Bhakti Insani', 'city' => 'Bogor', 'semester' => 'ganjil'], $overrides);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }
}
