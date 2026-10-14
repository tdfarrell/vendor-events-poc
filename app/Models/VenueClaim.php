<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueClaim extends Model
{
    protected $guarded = ['id', 'venue_id', 'status'];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
