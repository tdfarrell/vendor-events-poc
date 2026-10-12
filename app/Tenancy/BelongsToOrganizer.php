<?php

namespace App\Tenancy;

use App\Models\Organizer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * For models owned by one organizer. Adds the organizer filter to every query
 * and stamps organizer_id on create.
 */
trait BelongsToOrganizer
{
    public static function bootBelongsToOrganizer(): void
    {
        static::addGlobalScope(new OrganizerScope);

        static::creating(function ($model) {
            $current = Tenancy::id();
            if ($model->organizer_id === null) {
                if ($current === null) {
                    throw new LogicException('Cannot create '.static::class.' without a current organizer.');
                }
                $model->organizer_id = $current;
            } elseif ($current !== null && (int) $model->organizer_id !== $current) {
                throw new LogicException('Refusing to create '.static::class.' for another organizer.');
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('organizer_id')) {
                throw new LogicException('organizer_id cannot be changed.');
            }
        });
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }
}
