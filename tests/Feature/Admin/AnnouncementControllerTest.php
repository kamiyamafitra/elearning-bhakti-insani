<?php

namespace Tests\Feature\Admin;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class AnnouncementControllerTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    public function test_admin_can_create_a_school_wide_announcement(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Rapat Orang Tua',
            'content' => 'Rapat akan dilaksanakan Sabtu.',
            'visibility' => Announcement::VISIBILITY_ALL,
            'is_published' => '1',
        ]);

        $response->assertRedirect(route('admin.announcements.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('announcements', [
            'title' => 'Rapat Orang Tua',
            'visibility' => Announcement::VISIBILITY_ALL,
            'is_published' => true,
            'created_by' => $admin->id,
        ]);
    }

    public function test_admin_can_target_an_announcement_to_a_class(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $class = $this->makeClass('10 PPLG');

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Info Kelas',
            'content' => 'Khusus kelas sepuluh.',
            'visibility' => Announcement::VISIBILITY_CLASS,
            'class_id' => $class->id,
            'is_published' => '1',
        ]);

        $response->assertRedirect(route('admin.announcements.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('announcements', [
            'title' => 'Info Kelas',
            'visibility' => Announcement::VISIBILITY_CLASS,
            'class_id' => $class->id,
        ]);
    }

    public function test_class_visibility_requires_a_class(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Info Kelas',
            'content' => 'Khusus kelas sepuluh.',
            'visibility' => Announcement::VISIBILITY_CLASS,
            'is_published' => '1',
        ])->assertSessionHasErrors('class_id');
    }

    public function test_role_visibility_requires_a_target_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Info Guru',
            'content' => 'Khusus guru.',
            'visibility' => Announcement::VISIBILITY_ROLE,
            'is_published' => '1',
        ])->assertSessionHasErrors('target_role');
    }

    public function test_edit_and_show_pages_render_existing_announcements(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $class = $this->makeClass('10 PPLG');
        $announcement = $this->makeAnnouncement([
            'title' => 'Pengumuman Kelas',
            'content' => 'Isi pengumuman kelas.',
            'visibility' => Announcement::VISIBILITY_CLASS,
            'class_id' => $class->id,
            'created_by' => $admin->id,
        ]);

        $edit = $this->actingAs($admin)->get(route('admin.announcements.edit', $announcement));
        $edit->assertOk();
        $edit->assertSee('Pengumuman Kelas');
        $edit->assertSee('Isi pengumuman kelas.');
        $edit->assertSee('10 PPLG');

        $show = $this->actingAs($admin)->get(route('admin.announcements.show', $announcement));
        $show->assertOk();
        $show->assertSee('Pengumuman Kelas');
        $show->assertSee('10 PPLG');
        $show->assertSee('Dipublikasikan');
    }

    public function test_announcement_disappears_for_guru_and_siswa_after_its_end_time(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = $this->makeStudent($this->makeClass('X TKJ 1'));
        $teacher = $this->makeTeacher();

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Libur Nasional',
            'content' => 'Sekolah libur besok.',
            'visibility' => Announcement::VISIBILITY_ALL,
            'is_published' => '1',
            'expires_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $announcement = Announcement::where('title', 'Libur Nasional')->firstOrFail();

        $this->actingAs($student)->get(route('student.announcements.index'))->assertSee('Libur Nasional');

        $this->travel(2)->days();

        $this->actingAs($student)->get(route('student.announcements.index'))->assertDontSee('Libur Nasional');
        $this->actingAs($student)->get(route('student.announcements.show', $announcement))->assertNotFound();
        $this->actingAs($teacher)->get(route('teacher.dashboard'))->assertDontSee('Libur Nasional');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertDontSee('Libur Nasional');

        $this->actingAs($admin)->get(route('admin.announcements.index'))->assertSee('Libur Nasional')->assertSee('Berakhir');
    }

    public function test_end_time_must_come_after_the_publish_time(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Info',
            'content' => 'Isi.',
            'visibility' => Announcement::VISIBILITY_ALL,
            'is_published' => '1',
            'published_at' => '2026-12-10T08:00',
            'expires_at' => '2026-12-09T08:00',
        ])->assertSessionHasErrors(['expires_at' => 'Tanggal berakhir harus setelah tanggal publikasi.']);

        $this->assertDatabaseMissing('announcements', ['title' => 'Info']);
    }

    public function test_editing_keeps_the_new_publish_and_end_times(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $announcement = $this->makeAnnouncement(['title' => 'Rapat Guru']);

        $this->actingAs($admin)->put(route('admin.announcements.update', $announcement), [
            'title' => 'Rapat Guru',
            'content' => 'Rapat di aula.',
            'visibility' => Announcement::VISIBILITY_ALL,
            'is_published' => '1',
            'published_at' => '2026-12-01T07:00',
            'expires_at' => '2026-12-05T15:00',
        ])->assertSessionHasNoErrors();

        $announcement->refresh();
        $this->assertSame('2026-12-01 07:00', $announcement->published_at->format('Y-m-d H:i'));
        $this->assertSame('2026-12-05 15:00', $announcement->expires_at->format('Y-m-d H:i'));
    }

    public function test_announcement_image_is_shown_as_a_thumbnail_only_to_allowed_readers(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $class = $this->makeClass('X TKJ 1');
        $student = $this->makeStudent($class, 'Andi', '24001');
        $outsider = $this->makeStudent($this->makeClass('XI TKJ 1'), 'Budi', '24002');

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Denah Ruang Ujian',
            'content' => 'Lihat gambar.',
            'visibility' => Announcement::VISIBILITY_CLASS,
            'class_id' => $class->id,
            'is_published' => '1',
            'image' => UploadedFile::fake()->image('denah.jpg', 800, 600),
        ])->assertSessionHasNoErrors();

        $announcement = Announcement::where('title', 'Denah Ruang Ujian')->firstOrFail();
        Storage::disk('local')->assertExists($announcement->image_path);
        $this->assertStringStartsWith(Announcement::IMAGE_DIRECTORY.'/', $announcement->image_path);

        $this->actingAs($student)->get(route('student.announcements.show', $announcement))
            ->assertOk()
            ->assertSee(route('student.announcements.image', $announcement), false)
            ->assertSee('Ketuk untuk memperbesar');
        $this->actingAs($student)->get(route('student.announcements.image', $announcement))->assertOk();
        $this->actingAs($student)->get(route('student.announcements.index'))->assertSee('Ada gambar');

        $this->actingAs($outsider)->get(route('student.announcements.image', $announcement))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.announcements.image', $announcement))->assertOk();
    }

    public function test_image_can_be_replaced_or_removed_and_old_files_are_cleaned_up(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $announcement = $this->makeAnnouncement(['title' => 'Info', 'image_path' => UploadedFile::fake()->image('a.jpg')->store(Announcement::IMAGE_DIRECTORY, 'local')]);
        $firstImage = $announcement->image_path;
        $payload = ['title' => 'Info', 'content' => 'Isi.', 'visibility' => Announcement::VISIBILITY_ALL, 'is_published' => '1'];

        $this->actingAs($admin)->put(route('admin.announcements.update', $announcement), $payload)->assertSessionHasNoErrors();
        $this->assertSame($firstImage, $announcement->fresh()->image_path);

        $this->actingAs($admin)->put(route('admin.announcements.update', $announcement), $payload + ['image' => UploadedFile::fake()->image('b.png')]);
        $secondImage = $announcement->fresh()->image_path;
        $this->assertNotSame($firstImage, $secondImage);
        Storage::disk('local')->assertMissing($firstImage);
        Storage::disk('local')->assertExists($secondImage);

        $this->actingAs($admin)->put(route('admin.announcements.update', $announcement), $payload + ['remove_image' => '1']);
        $this->assertNull($announcement->fresh()->image_path);
        Storage::disk('local')->assertMissing($secondImage);

        $third = UploadedFile::fake()->image('c.jpg')->store(Announcement::IMAGE_DIRECTORY, 'local');
        $announcement->update(['image_path' => $third]);
        $this->actingAs($admin)->delete(route('admin.announcements.destroy', $announcement));
        Storage::disk('local')->assertMissing($third);
    }

    public function test_announcements_are_deleted_six_months_after_they_end(): void
    {
        Storage::fake('local');
        $oldImage = UploadedFile::fake()->image('lama.jpg')->store(Announcement::IMAGE_DIRECTORY, 'local');
        $endedLongAgo = $this->makeAnnouncement(['title' => 'Lama', 'expires_at' => now()->subMonths(6)->subDay(), 'image_path' => $oldImage]);
        $endedRecently = $this->makeAnnouncement(['title' => 'Baru Berakhir', 'expires_at' => now()->subMonths(5)]);
        $stillRunning = $this->makeAnnouncement(['title' => 'Masih Tampil', 'expires_at' => now()->addWeek()]);
        $noEndDate = $this->makeAnnouncement(['title' => 'Tanpa Batas', 'created_at' => now()->subYears(2)]);

        Artisan::call('model:prune', ['--model' => [Announcement::class]]);

        $this->assertModelMissing($endedLongAgo);
        Storage::disk('local')->assertMissing($oldImage);
        $this->assertModelExists($endedRecently);
        $this->assertModelExists($stillRunning);
        $this->assertModelExists($noEndDate);
    }

    public function test_daily_schedule_includes_announcement_cleanup(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('model:prune')
            ->assertSuccessful();

        $prune = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'model:prune'));

        $this->assertNotNull($prune);
        $this->assertStringContainsString('Announcement', $prune->command);
    }

    public function test_sidebar_shows_a_red_badge_for_new_announcements_until_the_list_is_opened(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = $this->makeStudent($this->makeClass('X TKJ 1'));
        $this->makeAnnouncement(['title' => 'Libur', 'created_by' => $admin->id]);
        $this->makeAnnouncement(['title' => 'Ujian', 'created_by' => $admin->id]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertSee('aria-label="2 pengumuman baru"', false)
            ->assertDontSee('bi-bell', false)
            ->assertDontSee('bi-megaphone me-2', false);

        $this->actingAs($student)->get(route('student.announcements.index'))->assertDontSee('pengumuman baru');
        $this->actingAs($student->fresh())->get(route('student.dashboard'))->assertDontSee('pengumuman baru');

        $this->travel(1)->minutes();
        $this->makeAnnouncement(['title' => 'Rapat', 'created_by' => $admin->id, 'published_at' => now()]);

        $this->actingAs($student->fresh())->get(route('student.dashboard'))->assertSee('aria-label="1 pengumuman baru"', false);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertDontSee('pengumuman baru');
    }

    public function test_saving_publishes_now_or_at_the_scheduled_time_without_a_checkbox(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = $this->makeStudent($this->makeClass('X TKJ 1'));
        $payload = ['content' => 'Isi.', 'visibility' => Announcement::VISIBILITY_ALL];

        $this->actingAs($admin)->get(route('admin.announcements.create'))->assertDontSee('name="is_published"', false);

        $this->actingAs($admin)->post(route('admin.announcements.store'), $payload + ['title' => 'Langsung Tayang'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.announcements.store'), $payload + [
            'title' => 'Nanti Siang',
            'published_at' => now()->addHours(3)->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Announcement::where('title', 'Langsung Tayang')->value('is_published'));
        $this->actingAs($student)->get(route('student.announcements.index'))
            ->assertSee('Langsung Tayang')
            ->assertDontSee('Nanti Siang');
        $this->actingAs($admin)->get(route('admin.announcements.index'))->assertSee('Terjadwal');

        $this->travel(4)->hours();

        $this->actingAs($student)->get(route('student.announcements.index'))->assertSee('Nanti Siang');
    }

    public function test_image_must_be_a_small_picture(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $payload = ['title' => 'Info', 'content' => 'Isi.', 'visibility' => Announcement::VISIBILITY_ALL, 'is_published' => '1'];

        $this->actingAs($admin)->post(route('admin.announcements.store'), $payload + ['image' => UploadedFile::fake()->create('besar.jpg', 3000, 'image/jpeg')])
            ->assertSessionHasErrors(['image' => 'Ukuran gambar maksimal 2 MB.']);

        $this->actingAs($admin)->post(route('admin.announcements.store'), $payload + ['image' => UploadedFile::fake()->create('virus.exe', 10)])
            ->assertSessionHasErrors(['image' => 'Gambar harus berformat PNG, JPG, atau WEBP.']);

        $this->assertDatabaseCount('announcements', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
