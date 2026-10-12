<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TicketOrder;
use App\Services\Ticketing;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function form(Event $event)
    {
        abort_unless($event->isPublic(), 404);

        return view('site.tickets', [
            'event' => $event,
            'types' => Ticketing::availability($event),
            'selling' => Ticketing::selling($event),
        ]);
    }

    public function buy(Request $request, Event $event, Ticketing $ticketing)
    {
        abort_unless($event->isPublic() && Ticketing::selling($event), 404);
        $data = $request->validate([
            'buyer_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'qty' => ['required', 'array'],
            'qty.*' => ['nullable', 'integer', 'min:0', 'max:50'],
        ]);

        [$order, $url, $error] = $ticketing->createOrder($event, $data['buyer_name'], $data['email'], $data['qty']);
        if ($error) {
            return back()->withInput()->with('error', $error);
        }

        return $url ? redirect()->away($url) : redirect()->route('site.order', $order);
    }

    public function order(TicketOrder $order)
    {
        $order->load('event', 'items.ticketType', 'tickets.ticketType');

        return view('site.order', ['order' => $order, 'event' => $order->event]);
    }

    public function qr(TicketOrder $order, string $code)
    {
        $ticket = $order->tickets()->where('code', $code)->firstOrFail();
        $svg = (new QRCode(new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'addQuietzone' => true,
        ])))->render($ticket->code);

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age=86400']);
    }
}
