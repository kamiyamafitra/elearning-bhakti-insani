<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class ClassDeleteTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    public function test_class_left_with_only_history_can_be_deleted(): void
    {
        $nextYear = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-13', 'end_date' => '2027-06-30', 'is_active' => false]);
        $currentClass = $this->makeClass('X TKJ 1');
        $emptiedClass = $this->makeClass('XI TKJ 1', $nextYear);
        $student = $this->makeStudent($emptiedClass, 'Andi', '24001');
        $student->studentProfile->update(['class_id' => $currentClass->id]);

        $this->assertDatabaseHas('class_memberships', ['class_id' => $emptiedClass->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.classes.destroy', $emptiedClass))
            ->assertRedirect(route('admin.classes.index'))
            ->assertSessionHas('success', 'Kelas berhasil dihapus.');

        $this->assertModelMissing($emptiedClass);
        $this->assertDatabaseMissing('class_memberships', ['class_id' => $emptiedClass->id]);
        $this->assertDatabaseHas('class_memberships', ['class_id' => $currentClass->id, 'student_id' => $student->id]);
    }

    public function test_error_names_what_still_blocks_the_delete(): void
    {
        $class = $this->makeClass('X TKJ 1');
        $subject = $this->makeSubject('Jaringan', 'JRG');
        $teacher = $this->makeTeacher();
        $this->assignTeacher($teacher, $class, $subject);
        $this->makeStudent($class, 'Andi', '24001');
        $this->makeAssignment($subject, $class, $teacher);

        $this->actingAs($this->admin())
            ->delete(route('admin.classes.destroy', $class))
            ->assertSessionHas('error', 'Kelas tidak dapat dihapus karena masih memiliki siswa, guru pengajar, tugas.');

        $this->assertModelExists($class);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }
}
