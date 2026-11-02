<?php

namespace App\Models;

use Closure;
use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory, MassPrunable;

    public const UPDATED_AT = null;

    public const RETENTION_DAYS = 180;

    public const ACTION_LOGIN = 'login';

    public const ACTION_LOGIN_FAILED = 'login_failed';

    public const ACTION_LOGOUT = 'logout';

    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_DELETED = 'deleted';

    public const ACTION_BULK = 'bulk';

    /**
     * Indonesian labels for each action, used by the admin filter and badges.
     *
     * @var array<string, string>
     */
    public const ACTION_LABELS = [
        self::ACTION_LOGIN => 'Masuk',
        self::ACTION_LOGIN_FAILED => 'Gagal masuk',
        self::ACTION_LOGOUT => 'Keluar',
        self::ACTION_CREATED => 'Tambah',
        self::ACTION_UPDATED => 'Ubah',
        self::ACTION_DELETED => 'Hapus',
        self::ACTION_BULK => 'Proses massal',
    ];

    private static bool $isPaused = false;

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Write a log entry for the signed-in user (or the given user) along with the request's IP and browser.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $action, string $description, ?Model $subject = null, array $properties = [], ?User $user = null): ?self
    {
        if (self::$isPaused) {
            return null;
        }

        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return self::create([
            'user_id' => ($user ?? auth()->user())?->getKey(),
            'action' => $action,
            'description' => Str::limit($description, 497),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 252) : null,
        ]);
    }

    /**
     * Run a bulk operation without logging every model change, so it can be recorded as one summary entry.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutLogging(Closure $callback): mixed
    {
        $wasPaused = self::$isPaused;
        self::$isPaused = true;

        try {
            return $callback();
        } finally {
            self::$isPaused = $wasPaused;
        }
    }

    public static function isPaused(): bool
    {
        return self::$isPaused;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }

    /**
     * Delete entries older than the retention period when `model:prune` runs.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
