<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketOrder extends Model
{
    use BelongsToOrganizer;

    protected $guarded = ['id', 'organizer_id'];

    protected function casts(): array
    {
        return ['hold_until' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'order_token';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TicketOrderItem::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function ticketCount(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
