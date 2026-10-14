<?php

namespace App\Models;

use App\Support\Uploads;
use App\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A hall, post, church, school or club with rooms to rent. With no organizer
 * it's a directory listing the platform seeded, waiting to be claimed; with
 * one, only that organizer can edit it or read its inquiries.
 *
 * Not organizer-scoped like events, because unclaimed venues belong to no one.
 * Admin code must go through ownedByCurrent(), which fails closed.
 */
class Venue extends Model
{
    protected $guarded = ['id', 'organizer_id'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float'];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(VenueRoom::class)->orderBy('sort_order')->orderBy('id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(VenueInquiry::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(VenueClaim::class);
    }

    /** Venues anyone can see in the directory. */
    public function scopePublic(Builder $q): Builder
    {
        return $q->whereIn('status', ['listed', 'active'])
            ->where(fn ($q) => $q->whereNull('organizer_id')
                ->orWhereHas('organizer', fn ($o) => $o->where('status', 'active')));
    }

    /** The current organizer's venues; nothing when no organizer is set. */
    public function scopeOwnedByCurrent(Builder $q): Builder
    {
        $id = Tenancy::id();

        return $id === null ? $q->whereRaw('1 = 0') : $q->where('organizer_id', $id);
    }

    public function isClaimed(): bool
    {
        return $this->organizer_id !== null;
    }

    public function kindConfig(): array
    {
        return config('venues.kinds.'.$this->kind) ?? config('venues.kinds.event_hall');
    }

    public function kindLabel(): string
    {
        return $this->kindConfig()['label'];
    }

    public function url(string $suffix = ''): string
    {
        return Organizer::directoryUrl('venues/'.$this->slug.$suffix);
    }

    public function coverUrl(): string
    {
        return Uploads::url($this->cover_path);
    }

    public function fullAddress(): string
    {
        return collect([$this->address, $this->town, $this->town ? config('platform.state') : null])->filter()->join(', ');
    }

    public function directionsUrl(): string
    {
        return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($this->lat !== null
            ? $this->lat.','.$this->lng : $this->name.' '.$this->fullAddress());
    }

    /** Where inquiries are emailed. */
    public function inquiryEmail(): string
    {
        return $this->contact_email ?: (string) $this->organizer?->contact_email;
    }

    /** Largest room, seated or standing. */
    public function maxGuests(): ?int
    {
        $rooms = $this->relationLoaded('rooms') ? $this->rooms : $this->rooms()->get();
        $n = $rooms->map(fn ($r) => max((int) $r->seated, (int) $r->standing))->max();

        return $n ?: null;
    }

    public function priceFrom(): ?int
    {
        $rooms = $this->relationLoaded('rooms') ? $this->rooms : $this->rooms()->get();

        return $rooms->pluck('price_from')->filter()->min();
    }

    /** @return string[] amenity keys across all rooms */
    public function amenityKeys(): array
    {
        $rooms = $this->relationLoaded('rooms') ? $this->rooms : $this->rooms()->get();

        return $rooms->flatMap(fn ($r) => $r->amenities ?? [])->unique()->values()->all();
    }

    public static function uniqueSlug(string $name, string $town = ''): string
    {
        $base = Str::slug(trim($name.' '.$town)) ?: 'venue';
        $base = Str::limit($base, 100, '');
        $slug = $base;
        for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
