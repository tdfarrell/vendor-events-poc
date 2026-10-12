<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An event run off the platform, shown in the directory until its organizer claims it. */
class Listing extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'lat' => 'float', 'lng' => 'float'];
    }

    public function claims(): HasMany
    {
        return $this->hasMany(ListingClaim::class);
    }

    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('status', 'listed')
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhere('starts_on', '>=', today()->toDateString()));
    }

    public function typeConfig(): array
    {
        return config('event_types.'.$this->type) ?? config('event_types.card_show');
    }

    public function typeLabel(): string
    {
        return $this->typeConfig()['label'];
    }
}
