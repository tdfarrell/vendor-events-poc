<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingClaim extends Model
{
    protected $guarded = ['id'];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
