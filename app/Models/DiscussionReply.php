<?php

namespace App\Models;

use App\Models\Concerns\HasPrivateImage;
use Database\Factories\DiscussionReplyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscussionReply extends Model
{
    /** @use HasFactory<DiscussionReplyFactory> */
    use HasFactory, HasPrivateImage;

    /**
     * A new reply moves its discussion back to the top of the list.
     *
     * @var list<string>
     */
    protected $touches = ['discussion'];

    protected $fillable = [
        'discussion_id',
        'user_id',
        'parent_id',
        'body',
        'image_path',
        'is_best',
    ];

    protected function casts(): array
    {
        return [
            'is_best' => 'boolean',
        ];
    }

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
