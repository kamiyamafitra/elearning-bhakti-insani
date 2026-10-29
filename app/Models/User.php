<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (blank($user->username)) {
                $user->username = static::generateUsername($user->email);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Normalize usernames so they can be matched case-insensitively.
     */
    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : Str::lower(trim($value)),
        );
    }

    /**
     * Build avatar initials from the user's name.
     */
    protected function initials(): Attribute
    {
        return Attribute::get(function (): string {
            $initials = Str::of((string) $this->name)
                ->squish()
                ->explode(' ')
                ->filter()
                ->take(2)
                ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
                ->implode('');

            return $initials !== '' ? $initials : '?';
        });
    }

    /**
     * Build a unique username from an email address.
     */
    public static function generateUsername(string $email): string
    {
        $base = Str::of($email)
            ->before('@')
            ->lower()
            ->replaceMatches('/[^a-z0-9._-]/', '')
            ->value();

        $base = $base !== '' ? $base : 'user';
        $username = $base;
        $suffix = 1;

        while (static::query()->where('username', $username)->exists()) {
            $username = $base.$suffix;
            $suffix++;
        }

        return $username;
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class, 'teacher_id');
    }

    public function classMemberships(): HasMany
    {
        return $this->hasMany(ClassMembership::class, 'student_id');
    }

    public function learningMaterials(): HasMany
    {
        return $this->hasMany(LearningMaterial::class, 'teacher_id');
    }

    public function createdAssignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'teacher_id');
    }

    public function assignmentSubmissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class, 'student_id');
    }

    public function gradedSubmissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class, 'graded_by');
    }

    /**
     * The class id linked to the student profile, when available.
     */
    public function studentClassId(): ?int
    {
        $classId = $this->studentProfile?->class_id;

        return $classId === null ? null : (int) $classId;
    }

    /**
     * The classes this teacher is assigned to.
     *
     * @return Collection<int, int>
     */
    public function taughtClassIds(): Collection
    {
        return $this->teachingAssignments()
            ->pluck('class_id')
            ->unique()
            ->values();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }
}
