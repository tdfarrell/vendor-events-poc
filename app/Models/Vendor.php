<?php

namespace App\Models;

use App\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One account per vendor across the platform. Not organizer-scoped: a vendor
 * books with many organizers. Organizers see a vendor's details only through
 * applications to their own events.
 */
class Vendor extends Model
{
    protected $guarded = ['id', 'email', 'slug'];

    protected function casts(): array
    {
        return ['listed' => 'boolean', 'last_login_at' => 'datetime'];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(VendorApplication::class);
    }

    public static function findOrCreateByEmail(string $email): self
    {
        $email = Str::lower(trim($email));
        $vendor = static::where('email', $email)->first();
        if ($vendor) {
            return $vendor;
        }
        $vendor = new static;
        $vendor->email = $email;
        $vendor->save();

        return $vendor;
    }

    public function assignSlug(): void
    {
        if ($this->slug || ! $this->business_name) {
            return;
        }
        $base = Str::slug($this->business_name) ?: 'vendor';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->where('id', '<>', $this->id)->exists()) {
            $slug = $base.'-'.$i++;
        }
        $this->slug = $slug;
        $this->save();
    }

    public function isPublic(): bool
    {
        return $this->listed && $this->slug && $this->business_name;
    }

    public function profileUrl(): string
    {
        return Organizer::directoryUrl('/vendors/'.$this->slug);
    }

    public function categoryList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->categories))));
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->business_name ?: $this->email)) ?: ['?'];

        return Str::upper(Str::substr($words[0], 0, 1).Str::substr($words[1] ?? '', 0, 1));
    }

    /** Links to show on the profile, as [label => url]. */
    public function links(): array
    {
        $out = [];
        if ($this->instagram) {
            $handle = ltrim(preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $this->instagram), '@/');
            $out['Instagram'] = 'https://instagram.com/'.trim($handle, '/');
        }
        if ($this->facebook) {
            $out['Facebook'] = Str::startsWith($this->facebook, 'http') ? $this->facebook : 'https://facebook.com/'.ltrim($this->facebook, '/');
        }
        if ($this->website) {
            $out['Website'] = Str::startsWith($this->website, 'http') ? $this->website : 'https://'.$this->website;
        }

        return $out;
    }

    /**
     * Confirmed bookings at upcoming published events, across every organizer:
     * "where I'll be next".
     */
    public function upcomingAppearances(): Collection
    {
        return Tenancy::acrossOrganizers(fn () => VendorApplication::query()
            ->with(['event.organizer', 'tableType'])
            ->where('vendor_id', $this->id)
            ->where('status', 'paid')
            ->whereHas('event', fn ($q) => $q->published()->where('notice', '<>', 'cancelled')
                ->where(fn ($q) => $q->whereNull('starts_on')->orWhere('starts_on', '>=', today()->toDateString())))
            ->get()
            ->sortBy(fn ($a) => $a->event->starts_on?->timestamp ?? PHP_INT_MAX)
            ->values());
    }
}
