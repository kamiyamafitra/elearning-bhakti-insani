<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id',
        'nisn',
        'nis',
        'class_id',
        'gender',
        'birth_date',
        'phone',
        'address',
        'parent_name',
        'parent_phone',
    ];

    protected static function booted(): void
    {
        static::saved(fn (StudentProfile $student) => $student->syncClassMemberships());
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    /**
     * Keep the class history in step with class_id: a move within the same academic year
     * replaces the old membership, while a move to another year keeps it as history.
     */
    private function syncClassMemberships(): void
    {
        $previousClassId = $this->getRawOriginal('class_id');

        if ((int) $previousClassId === (int) $this->class_id) {
            return;
        }

        $previousClass = $previousClassId ? Classes::find($previousClassId) : null;
        $currentClass = $this->class_id ? Classes::find($this->class_id) : null;

        if ($previousClass !== null) {
            $membership = [
                'academic_year_id' => $previousClass->academic_year_id,
                'class_id' => $previousClass->id,
                'student_id' => $this->user_id,
            ];

            if ((int) $previousClass->academic_year_id === (int) $currentClass?->academic_year_id) {
                ClassMembership::where($membership)->delete();
            } else {
                ClassMembership::firstOrCreate($membership);
            }
        }

        if ($currentClass !== null) {
            ClassMembership::firstOrCreate([
                'academic_year_id' => $currentClass->academic_year_id,
                'class_id' => $currentClass->id,
                'student_id' => $this->user_id,
            ]);
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function activityLabel(): string
    {
        return 'data siswa';
    }

    public function activityName(): ?string
    {
        return $this->user?->name;
    }

    /**
     * Creation and deletion are logged on the user account instead.
     *
     * @return list<string>
     */
    protected static function loggedActivityEvents(): array
    {
        return ['updated'];
    }
}
