<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorApplication;
use App\Services\Bookings;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function show(VendorApplication $application)
    {
        $application->load('event', 'tableType', 'vendor');
        // Other bookings by the same vendor, with this organizer only.
        $history = VendorApplication::with('event')->where('id', '<>', $application->id)
            ->where(fn ($q) => $q->where('email', $application->email)
                ->when($application->vendor_id, fn ($q) => $q->orWhere('vendor_id', $application->vendor_id)))
            ->latest()->get();

        return view('admin.application', ['app' => $application, 'history' => $history]);
    }

    public function act(Request $request, VendorApplication $application, Bookings $bookings)
    {
        $action = (string) $request->input('action');
        $error = null;
        $message = 'Saved.';

        switch ($action) {
            case 'approve':
                $error = $bookings->approve($application);
                $message = $application->fresh()->status === 'paid' ? 'Approved and confirmed.' : 'Approved. The vendor was emailed a payment link.';
                break;
            case 'waitlist':
            case 'decline':
            case 'cancel':
                $status = ['waitlist' => 'waitlisted', 'decline' => 'declined', 'cancel' => 'cancelled'][$action];
                $error = $bookings->setStatus($application, $status, $request->boolean('refund'));
                $message = 'Marked '.$status.'.';
                break;
            case 'mark_paid':
                $method = in_array($request->input('method'), ['cash', 'other'], true) ? $request->input('method') : 'cash';
                if (! in_array($application->status, ['pending', 'approved', 'waitlisted', 'incomplete'], true) || $application->paid_at) {
                    $error = 'Only an unpaid application can be marked paid.';
                    break;
                }
                // Paying by hand also confirms: route through approve's space check first.
                if ($application->status !== 'approved') {
                    $error = $bookings->approve($application);
                }
                if (! $error) {
                    $bookings->markPaid($application->fresh(), $method);
                    $message = 'Marked paid ('.$method.') and confirmed.';
                }
                break;
            case 'details':
                $data = $request->validate([
                    'table_numbers' => ['nullable', 'string', 'max:60'],
                    'admin_note' => ['nullable', 'string', 'max:2000'],
                ]);
                $application->update(['table_numbers' => $data['table_numbers'] ?? '', 'admin_note' => $data['admin_note'] ?? null]);
                break;
            default:
                abort(400);
        }

        return back()->with($error ? 'error' : 'status', $error ?? $message);
    }
}
