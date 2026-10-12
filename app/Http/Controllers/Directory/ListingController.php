<?php

namespace App\Http\Controllers\Directory;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\Notifier;
use Illuminate\Http\Request;

/** Off-platform events in the directory, and the "this is my event" claim form. */
class ListingController extends Controller
{
    public function show(Listing $listing)
    {
        abort_unless($listing->status === 'listed', 404);

        return view('directory.listing', compact('listing'));
    }

    public function claim(Request $request, Listing $listing)
    {
        abort_unless($listing->status === 'listed', 404);
        if ($request->filled('company_website')) {
            return back()->with('error', 'Something went wrong. Please try again.');
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $listing->claims()->create(array_map(fn ($v) => $v ?? '', $data));

        if ($to = config('platform.admin_email')) {
            Notifier::send(null, $to, "Claim request: {$listing->title}", [
                "{$data['name']} ({$data['email']}) says they run {$listing->title}.",
                'Role: '.($data['role'] ?: 'not given').($data['note'] ? "\nNote: {$data['note']}" : ''),
            ], ['label' => 'Review claims', 'url' => route('platform.dashboard')], 'claim');
        }

        return back()->with('status', "Thanks. We'll check and email you at {$data['email']} to set up your event on ".config('platform.name').'.');
    }
}
