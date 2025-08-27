<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperFavouriteStreamer
 */
class FavouriteStreamer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'streamer_id',
        'streamer_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
