<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organizer extends Model
{
    protected $guarded = ['id'];

    /** Absolute URL on this organizer's subdomain. */
    public function url(string $path = '/'): string
    {
        return self::hostUrl($this->slug.'.'.config('platform.domain'), $path);
    }

    /** Absolute URL on the directory (root) domain. */
    public static function directoryUrl(string $path = '/'): string
    {
        return self::hostUrl(config('platform.domain'), $path);
    }

    private static function hostUrl(string $host, string $path): string
    {
        $port = (string) config('platform.port');
        $scheme = config('platform.scheme');
        $default = ($scheme === 'https' && $port === '443') || ($scheme === 'http' && $port === '80') || $port === '';

        return $scheme.'://'.$host.($default ? '' : ':'.$port).'/'.ltrim($path, '/');
    }

    public function fromName(): string
    {
        return $this->email_from_name ?: $this->name;
    }

    /** Text color that reads on the brand color. */
    public function brandInk(): string
    {
        $hex = ltrim($this->brand_color ?: '#2f5d50', '#');
        [$r, $g, $b] = array_map('hexdec', str_split(str_pad($hex, 6, '0'), 2));
        $lum = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;

        return $lum > 0.6 ? '#111111' : '#ffffff';
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
