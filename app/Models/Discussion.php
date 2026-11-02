<?php

namespace App\Models;

use App\Models\Concerns\HasPrivateImage;
use Database\Factories\DiscussionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Discussion extends Model
{
    /** @use HasFactory<DiscussionFactory> */
    use HasFactory, HasPrivateImage;

    public const IMAGE_DIRECTORY = 'discussion-images';

    protected $fillable = [
        'class_id',
        'subject_id',
        'user_id',
        'title',
        'body',
        'image_path',
        'is_pinned',
        'is_closed',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_closed' => 'boolean',
        ];
    }

    /**
     * Replies are removed one by one so their images are deleted too.
     */
    protected static function booted(): void
    {
        static::deleting(function (Discussion $discussion): void {
            $discussion->replies()->each(fn (DiscussionReply $reply) => $reply->delete());
        });
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(DiscussionReply::class);
    }

    /**
     * @param  Collection<int, object{class_id: int, subject_id: int}>  $rooms
     */
    public function scopeInRooms(Builder $query, Collection $rooms): Builder
    {
        if ($rooms->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($rooms): void {
            foreach ($rooms as $room) {
                $query->orWhere(fn (Builder $query) => $query
                    ->where('class_id', $room->class_id)
                    ->where('subject_id', $room->subject_id));
            }
        });
    }
}
