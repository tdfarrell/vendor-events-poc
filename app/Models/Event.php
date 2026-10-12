<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
