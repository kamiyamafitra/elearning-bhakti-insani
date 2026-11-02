<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherProfile extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id',
        'nip',
        'phone',
        'address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activityLabel(): string
    {
        return 'data guru';
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
