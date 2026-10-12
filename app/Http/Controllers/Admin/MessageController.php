<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\Event;
use App\Models\VendorApplication;
use App\Services\Notifier;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;

/** Message an event's vendors (load-in, parking, weather), and the log of everything sent. */
class MessageController extends Controller
{
    private const GROUPS = [
        'paid' => 'Confirmed vendors',
        'approved' => 'Approved, not yet paid',
        'pending' => 'Waiting on review',
        'waitlisted' => 'Waitlist',
    ];

    private function recipients(Event $event, array $groups)
    {
        return VendorApplication::where('event_id', $event->id)->whereIn('status', $groups)
            ->where('email', '<>', '')->orderBy('business_name')->get()->unique(fn ($a) => strtolower($a->email));
    }

    public function create(Event $event)
    {
        $counts = VendorApplication::where('event_id', $event->id)->where('email', '<>', '')
            ->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $sent = EmailLog::where('event_id', $event->id)->where('kind', 'message')->latest()->get()
            ->groupBy(fn ($m) => $m->subject.'|'.$m->created_at->format('Y-m-d H:i'));

        return view('admin.message', ['event' => $event, 'groups' => self::GROUPS, 'counts' => $counts, 'sent' => $sent]);
    }

    public function send(Request $request, Event $event)
    {
        $data = $request->validate([
            'groups' => ['required', 'array', 'min:1'],
            'groups.*' => ['in:'.implode(',', array_keys(self::GROUPS))],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $to = $this->recipients($event, $data['groups']);
        if ($to->isEmpty()) {
            return back()->withInput()->with('error', 'Nobody in those groups has an email address yet.');
        }
        foreach ($to as $a) {
            Notifier::send(Tenancy::current(), $a->email, $data['subject'], [
                "Hi {$a->contact_name},",
                $data['body'],
                'Event: '.$event->whenWhere(),
            ], ['label' => 'View my booking', 'url' => $a->statusUrl()], 'message', $event->id);
        }

        return redirect()->route('admin.messages.create', $event)
            ->with('status', 'Sent to '.$to->count().' '.str('vendor')->plural($to->count()).'.');
    }

    public function log(Request $request)
    {
        $kind = (string) $request->query('kind', '');
        $mails = EmailLog::query()->when($kind !== '', fn ($q) => $q->where('kind', $kind))
            ->latest('id')->paginate(40)->withQueryString();
        $events = Event::whereIn('id', $mails->pluck('event_id')->filter()->unique())->get()->keyBy('id');

        return view('admin.emails', compact('mails', 'events', 'kind'));
    }
}
