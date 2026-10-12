<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\VendorApplication;

class DashboardController extends Controller
{
    public function index()
    {
        $events = Event::withCount([
            'applications as confirmed_count' => fn ($q) => $q->where('status', 'paid'),
            'applications as review_count' => fn ($q) => $q->where('status', 'pending'),
        ])->orderByRaw("status = 'archived'")->orderByRaw('starts_on is null')->orderBy('starts_on')->get();

        $needsReview = VendorApplication::with('event')->where('status', 'pending')->latest()->limit(10)->get();

        return view('admin.dashboard', compact('events', 'needsReview'));
    }

    public function order(TicketOrder $order)
    {
        $order->load('event', 'items.ticketType', 'tickets.ticketType');

        return view('admin.order', compact('order'));
    }
}
