<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Record create, update, and delete events of a model in the activity log.
 *
 * Models describe themselves through activityLabel() (e.g. "kelas") and activityName() (e.g. "X TKJ 1").
 */
trait LogsActivity
{
    /**
     * Attributes that never appear in the change list.
     *
     * @var list<string>
     */
    private static array $ignoredActivityAttributes = ['created_at', 'updated_at', 'remember_token', 'password', 'file_path'];

    public static function bootLogsActivity(): void
    {
        $events = static::loggedActivityEvents();

        static::created(function (Model $model) use ($events): void {
            if (! in_array('created', $events, true)) {
                return;
            }

            ActivityLog::record(ActivityLog::ACTION_CREATED, "menambahkan {$model->activityLabel()} {$model->quotedActivityName()}", $model);
        });

        static::updated(function (Model $model) use ($events): void {
            if (! in_array('updated', $events, true)) {
                return;
            }

            $changes = $model->activityChanges();

            if ($changes === [] && ! $model->wasChanged('password')) {
                return;
            }

            $properties = $changes === [] ? [] : ['changes' => $changes];

            if ($model->wasChanged('password')) {
                $properties['password_changed'] = true;
            }

            ActivityLog::record(ActivityLog::ACTION_UPDATED, "mengubah {$model->activityLabel()} {$model->quotedActivityName()}", $model, $properties);
        });

        static::deleted(function (Model $model) use ($events): void {
            if (! in_array('deleted', $events, true)) {
                return;
            }

            ActivityLog::record(ActivityLog::ACTION_DELETED, "menghapus {$model->activityLabel()} {$model->quotedActivityName()}", $model);
        });
    }

    /**
     * The changed attributes as [attribute => [old, new]].
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function activityChanges(): array
    {
        $changes = [];

        foreach ($this->getChanges() as $attribute => $newValue) {
            if (in_array($attribute, self::$ignoredActivityAttributes, true)) {
                continue;
            }

            $changes[$attribute] = [$this->getRawOriginal($attribute), $newValue];
        }

        return $changes;
    }

    public function quotedActivityName(): string
    {
        $name = trim((string) $this->activityName());

        return $name === '' ? "#{$this->getKey()}" : "\"{$name}\"";
    }

    /**
     * Which model events are written to the log.
     *
     * @return list<string>
     */
    protected static function loggedActivityEvents(): array
    {
        return ['created', 'updated', 'deleted'];
    }

    abstract public function activityLabel(): string;

    public function activityName(): ?string
    {
        return $this->getAttribute('name') ?? $this->getAttribute('title');
    }
}
