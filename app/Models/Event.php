<?php

namespace App\Models;

use App\Support\Uploads;
use App\Tenancy\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Event extends Model
{
    use BelongsToOrganizer;

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];

    public const PAYMENT_MODES = [
        'review' => 'Vendors pay when applying, then you review (declines are refunded)',
        'instant' => 'Vendors pay and are confirmed instantly (first come, first served)',
        'approve_first' => 'You approve first, then the vendor pays from an emailed link',
    ];

    protected $guarded = ['id', 'organizer_id', 'checkin_token'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'applications_open' => 'boolean',
            'tickets_on_sale' => 'boolean',
            'amenities' => 'array',
            'faq' => 'array',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function tableTypes(): HasMany
    {
        return $this->hasMany(TableType::class)->orderBy('sort_order')->orderBy('id');
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class)->orderBy('sort_order')->orderBy('id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(VendorApplication::class);
    }

    public function ticketOrders(): HasMany
    {
        return $this->hasMany(TicketOrder::class);
    }

    public function doorSales(): HasMany
    {
        return $this->hasMany(DoorSale::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(EventPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function floorPlan(): HasOne
    {
        return $this->hasOne(FloorPlan::class);
    }

    public function coverUrl(): string
    {
        return Uploads::url($this->cover_path);
    }

    /** PNG for Facebook and other link previews: the cover photo, or a generated poster. */
    public function shareImageUrl(): string
    {
        return $this->cover_path ? $this->coverUrl() : $this->organizer->url('/events/'.$this->slug.'/share.png');
    }

    public function hasLocation(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    /** The listed venue this event is held at, if any (venues aren't organizer-scoped). */
    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function fullAddress(): string
    {
        return collect([$this->venue_address, $this->town, $this->town ? config('platform.state') : null])->filter()->join(', ');
    }

    public function directionsUrl(): string
    {
        $q = $this->hasLocation() ? $this->lat.','.$this->lng : trim($this->venue_name.' '.$this->fullAddress());

        return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($q);
    }

    public function googleCalendarUrl(): string
    {
        $day = $this->starts_on?->format('Ymd');
        $next = $this->starts_on?->copy()->addDay()->format('Ymd');

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => $this->title,
            'dates' => $day ? $day.'/'.$next : '',
            'details' => trim(($this->hours ? $this->hours."\n" : '').$this->publicUrl()),
            'location' => trim($this->venue_name.', '.$this->fullAddress(), ', '),
        ]);
    }

    /** [emoji, label] for each amenity the organizer ticked. */
    public function amenityList(): array
    {
        $all = config('platform.amenities');

        return array_values(array_filter(array_map(fn ($k) => $all[$k] ?? null, $this->amenities ?? [])));
    }

    public function faqList(): array
    {
        return array_values(array_filter($this->faq ?? [], fn ($f) => trim($f['q'] ?? '') !== '' && trim($f['a'] ?? '') !== ''));
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->where(fn ($q) => $q->whereNull('starts_on')->orWhere('starts_on', '>=', today()->toDateString()))
            ->orderByRaw('starts_on is null')->orderBy('starts_on');
    }

    public function typeConfig(): array
    {
        return config('event_types.'.$this->type) ?? config('event_types.card_show');
    }

    public function typeLabel(): string
    {
        return $this->typeConfig()['label'];
    }

    /** "table" / "booth" / "stall" / "spot". */
    public function space(bool $plural = false): string
    {
        return $this->typeConfig()[$plural ? 'spaces' : 'space'];
    }

    public function categoryList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->categories))));
    }

    public function isPublic(): bool
    {
        return $this->status === 'published';
    }

    public function publicUrl(string $suffix = ''): string
    {
        return $this->organizer->url('/events/'.$this->slug.$suffix);
    }

    public function whenWhere(): string
    {
        $parts = [$this->starts_on?->format('l, F j, Y') ?? 'Date to be announced'];
        if ($this->hours) {
            $parts[] = $this->hours;
        }
        if ($this->venue_name) {
            $parts[] = $this->venue_name.($this->town ? ', '.$this->town : '');
        }

        return implode(' · ', $parts);
    }

    public function ensureCheckinToken(): string
    {
        if (! $this->checkin_token) {
            $this->forceFill(['checkin_token' => Str::lower(Str::random(32))])->save();
        }

        return $this->checkin_token;
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '<>', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
