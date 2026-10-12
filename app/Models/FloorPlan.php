<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FloorPlan extends Model
{
    use BelongsToOrganizer;

    protected $guarded = ['id', 'organizer_id'];

    protected function casts(): array
    {
        return ['elements' => 'array', 'is_public' => 'boolean', 'width_ft' => 'float', 'depth_ft' => 'float', 'aisle_ft' => 'float'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function hasTables(): bool
    {
        foreach ($this->elements ?? [] as $el) {
            if (\App\Services\FloorPlans::sellable($el)) {
                return true;
            }
        }

        return false;
    }
}
