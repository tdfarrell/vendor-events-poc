@extends('layouts.directory')
@section('title', 'My vendor account · '.config('platform.name'))

@section('content')
<div class="row between" style="margin-bottom:16px">
    <h1 style="margin:0">{{ $vendor->business_name ?: 'My vendor account' }}</h1>
    <div class="row">
        @if ($vendor->isPublic())<a class="btn small" href="{{ route('directory.vendor', $vendor->slug) }}">View public profile</a>@endif
        <form method="post" action="{{ route('vendor.logout') }}">@csrf<button class="btn small">Sign out</button></form>
    </div>
</div>

<div class="cols">
    <section class="card flush">
        <div class="card-head"><h2>My bookings</h2><small>Across every organizer</small></div>
        <div class="table-wrap">
            <table class="list">
                <thead><tr><th>Event</th><th>Space</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($bookings as $b)
                    @php [$cls, $label] = $b->statusBadge(); @endphp
                    <tr>
                        <td><a href="{{ $b->statusUrl() }}">{{ $b->event->title }}</a><br><small>{{ $b->event->starts_on?->format('D, M j, Y') ?? 'TBA' }} · {{ $b->event->organizer->name }}</small></td>
                        <td>{{ $b->tableSummary() }}@if ($b->table_numbers)<br><small>Table {{ $b->table_numbers }}</small>@endif</td>
                        <td><span class="badge {{ $cls }}">{{ $label }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3"><p class="muted" style="margin:0">No bookings yet. <a href="{{ route('directory.events') }}">Find events that are booking vendors.</a></p></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>Profile</h2>
        <p class="muted" style="font-size:.9rem">Organizers see this when you apply, and shoppers see it in the directory if you choose.</p>
        <form method="post" action="{{ route('vendor.account') }}">
            @csrf @method('put')
            <div class="field"><label>Business name</label><input type="text" name="business_name" required value="{{ old('business_name', $vendor->business_name) }}"></div>
            <div class="field"><label>Contact name</label><input type="text" name="contact_name" value="{{ old('contact_name', $vendor->contact_name) }}"></div>
            <div class="fields-2">
                <div class="field"><label>Phone</label><input type="tel" name="phone" value="{{ old('phone', $vendor->phone) }}"></div>
                <div class="field"><label>Town</label><input type="text" name="town" value="{{ old('town', $vendor->town) }}"></div>
            </div>
            <div class="field"><label>What you sell <span class="hint">comma separated</span></label><input type="text" name="categories" value="{{ old('categories', $vendor->categories) }}"></div>
            <div class="field"><label>About</label><textarea name="description">{{ old('description', $vendor->description) }}</textarea></div>
            <div class="field"><label>Instagram</label><input type="text" name="instagram" value="{{ old('instagram', $vendor->instagram) }}" placeholder="@yourshop"></div>
            <div class="field"><label>Facebook</label><input type="text" name="facebook" value="{{ old('facebook', $vendor->facebook) }}"></div>
            <div class="field"><label>Website</label><input type="text" name="website" value="{{ old('website', $vendor->website) }}"></div>
            <div class="field"><label class="check"><input type="checkbox" name="listed" value="1" @checked(old('listed', $vendor->listed))> Show my profile in the public vendor directory</label></div>
            <button class="btn primary">Save profile</button>
        </form>
    </section>
</div>
@endsection
