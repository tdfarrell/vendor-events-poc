<?php

namespace App\Support;

/**
 * Generated posters for events without a cover photo, in their category's
 * colors: a two-color gradient, a soft glow, and the category's emoji
 * scattered at a slant. Each event gets its own arrangement from its id, so
 * two card shows side by side don't look identical.
 */
class Poster
{
    /** Inline SVG. With $title, the event name is set on the poster too. */
    public static function svg(string $type, int $seed, ?string $title = null, ?string $dateLabel = null, string $class = ''): string
    {
        $cfg = config('event_types.'.$type) ?? config('event_types.card_show');
        [$a, $b] = $cfg['colors'];
        $emoji = $cfg['emoji'];
        $id = 'p'.$seed.substr(md5($type.$title.$class), 0, 6);
        $r = self::rand($seed);

        $angle = [-14, -8, 10, 16][$r(0, 3)];
        $glowX = $r(15, 85);
        $glowY = $r(10, 60);

        // Scattered emoji: one big, a few small, partly off the edges.
        $marks = '';
        $spots = [[$r(470, 620), $r(160, 330), 300], [$r(40, 160), $r(380, 520), 120], [$r(300, 420), $r(20, 80), 90], [$r(640, 760), $r(470, 560), 110]];
        foreach ($spots as $i => [$x, $y, $size]) {
            $rot = $angle + ($i % 2 ? 18 : -6);
            $op = $i === 0 ? '.95' : '.55';
            $marks .= '<text x="'.$x.'" y="'.$y.'" font-size="'.$size.'" text-anchor="middle" dominant-baseline="central" opacity="'.$op
                .'" transform="rotate('.$rot.' '.$x.' '.$y.')">'.$emoji.'</text>';
        }

        $words = '';
        if ($title !== null) {
            $lines = self::wrap($title, 16, 3);
            $fs = count($lines) > 2 ? 64 : 76;
            $y0 = 560 - (count($lines) - 1) * ($fs * 1.02);
            foreach ($lines as $i => $line) {
                $words .= '<text x="48" y="'.round($y0 + $i * $fs * 1.02).'" font-family="Bricolage Grotesque, system-ui, sans-serif" font-weight="800" font-size="'.$fs
                    .'" letter-spacing="-1.5" fill="#fff">'.e($line).'</text>';
            }
            if ($dateLabel) {
                $words .= '<g transform="translate(48 52)"><rect width="'.(strlen($dateLabel) * 15 + 36).'" height="48" rx="24" fill="#fff" fill-opacity=".92"/>'
                    .'<text x="18" y="32" font-family="Figtree, system-ui, sans-serif" font-weight="700" font-size="24" fill="#1D1533">'.e($dateLabel).'</text></g>';
            }
        }

        $shade = $title !== null
            ? '<rect width="800" height="600" fill="url(#'.$id.'s)"/>'
            : '';

        return '<svg class="poster '.$class.'" viewBox="0 0 800 600" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="'.e($title ?? $cfg['label']).'">'
            .'<defs>'
            .'<linearGradient id="'.$id.'g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="'.$a.'"/><stop offset="1" stop-color="'.$b.'"/></linearGradient>'
            .'<radialGradient id="'.$id.'r" cx="'.$glowX.'%" cy="'.$glowY.'%" r="60%"><stop offset="0" stop-color="#fff" stop-opacity=".45"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>'
            .'<linearGradient id="'.$id.'s" x1="0" y1="0" x2="0" y2="1"><stop offset=".35" stop-color="#1D1533" stop-opacity="0"/><stop offset="1" stop-color="#1D1533" stop-opacity=".55"/></linearGradient>'
            .'</defs>'
            .'<rect width="800" height="600" fill="url(#'.$id.'g)"/>'
            .'<rect width="800" height="600" fill="url(#'.$id.'r)"/>'
            .$marks.$shade.$words
            .'</svg>';
    }

    /**
     * 1200 × 630 PNG for link previews (Facebook, texts). Emoji can't be drawn
     * here, so it uses the gradient, a glow with embers, and the title. Cached by
     * event and last update.
     */
    public static function png(string $type, int $seed, string $title, string $line2, string $line3, string $cacheKey): string
    {
        $dir = storage_path('app/share');
        $file = $dir.'/v2-'.preg_replace('/[^a-z0-9_-]/i', '', $cacheKey).'.png';
        if (is_file($file)) {
            return $file;
        }
        @mkdir($dir, 0755, true);

        $cfg = config('event_types.'.$type) ?? config('event_types.card_show');
        [$a, $b] = array_map([self::class, 'rgb'], $cfg['colors']);
        $W = 1200;
        $H = 630;
        $im = imagecreatetruecolor($W, $H);
        // Diagonal gradient
        for ($x = 0; $x < $W + $H; $x += 2) {
            $t = $x / ($W + $H);
            $c = imagecolorallocate($im, (int) ($a[0] + ($b[0] - $a[0]) * $t), (int) ($a[1] + ($b[1] - $a[1]) * $t), (int) ($a[2] + ($b[2] - $a[2]) * $t));
            imageline($im, $x, 0, $x - $H, $H, $c);
            imageline($im, $x + 1, 0, $x + 1 - $H, $H, $c);
        }
        imagealphablending($im, true);
        // Soft glow
        for ($i = 14; $i > 0; $i--) {
            $glow = imagecolorallocatealpha($im, 255, 255, 255, 120);
            imagefilledellipse($im, 1040, 300, $i * 64, $i * 64, $glow);
        }
        // A few rising embers.
        $r = self::rand($seed);
        for ($i = 0; $i < 14; $i++) {
            $size = $r(4, 14);
            $ember = imagecolorallocatealpha($im, 255, $r(190, 235), $r(90, 160), $r(20, 70));
            imagefilledellipse($im, $r(820, 1160), $r(60, 560), $size, $size, $ember);
        }

        $font = resource_path('fonts/BricolageGrotesque_800ExtraBold.ttf');
        $font2 = resource_path('fonts/BricolageGrotesque_600SemiBold.ttf');
        $ink = imagecolorallocate($im, 255, 255, 255);
        $soft = imagecolorallocatealpha($im, 255, 255, 255, 25);
        $lines = self::wrap($title, 20, 3);
        $size = count($lines) > 2 ? 58 : 68;
        $y = 420 - (count($lines) - 1) * (int) ($size * 1.15);
        foreach ($lines as $l) {
            imagettftext($im, $size, 0, 64, $y, $ink, $font, $l);
            $y += (int) ($size * 1.15);
        }
        imagettftext($im, 30, 0, 66, $y + 18, $soft, $font2, $line2);
        imagettftext($im, 26, 0, 66, $y + 62, $soft, $font2, $line3);
        imagettftext($im, 30, 0, 64, 84, $ink, $font, config('platform.name'));

        imagepng($im, $file, 6);
        imagedestroy($im);

        return $file;
    }

    private static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');

        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    /** Word-wraps to at most $max lines of about $width characters. */
    public static function wrap(string $text, int $width, int $max): array
    {
        $lines = explode("\n", wordwrap(trim($text), $width, "\n", true));
        if (count($lines) > $max) {
            $lines = array_slice($lines, 0, $max);
            $lines[$max - 1] = rtrim(mb_substr($lines[$max - 1], 0, $width - 1)).'…';
        }

        return $lines;
    }

    /** Small deterministic random-number source from a seed. */
    private static function rand(int $seed): \Closure
    {
        $state = $seed * 2654435761 % 4294967296 ?: 1;

        return function (int $min, int $max) use (&$state) {
            $state = ($state * 1103515245 + 12345) % 2147483648;

            return $min + ($state % ($max - $min + 1));
        };
    }
}
