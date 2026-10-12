<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorApplication extends Model
{
    use BelongsToOrganizer;

    public const STATUS_LABELS = [
        'checkout' => 'In checkout',
        'incomplete' => "Didn't finish payment",
        'pending' => 'Pending review',
        'approved' => 'Approved, awaiting payment',
        'paid' => 'Confirmed',
        'waitlisted' => 'Waitlisted',
        'declined' => 'Declined',
        'cancelled' => 'Cancelled',
    ];

    protected $guarded = ['id', 'organizer_id', 'pay_token'];

    protected function casts(): array
    {
        return [
            'needs_power' => 'boolean',
            'approved_at' => 'datetime',
            'pay_by' => 'datetime',
            'hold_until' => 'datetime',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'checked_in_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'pay_token';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tableType(): BelongsTo
    {
        return $this->belongsTo(TableType::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function paidAwaitingReview(): bool
    {
        return $this->status === 'pending' && $this->paid_at !== null;
    }

    public function holdExpired(): bool
    {
        return $this->status === 'approved' && $this->pay_by !== null && $this->pay_by->isPast();
    }

    /** [css class, label] for lists, accounting for stale holds and refunds. */
    public function statusBadge(): array
    {
        if ($this->paidAwaitingReview()) {
            return ['review', 'Paid, awaiting review'];
        }
        if ($this->holdExpired()) {
            return ['overdue', 'Payment overdue'];
        }
        if ($this->status === 'checkout' && $this->hold_until?->isPast()) {
            return ['cancelled', "Didn't finish payment"];
        }
        if (in_array($this->status, ['cancelled', 'declined'], true) && $this->refunded_at) {
            return [$this->status, self::STATUS_LABELS[$this->status].' & refunded'];
        }

        return [$this->status, self::STATUS_LABELS[$this->status] ?? $this->status];
    }

    public function tableSummary(): string
    {
        return $this->quantity.' × '.($this->tableType?->name ?? 'space');
    }

    public function statusUrl(): string
    {
        return $this->event->organizer->url('/booking/'.$this->pay_token);
    }
}
