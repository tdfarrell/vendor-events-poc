<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A rental request sent from a venue's page. Visible only to the venue's organizer. */
class VenueInquiry extends Model
{
    protected $guarded = ['id', 'venue_id', 'status', 'replied_at'];

    protected function casts(): array
    {
        return ['event_date' => 'date', 'date_flexible' => 'boolean', 'guests' => 'integer', 'replied_at' => 'datetime'];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(VenueRoom::class, 'venue_room_id');
    }

    public function kindLabel(): string
    {
        return config('venues.event_kinds.'.$this->event_kind, 'Event');
    }

    /** @return array{0:string,1:string} [badge class, label] */
    public function statusBadge(): array
    {
        [$label, $cls] = config('venues.inquiry_statuses.'.$this->status, ['New', 'pending']);

        return [$cls, $label];
    }

    public function whenText(): string
    {
        $date = $this->event_date?->format('D, M j, Y') ?? 'Date not set';

        return $date.($this->date_flexible ? ' (flexible)' : '').($this->time_range ? ' · '.$this->time_range : '');
    }
}
