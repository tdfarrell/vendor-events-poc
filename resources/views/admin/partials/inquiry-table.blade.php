@if ($inquiries->isEmpty())
    <p class="card-body muted" style="margin:0">No requests yet. Share your venue page and they'll show up here.</p>
@else
    <div class="table-wrap"><table class="list">
        <thead><tr><th>From</th><th>Occasion</th><th>Date</th><th class="num">Guests</th><th>Status</th><th class="num">Sent</th></tr></thead>
        @foreach ($inquiries as $i)
            @php [$cls, $label] = $i->statusBadge(); @endphp
            <tr class="click">
                <td><a href="{{ route('admin.inquiries.show', $i->id) }}"><strong>{{ $i->name }}</strong></a><br><small>{{ $i->email }}</small></td>
                <td>{{ $i->kindLabel() }}<br><small>{{ $i->room?->name ?? $i->venue->name }}</small></td>
                <td>{{ $i->event_date?->format('D, M j') ?? 'Not set' }}@if ($i->date_flexible)<br><small>flexible</small>@endif</td>
                <td class="num">{{ $i->guests ?? '–' }}</td>
                <td><span class="badge {{ $cls }}">{{ $label }}</span></td>
                <td class="num"><small>{{ $i->created_at->diffForHumans() }}</small></td>
            </tr>
        @endforeach
    </table></div>
@endif
