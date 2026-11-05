<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Classes extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'classes';

    protected $fillable = [
        'academic_year_id',
        'name',
        'code',
        'level',
    ];

    /**
     * Build a code such as "X-TKJ-1-2627" for the given year, staying within 20 characters and unique.
     */
    public static function uniqueCodeFor(string $sourceCode, AcademicYear $academicYear): string
    {
        $baseCode = preg_replace('/-\d{4}$/', '', $sourceCode);
        [$firstYear, $secondYear] = explode('/', $academicYear->name) + [1 => ''];
        $suffix = '-'.substr($firstYear, -2).substr($secondYear, -2);

        $code = Str::limit($baseCode, 20 - strlen($suffix), '').$suffix;
        $counter = 2;

        while (self::where('code', $code)->exists()) {
            $counterSuffix = $suffix.'-'.$counter++;
            $code = Str::limit($baseCode, 20 - strlen($counterSuffix), '').$counterSuffix;
        }

        return $code;
    }

    public function scopeInActiveYear(Builder $query): Builder
    {
        return $query->whereIn($query->qualifyColumn('academic_year_id'), AcademicYear::where('is_active', true)->select('id'));
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(StudentProfile::class, 'class_id');
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class, 'class_id');
    }

    public function classMemberships(): HasMany
    {
        return $this->hasMany(ClassMembership::class, 'class_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class, 'class_id');
    }

    public function learningMaterials(): HasMany
    {
        return $this->hasMany(LearningMaterial::class, 'class_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'class_id');
    }

    public function activityLabel(): string
    {
        return 'kelas';
    }
}
