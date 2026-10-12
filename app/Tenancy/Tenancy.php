<?php

namespace App\Tenancy;

use App\Models\Organizer;
use Closure;

/**
 * Holds the organizer the current request (or job, or seeder) is acting for.
 *
 * Scoped models read from here. With no organizer set, scoped queries return
 * nothing, so forgetting to set one fails closed instead of leaking data.
 */
class Tenancy
{
    private static ?Organizer $current = null;

    private static int $acrossDepth = 0;

    public static function set(?Organizer $organizer): void
    {
        self::$current = $organizer;
    }

    public static function current(): ?Organizer
    {
        return self::$current;
    }

    public static function id(): ?int
    {
        return self::$current?->id;
    }

    public static function forget(): void
    {
        self::$current = null;
        self::$acrossDepth = 0;
    }

    /** Runs $callback acting as $organizer, then restores the previous one. */
    public static function as(Organizer $organizer, Closure $callback): mixed
    {
        $previous = self::$current;
        self::$current = $organizer;
        try {
            return $callback();
        } finally {
            self::$current = $previous;
        }
    }

    /**
     * Runs $callback with the organizer filter switched off. Only the public
     * directory and the vendor's own account pages should need this, and they
     * must limit what they read themselves (published events, the vendor's
     * own bookings).
     */
    public static function acrossOrganizers(Closure $callback): mixed
    {
        self::$acrossDepth++;
        try {
            return $callback();
        } finally {
            self::$acrossDepth--;
        }
    }

    public static function isAcrossOrganizers(): bool
    {
        return self::$acrossDepth > 0;
    }
}
