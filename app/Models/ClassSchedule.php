<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassSchedule extends Model
{
    /**
     * Weekday numbers follow ISO-8601: 1 = Monday ... 7 = Sunday.
     *
     * @var array<int, string>
     */
    public const DAYS = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    protected $fillable = [
        'subject_id',
        'class_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    protected function dayLabel(): Attribute
    {
        return Attribute::get(fn (): string => self::DAYS[$this->day_of_week] ?? '-');
    }

    protected function timeRange(): Attribute
    {
        return Attribute::get(fn (): string => sprintf(
            '%s–%s',
            self::formatTime($this->start_time),
            self::formatTime($this->end_time),
        ));
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('day_of_week')->orderBy('start_time');
    }

    public static function formatTime(?string $time): string
    {
        return $time ? substr($time, 0, 5) : '-';
    }
}
