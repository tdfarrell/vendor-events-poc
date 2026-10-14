<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueRoom extends Model
{
    protected $guarded = ['id', 'venue_id'];

    protected function casts(): array
    {
        return ['amenities' => 'array', 'seated' => 'integer', 'standing' => 'integer', 'price_from' => 'integer', 'price_to' => 'integer'];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function capacityText(): string
    {
        $parts = [];
        if ($this->seated) {
            $parts[] = number_format($this->seated).' seated';
        }
        if ($this->standing) {
            $parts[] = number_format($this->standing).' standing';
        }

        return implode(' · ', $parts);
    }

    public function priceText(): string
    {
        if (! $this->price_from) {
            return 'Ask for pricing';
        }
        $unit = config('venues.price_units.'.$this->price_unit, 'per event');
        $range = '$'.number_format($this->price_from).($this->price_to && $this->price_to > $this->price_from ? '–$'.number_format($this->price_to) : '');

        return $range.' '.$unit;
    }
}
