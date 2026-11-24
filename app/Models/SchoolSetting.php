<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\SchoolSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    /** @use HasFactory<SchoolSettingFactory> */
    use HasFactory, LogsActivity;

    public const SEMESTER_ODD = 'ganjil';

    public const SEMESTER_EVEN = 'genap';

    /**
     * @var array<string, string>
     */
    public const SEMESTER_LABELS = [
        self::SEMESTER_ODD => 'Ganjil',
        self::SEMESTER_EVEN => 'Genap',
    ];

    protected $fillable = [
        'name',
        'city',
        'address',
        'phone',
        'email',
        'logo_path',
        'semester',
    ];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'name' => 'SMK Bhakti Insani',
        'city' => 'Bogor',
        'semester' => self::SEMESTER_ODD,
    ];

    /**
     * The school's single settings row, or the defaults until an admin has saved them.
     */
    public static function current(): self
    {
        return once(fn () => static::query()->first() ?? new static);
    }

    public function fullName(): string
    {
        return trim("{$this->name} {$this->city}");
    }

    public function semesterLabel(): string
    {
        return self::SEMESTER_LABELS[$this->semester] ?? $this->semester;
    }

    /**
     * The school's own logo, shipped in public/images; a logo uploaded in Pengaturan Sekolah replaces it.
     */
    public const DEFAULT_LOGO = 'images/logo-bhakti-insani.png';

    public function logoUrl(): string
    {
        return $this->logo_path
            ? route('school-logo', ['v' => $this->updated_at?->timestamp])
            : asset(self::DEFAULT_LOGO);
    }

    public function hasUploadedLogo(): bool
    {
        return filled($this->logo_path);
    }

    public function activityLabel(): string
    {
        return 'pengaturan sekolah';
    }
}
