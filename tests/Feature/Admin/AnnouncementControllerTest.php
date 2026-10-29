<?php

namespace Tests\Feature\Admin;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
