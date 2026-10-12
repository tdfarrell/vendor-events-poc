<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableType extends Model
{
    use BelongsToOrganizer;

    protected $guarded = ['id', 'organizer_id'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
