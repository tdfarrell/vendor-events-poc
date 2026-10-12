<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketType extends Model
{
    use BelongsToOrganizer;

    protected $guarded = ['id', 'organizer_id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
