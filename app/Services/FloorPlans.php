<?php

namespace App\Services;

use App\Models\Event;
use App\Models\FloorPlan;
use App\Models\TableType;
use App\Models\VendorApplication;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/*
 * Floor plans, ported from the Nutmeg app's floorplan.php: a scaled map of
 * the room in feet, with sellable spaces (tables, booths) and landmarks.
 *
 * Table numbers live on the map items. Which vendor is at which table stays
 * on vendor_applications.table_numbers, so emails, check-in, the CSV and the
 * vendor's booking page keep working without the map.
 */
class FloorPlans
{
    public static function kinds(): array
    {
        return config('floorplan.kinds');
    }

    public static function sellable(array $el): bool
    {
        return (bool) (self::kinds()[$el['kind'] ?? ''][1] ?? false);
    }

    /** "12, 13" / "12 & 13" / "#12" → ['12', '13'] */
    public static function splitNumbers(string $s): array
    {
        $out = [];
        foreach (preg_split('/[\s,;&\/]+/', $s) ?: [] as $t) {
            $t = ltrim(trim($t), '#');
            if ($t !== '' && ! in_array($t, $out, true)) {
                $out[] = $t;
            }
        }

        return $out;
    }

    public static function cleanNumber(string $n): string
    {
        return mb_substr(preg_replace('/[^A-Za-z0-9-]/', '', $n) ?? '', 0, 6);
    }

    public static function isPublic(Event $event): bool
    {
        $plan = $event->floorPlan;

        return $plan && $plan->is_public && $plan->hasTables();
    }

    /** Applications that hold (or may soon hold) a space, with their numbers split out. */
    public static function placeableApplications(Event $event)
    {
        return VendorApplication::with('vendor')
            ->where('event_id', $event->id)
            ->whereIn('status', config('floorplan.placeable'))
            ->orderByRaw("case status when 'paid' then 0 when 'approved' then 1 else 2 end")
            ->orderBy('business_name')
            ->get();
    }

    /** Everything the editor needs. */
    public static function editorData(Event $event): array
    {
        $plan = $event->floorPlan;

        return [
            'mode' => 'admin',
            'room' => $plan
                ? ['w' => $plan->width_ft, 'd' => $plan->depth_ft, 'aisle' => $plan->aisle_ft, 'public' => $plan->is_public]
                : ['w' => 60, 'd' => 40, 'aisle' => 4, 'public' => false],
            'elements' => $plan?->elements ?? [],
            'types' => Bookings::availability($event)->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->name, 'price' => $t->price_cents, 'quantity' => $t->quantity, 'committed' => $t->committed,
            ])->values()->all(),
            'apps' => self::appsForEditor($event),
            'kinds' => self::kinds(),
            'isNew' => ! $plan,
            'saveUrl' => route('admin.floor-plan.save', $event),
            'editTypesUrl' => route('admin.events.show', [$event, 'tab' => 'spaces']),
        ];
    }

    private static function appsForEditor(Event $event): array
    {
        return self::placeableApplications($event)->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->business_name,
            'status' => $a->status,
            'type' => $a->table_type_id,
            'qty' => $a->quantity,
            'numbers' => self::splitNumbers($a->table_numbers),
            'url' => route('admin.applications.show', $a->id),
        ])->values()->all();
    }

    /**
     * Validates posted elements. Returns [elements, errors].
     * Coordinates snap to half feet and are kept inside the room.
     */
    public static function cleanElements($raw, array $typeIds, float $roomW, float $roomD): array
    {
        if (! is_array($raw)) {
            return [[], ['The floor plan data was missing.']];
        }
        if (count($raw) > config('floorplan.max_elements')) {
            return [[], ['A floor plan can have up to '.config('floorplan.max_elements').' items.']];
        }
        $kinds = self::kinds();
        $snap = fn ($v) => round(((float) $v) * 2) / 2;
        $out = [];
        $errors = [];
        $seenIds = [];
        $seenNums = [];
        foreach ($raw as $e) {
            if (! is_array($e) || ! isset($kinds[$e['kind'] ?? ''])) {
                continue;
            }
            $kind = $e['kind'];
            $id = (string) ($e['id'] ?? '');
            if (! preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id) || isset($seenIds[$id])) {
                $id = 'e'.bin2hex(random_bytes(5));
            }
            $seenIds[$id] = true;
            $w = max(0.5, min(400, $snap($e['w'] ?? $kinds[$kind][2])));
            $h = max(0.5, min(400, $snap($e['h'] ?? $kinds[$kind][3])));
            $el = [
                'id' => $id, 'kind' => $kind,
                'x' => max(0, min(max(0, $roomW - $w), $snap($e['x'] ?? 0))),
                'y' => max(0, min(max(0, $roomD - $h), $snap($e['y'] ?? 0))),
                'w' => $w, 'h' => $h, 'rot' => ((int) ($e['rot'] ?? 0)) === 90 ? 90 : 0,
            ];
            if ($kinds[$kind][1]) {
                $num = self::cleanNumber((string) ($e['num'] ?? ''));
                if ($num === '') {
                    $errors[] = 'Every table needs a number. Use "Renumber" to fill them in.';

                    continue;
                }
                if (isset($seenNums[strtolower($num)])) {
                    $errors[] = "Table number $num is used twice. Each table needs its own number.";
                }
                $seenNums[strtolower($num)] = true;
                $type = (int) ($e['type'] ?? 0);
                $el['num'] = $num;
                $el['type'] = in_array($type, $typeIds, true) ? $type : null;
            } else {
                $el['label'] = mb_substr(trim((string) ($e['label'] ?? '')), 0, 60);
            }
            $out[] = $el;
        }

        return [$out, array_values(array_unique($errors))];
    }

    /**
     * Saves the map and the vendor assignments posted from the editor.
     * $payload: {width, depth, aisle, public, elements[], assignments{appId: [num...]}, sync_counts}
     * Returns [ok, messages].
     */
    public static function save(Event $event, array $payload): array
    {
        $types = $event->tableTypes()->get();
        $typeIds = $types->pluck('id')->map(fn ($id) => (int) $id)->all();
        $w = max(10, min(400, round(((float) ($payload['width'] ?? 60)) * 2) / 2));
        $d = max(10, min(400, round(((float) ($payload['depth'] ?? 40)) * 2) / 2));
        $aisle = max(0, min(30, round(((float) ($payload['aisle'] ?? 4)) * 2) / 2));
        [$elements, $errors] = self::cleanElements($payload['elements'] ?? null, $typeIds, $w, $d);
        if ($errors) {
            return [false, $errors];
        }

        $mapNums = [];
        foreach ($elements as $el) {
            if (self::sellable($el)) {
                $mapNums[strtolower($el['num'])] = $el['num'];
            }
        }

        // Work out each placeable vendor's new table numbers.
        $posted = is_array($payload['assignments'] ?? null) ? $payload['assignments'] : [];
        $updates = [];
        $claimed = [];
        foreach (self::placeableApplications($event) as $a) {
            $assigned = [];
            foreach ((array) ($posted[(string) $a->id] ?? []) as $n) {
                $key = strtolower(self::cleanNumber((string) $n));
                if (! isset($mapNums[$key])) {
                    continue;
                }
                if (isset($claimed[$key]) && $claimed[$key][0] !== $a->id) {
                    $errors[] = "Table {$mapNums[$key]} is assigned to both {$claimed[$key][1]} and {$a->business_name}.";

                    continue;
                }
                if (in_array($mapNums[$key], $assigned, true)) {
                    continue;
                }
                $claimed[$key] = [$a->id, $a->business_name];
                $assigned[] = $mapNums[$key];
            }
            // Keep numbers typed by hand that aren't on the map (an overflow room, say).
            $extras = array_values(array_filter(self::splitNumbers($a->table_numbers), fn ($n) => ! isset($mapNums[strtolower($n)])));
            $value = implode(', ', array_merge($assigned, $extras));
            if (mb_strlen($value) > 60) {
                $errors[] = "{$a->business_name} has too many table numbers to save.";

                continue;
            }
            if ($value !== (string) $a->table_numbers) {
                $updates[$a->id] = $value;
            }
        }
        if ($errors) {
            return [false, array_values(array_unique($errors))];
        }

        $messages = [];
        DB::transaction(function () use ($event, $w, $d, $aisle, $payload, $elements, $updates, $types, &$messages) {
            FloorPlan::updateOrCreate(['event_id' => $event->id], [
                'width_ft' => $w, 'depth_ft' => $d, 'aisle_ft' => $aisle,
                'elements' => $elements, 'is_public' => ! empty($payload['public']),
            ]);
            foreach ($updates as $appId => $value) {
                VendorApplication::whereKey($appId)->where('event_id', $event->id)->update(['table_numbers' => $value]);
            }
            if ($updates) {
                $messages[] = count($updates).' vendor '.(count($updates) === 1 ? 'assignment' : 'assignments').' updated.';
            }
            if (! empty($payload['sync_counts'])) {
                $counts = [];
                foreach ($elements as $el) {
                    if (self::sellable($el) && $el['type']) {
                        $counts[$el['type']] = ($counts[$el['type']] ?? 0) + 1;
                    }
                }
                foreach ($types as $t) {
                    $n = $counts[$t->id] ?? 0;
                    $committed = Bookings::committed($t->id);
                    if ($n === 0) {
                        $messages[] = "\"{$t->name}\" has no tables on the map, so its count stayed at {$t->quantity}.";

                        continue;
                    }
                    if ($n < $committed) {
                        $messages[] = "\"{$t->name}\": $committed are already paid or on hold, so the count was set to $committed, not $n.";
                        $n = $committed;
                    }
                    if ($n !== (int) $t->quantity) {
                        $messages[] = "\"{$t->name}\" count changed from {$t->quantity} to $n.";
                        $t->update(['quantity' => $n]);
                    }
                }
            }
        });

        return [true, $messages];
    }

    /** Copies a layout between events. Space types are matched by name, then by position. */
    public static function copy(Event $from, Event $to): bool
    {
        $plan = $from->floorPlan;
        if (! $plan) {
            return false;
        }
        $fromTypes = $from->tableTypes()->get()->values();
        $toTypes = $to->tableTypes()->get()->values();
        $map = [];
        foreach ($fromTypes as $i => $t) {
            $match = $toTypes->first(fn ($u) => strcasecmp(trim($u->name), trim($t->name)) === 0) ?? $toTypes->get($i) ?? $toTypes->first();
            $map[$t->id] = $match?->id;
        }
        $elements = array_map(function ($el) use ($map) {
            if (array_key_exists('type', $el)) {
                $el['type'] = $el['type'] !== null ? ($map[(int) $el['type']] ?? null) : null;
            }

            return $el;
        }, $plan->elements ?? []);
        FloorPlan::updateOrCreate(['event_id' => $to->id], [
            'width_ft' => $plan->width_ft, 'depth_ft' => $plan->depth_ft, 'aisle_ft' => $plan->aisle_ft,
            'elements' => $elements, 'is_public' => false,
        ]);

        return true;
    }

    public static function unsentCount(Event $event): int
    {
        return VendorApplication::where('event_id', $event->id)->where('status', 'paid')
            ->where('table_numbers', '<>', '')->whereColumn('table_numbers', '<>', 'table_notified')->count();
    }

    /** Emails confirmed vendors their table numbers. Returns how many were sent. */
    public static function emailAssignments(Event $event, bool $onlyChanged): int
    {
        $mapUrl = self::isPublic($event) ? $event->publicUrl('/map') : '';
        $sent = 0;
        $apps = VendorApplication::where('event_id', $event->id)->where('status', 'paid')->where('table_numbers', '<>', '')->get();
        foreach ($apps as $a) {
            if ($onlyChanged && $a->table_numbers === $a->table_notified) {
                continue;
            }
            $nums = self::splitNumbers($a->table_numbers);
            $label = (count($nums) > 1 ? 'Tables ' : 'Table ').implode(' & ', $nums);
            $lines = [
                "Hi {$a->contact_name},",
                $a->table_notified !== ''
                    ? "Your spot at {$event->title} has changed. {$a->business_name} is now at $label."
                    : "Your spot at {$event->title} is set. {$a->business_name} is at $label.",
                'Event: '.$event->whenWhere(),
            ];
            if ($event->vendor_setup) {
                $lines[] = 'Vendor setup: '.$event->vendor_setup;
            }
            if ($mapUrl) {
                $lines[] = 'The floor map shows where you are and what’s around you. Shoppers can find you on it too.';
            }
            Notifier::send($event->organizer, $a->email, "$label: {$event->title}", $lines,
                $mapUrl ? ['label' => 'See the floor map', 'url' => $mapUrl.'#t'.rawurlencode($nums[0] ?? '')]
                    : ['label' => 'View my booking', 'url' => $a->statusUrl()],
                'tables', $event->id);
            $a->update(['table_notified' => $a->table_numbers]);
            $sent++;
        }

        return $sent;
    }

    /** What the public map needs: room, items, prices, and who is at each table. */
    public static function publicData(Event $event, FloorPlan $plan, bool $applyOpen): array
    {
        $types = [];
        foreach (Bookings::availability($event) as $t) {
            $types[$t->id] = ['name' => $t->name, 'price' => $t->price_cents, 'available' => $t->available];
        }
        $vendors = [];
        $byNum = [];
        $confirmed = VendorApplication::with('vendor')->where('event_id', $event->id)->where('status', 'paid')
            ->where('table_numbers', '<>', '')->orderBy('business_name')->get()
            ->filter(fn ($a) => ! $a->vendor || $a->vendor->listed)->values();
        foreach ($confirmed as $i => $a) {
            $v = $a->vendor;
            $cats = array_values(array_filter(array_map('trim', explode(',', $v?->categories ?: $a->categories))));
            $vendors[$i] = [
                'name' => $a->business_name, 'cats' => $cats,
                'logo' => $v?->logoUrl() ?: '', 'url' => $v && $v->isPublic() ? $v->profileUrl() : '',
            ];
            foreach (self::splitNumbers($a->table_numbers) as $n) {
                $byNum[strtolower($n)] = $i;
            }
        }
        $elements = [];
        foreach ($plan->elements ?? [] as $el) {
            if (self::sellable($el)) {
                $el['v'] = $byNum[strtolower($el['num'])] ?? null;
            }
            $elements[] = $el;
        }

        return [
            'mode' => 'public',
            'room' => ['w' => $plan->width_ft, 'd' => $plan->depth_ft, 'name' => $event->venue_name],
            'types' => $types,
            'elements' => $elements,
            'vendors' => $vendors,
            'kinds' => self::kinds(),
            'applyUrl' => $applyOpen ? route('site.apply', $event) : '',
            'space' => $event->space(),
        ];
    }

    public static function money(int $cents): string
    {
        return Money::format($cents);
    }
}
