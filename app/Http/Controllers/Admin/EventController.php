<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\CheckinController;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\TableType;
use App\Models\TicketType;
use App\Services\Bookings;
use App\Services\Finances;
use App\Services\FloorPlans;
use App\Services\Geocoder;
use App\Services\Ticketing;
use App\Support\Money;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function create(Request $request)
    {
        $type = array_key_exists($request->query('type'), config('event_types')) ? $request->query('type') : null;

        return view('admin.event-form', [
            'event' => new Event([
                'type' => $type ?? 'card_show',
                'categories' => $type ? implode(', ', config("event_types.$type.categories")) : '',
                'admission_note' => $type && config("event_types.$type.free_admission") ? 'Free admission' : '',
                'vendor_payment_mode' => 'review',
                'payment_window_days' => 7,
                'status' => 'draft',
                'applications_open' => true,
                'tickets_on_sale' => $type ? ! empty(config("event_types.$type.ticket_types")) : false,
                'amenities' => $type && config("event_types.$type.free_admission") ? ['free_parking'] : ['free_parking', 'indoor'],
                'faq' => [],
            ]),
            'chooseType' => $type === null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $template = config('event_types.'.$data['type']);
        [$cover, $err] = Uploads::storeImage($request->file('cover'), 'events', 1800);
        if ($err) {
            return back()->withInput()->with('error', $err);
        }

        $event = DB::transaction(function () use ($data, $template, $cover) {
            $event = new Event($data);
            $event->slug = Event::uniqueSlug($data['title']);
            $event->cover_path = $cover ?? '';
            $this->locate($event, true);
            $event->save();
            foreach ($template['space_types'] as $i => $t) {
                $event->tableTypes()->create($t + ['sort_order' => $i]);
            }
            foreach ($template['ticket_types'] as $i => $t) {
                $event->ticketTypes()->create($t + ['sort_order' => $i]);
            }

            return $event;
        });

        return redirect()->route('admin.events.show', $event)
            ->with('status', 'Event created with the usual '.$template['spaces'].' for a '.Str::lower($template['label']).'. Adjust prices and counts below.');
    }

    public function show(Request $request, Event $event)
    {
        $statusFilter = (string) $request->query('status', 'active');
        $applications = $event->applications()->with('tableType')
            ->when($statusFilter === 'active', fn ($q) => $q->whereNotIn('status', ['incomplete', 'declined', 'cancelled', 'checkout']))
            ->when($statusFilter !== 'active' && $statusFilter !== 'all', fn ($q) => $q->where('status', $statusFilter))
            ->orderByRaw("case status when 'pending' then 0 when 'approved' then 1 when 'waitlisted' then 2 when 'paid' then 3 else 4 end")
            ->orderBy('created_at')
            ->get();

        return view('admin.event', [
            'event' => $event,
            'tab' => $request->query('tab', 'vendors'),
            'statusFilter' => $statusFilter,
            'spaces' => Bookings::availability($event),
            'applications' => $applications,
            'ticketTypes' => Ticketing::availability($event, true),
            'orders' => $event->ticketOrders()->with('items')->where('status', 'paid')->latest()->limit(100)->get(),
            'stats' => CheckinController::stats($event),
            'money' => Finances::forEvent($event),
            'unsentTables' => FloorPlans::unsentCount($event),
        ]);
    }

    public function edit(Event $event)
    {
        $event->load('photos');

        return view('admin.event-form', ['event' => $event, 'chooseType' => false]);
    }

    public function update(Request $request, Event $event)
    {
        $data = $this->validated($request, $event);
        if ($request->hasFile('cover')) {
            [$cover, $err] = Uploads::storeImage($request->file('cover'), 'events', 1800);
            if ($err) {
                return back()->withInput()->with('error', $err);
            }
            Uploads::delete($event->cover_path);
            $event->cover_path = $cover;
        } elseif ($request->boolean('remove_cover')) {
            Uploads::delete($event->cover_path);
            $event->cover_path = '';
        }
        $event->fill($data);
        $this->locate($event, $event->isDirty(['venue_name', 'venue_address', 'town']));
        $event->save();

        return redirect()->route('admin.events.show', $event)->with('status', 'Event saved.');
    }

    /**
     * Sets map coordinates: a pin the organizer dropped wins; otherwise look
     * the address up when it changed or there's no pin yet.
     */
    private function locate(Event $event, bool $addressChanged): void
    {
        $req = request();
        if ($req->boolean('pin_moved') && is_numeric($req->input('lat')) && is_numeric($req->input('lng'))) {
            $event->lat = round((float) $req->input('lat'), 6);
            $event->lng = round((float) $req->input('lng'), 6);

            return;
        }
        if (($addressChanged || $event->lat === null) && ($event->town || $event->venue_address)) {
            $hit = Geocoder::locate($event->venue_name, $event->venue_address, $event->town);
            if ($hit) {
                [$event->lat, $event->lng] = $hit;
            }
        }
    }

    /**
     * Copies an event for its next date: details, spaces, ticket types and
     * floor plan layout. Vendors, tickets and photos stay with the original.
     */
    public function duplicate(Request $request, Event $event)
    {
        $data = $request->validate(['starts_on' => ['nullable', 'date'], 'title' => ['nullable', 'string', 'max:200']]);
        $copy = DB::transaction(function () use ($event, $data) {
            $copy = $event->replicate(['slug', 'checkin_token', 'status', 'notice', 'notice_text', 'copied_from_id']);
            $copy->title = $data['title'] ?? $event->title;
            $copy->starts_on = $data['starts_on'] ?? $event->starts_on?->copy()->addMonth();
            $copy->slug = Event::uniqueSlug($copy->title.' '.($copy->starts_on?->format('M Y') ?? 'copy'));
            $copy->status = 'draft';
            $copy->notice = '';
            $copy->notice_text = '';
            $copy->copied_from_id = $event->id;
            $copy->save();
            foreach ($event->tableTypes as $t) {
                $copy->tableTypes()->create($t->only(['name', 'description', 'price_cents', 'quantity', 'max_per_vendor', 'sort_order']));
            }
            foreach ($event->ticketTypes as $t) {
                $copy->ticketTypes()->create($t->only(['name', 'description', 'price_cents', 'quantity', 'max_per_order', 'active', 'sort_order']));
            }
            FloorPlans::copy($event, $copy);

            return $copy;
        });

        return redirect()->route('admin.events.edit', $copy)
            ->with('status', "Copied “{$event->title}” as a draft. Check the date and details, then publish it.");
    }

    public function addPhotos(Request $request, Event $event)
    {
        $request->validate(['photos' => ['required', 'array', 'max:20'], 'photos.*' => ['file', 'max:12288']]);
        $order = (int) $event->photos()->max('sort_order');
        $n = 0;
        foreach ($request->file('photos') as $file) {
            [$path, $err] = Uploads::storeImage($file, 'events', 1800);
            if ($err) {
                return back()->with('error', $err);
            }
            $event->photos()->create(['path' => $path, 'sort_order' => ++$order]);
            $n++;
        }

        return redirect(route('admin.events.edit', $event).'#photos')->with('status', "Added {$n} ".Str::plural('photo', $n).'.');
    }

    public function deletePhoto(Event $event, EventPhoto $photo)
    {
        abort_unless($photo->event_id === $event->id, 404);
        Uploads::delete($photo->path);
        $photo->delete();

        return redirect(route('admin.events.edit', $event).'#photos')->with('status', 'Photo removed.');
    }

    public function addTableType(Request $request, Event $event)
    {
        $event->tableTypes()->create($this->spaceData($request) + ['sort_order' => $event->tableTypes()->count()]);

        return back()->with('status', 'Added.');
    }

    public function updateTableType(Request $request, TableType $tableType)
    {
        $data = $this->spaceData($request);
        $committed = Bookings::committed($tableType->id);
        if ($data['quantity'] < $committed) {
            return back()->with('error', "{$committed} of these are already booked or held, so the count can't go below that.");
        }
        $tableType->update($data);

        return back()->with('status', 'Saved.');
    }

    public function addTicketType(Request $request, Event $event)
    {
        $event->ticketTypes()->create($this->ticketData($request) + ['sort_order' => $event->ticketTypes()->count()]);

        return redirect()->route('admin.events.show', [$event, 'tab' => 'tickets'])->with('status', 'Ticket type added.');
    }

    public function updateTicketType(Request $request, TicketType $ticketType)
    {
        $ticketType->update($this->ticketData($request) + ['active' => $request->boolean('active')]);

        return redirect()->route('admin.events.show', [$ticketType->event, 'tab' => 'tickets'])->with('status', 'Saved.');
    }

    /** Add a vendor who booked by text, Facebook or in person, in one step. */
    public function walkIn(Request $request, Event $event, Bookings $bookings)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:200'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'table_type_id' => ['required', Rule::exists('table_types', 'id')->where('event_id', $event->id)],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'table_numbers' => ['nullable', 'string', 'max:60'],
            'amount' => ['nullable', 'string', 'max:12'],
            'method' => ['required', 'in:cash,other,unpaid'],
        ]);
        $tableType = $event->tableTypes()->findOrFail($data['table_type_id']);
        if (($data['amount'] ?? '') !== '') {
            $data['amount_cents'] = Money::parse($data['amount']);
            if ($data['amount_cents'] === null) {
                return back()->withInput()->with('error', 'That amount doesn’t look right.');
            }
        }
        $data['contact_name'] ??= '';
        $error = $bookings->addWalkIn($event, $tableType, $data, $data['method']);

        return back()->with($error ? 'error' : 'status', $error ?? "Added {$data['business_name']}.");
    }

    public function comp(Request $request, Event $event, Ticketing $ticketing)
    {
        $data = $request->validate([
            'buyer_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'ticket_type_id' => ['required', Rule::exists('ticket_types', 'id')->where('event_id', $event->id)],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);
        [, , $error] = $ticketing->createOrder($event, $data['buyer_name'], $data['email'], [$data['ticket_type_id'] => $data['quantity']], 'comp');

        return redirect()->route('admin.events.show', [$event, 'tab' => 'tickets'])
            ->with($error ? 'error' : 'status', $error ?? 'Comp tickets sent to '.$data['email'].'.');
    }

    public function resetCheckin(Event $event)
    {
        $event->forceFill(['checkin_token' => Str::lower(Str::random(32))])->save();

        return redirect()->route('admin.events.show', [$event, 'tab' => 'door'])->with('status', 'New check-in link made. The old one no longer works.');
    }

    public function vendorsCsv(Event $event)
    {
        $rows = $event->applications()->with('tableType')->whereNotIn('status', ['checkout', 'incomplete'])->orderBy('business_name')->get();
        $filename = $event->slug.'-vendors.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Business', 'Contact', 'Email', 'Phone', 'Status', 'Space', 'Qty', 'Table numbers', 'Amount', 'Paid via', 'Paid at', 'Categories', 'Needs power', 'Checked in']);
            foreach ($rows as $a) {
                fputcsv($out, [$a->business_name, $a->contact_name, $a->email, $a->phone, $a->statusBadge()[1], $a->tableType?->name,
                    $a->quantity, $a->table_numbers, $a->amount_cents !== null ? number_format($a->amount_cents / 100, 2, '.', '') : '',
                    $a->payment_method, $a->paid_at?->format('Y-m-d H:i'), $a->categories, $a->needs_power ? 'yes' : '', $a->checked_in_at ? 'yes' : '']);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function validated(Request $request, ?Event $event = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(config('event_types')))],
            'title' => ['required', 'string', 'max:200'],
            'starts_on' => ['nullable', 'date'],
            'hours' => ['nullable', 'string', 'max:80'],
            'vendor_setup' => ['nullable', 'string', 'max:120'],
            'venue_name' => ['nullable', 'string', 'max:200'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'town' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'admission_note' => ['nullable', 'string', 'max:200'],
            'categories' => ['nullable', 'string', 'max:500'],
            'vendor_terms' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(array_keys(Event::STATUSES))],
            'notice' => ['nullable', Rule::in(['', 'cancelled', 'rescheduled'])],
            'notice_text' => ['nullable', 'string', 'max:500'],
            'vendor_payment_mode' => ['required', Rule::in(array_keys(Event::PAYMENT_MODES))],
            'payment_window_days' => ['required', 'integer', 'min:1', 'max:60'],
            'ticket_capacity' => ['nullable', 'integer', 'min:1'],
            'parking_info' => ['nullable', 'string', 'max:500'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => [Rule::in(array_keys(config('platform.amenities')))],
            'faq' => ['nullable', 'array', 'max:12'],
            'faq.*.q' => ['nullable', 'string', 'max:200'],
            'faq.*.a' => ['nullable', 'string', 'max:2000'],
            'cover' => ['nullable', 'file', 'max:12288'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        unset($data['cover'], $data['lat'], $data['lng']);
        $data['amenities'] = array_values($data['amenities'] ?? []);
        $data['faq'] = array_values(array_filter(array_map(fn ($f) => ['q' => trim($f['q'] ?? ''), 'a' => trim($f['a'] ?? '')], $data['faq'] ?? []),
            fn ($f) => $f['q'] !== '' && $f['a'] !== ''));
        foreach (['hours', 'vendor_setup', 'venue_name', 'venue_address', 'town', 'admission_note', 'categories', 'notice', 'notice_text', 'parking_info'] as $k) {
            $data[$k] = $data[$k] ?? '';
        }
        $data['applications_open'] = $request->boolean('applications_open');
        $data['tickets_on_sale'] = $request->boolean('tickets_on_sale');

        return $data;
    }

    private function spaceData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'string', 'max:12'],
            'quantity' => ['required', 'integer', 'min:0', 'max:5000'],
            'max_per_vendor' => ['required', 'integer', 'min:1', 'max:50'],
        ]);
        $price = Money::parse($data['price']);
        abort_if($price === null, 422, 'Enter a price like 50 or 50.00.');

        return ['name' => $data['name'], 'description' => $data['description'] ?? '', 'price_cents' => $price,
            'quantity' => $data['quantity'], 'max_per_vendor' => $data['max_per_vendor']];
    }

    private function ticketData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'string', 'max:12'],
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'max_per_order' => ['required', 'integer', 'min:1', 'max:50'],
        ]);
        $price = Money::parse($data['price']);
        abort_if($price === null, 422, 'Enter a price like 5 or 5.00.');

        return ['name' => $data['name'], 'description' => $data['description'] ?? '', 'price_cents' => $price,
            'quantity' => $data['quantity'], 'max_per_order' => $data['max_per_order']];
    }
}
