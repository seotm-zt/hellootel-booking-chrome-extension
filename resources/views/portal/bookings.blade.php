<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HellOotel — My Bookings</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            min-height: 100vh;
        }
        header {
            background: #1e293b;
            border-bottom: 1px solid #334155;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        header h1 { font-size: 18px; font-weight: 700; color: #f59e0b; }
        .user-bar { display: flex; align-items: center; gap: 14px; font-size: 14px; color: #94a3b8; }
        form.logout button {
            background: transparent;
            border: 1px solid #475569;
            border-radius: 6px;
            color: #94a3b8;
            padding: 6px 14px;
            font-size: 13px;
            cursor: pointer;
        }
        form.logout button:hover { border-color: #94a3b8; color: #f1f5f9; }
        main { padding: 28px 24px; max-width: 1200px; margin: 0 auto; }
        h2 { font-size: 16px; font-weight: 600; color: #cbd5e1; margin-bottom: 18px; }
        .empty { color: #475569; text-align: center; padding: 60px 0; font-size: 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        thead tr { background: #1e293b; }
        th {
            text-align: left;
            padding: 10px 14px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
            border-bottom: 1px solid #334155;
        }
        td {
            padding: 11px 14px;
            border-bottom: 1px solid #1e293b;
            vertical-align: top;
        }
        tr:hover td { background: #1e293b; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            background: #1e3a5f;
            color: #7dd3fc;
        }
        .badge--confirmed { background: #14532d; color: #86efac; }
        .badge--pending   { background: #422006; color: #fed7aa; }
        .tourists { font-size: 12px; color: #94a3b8; margin-top: 3px; }
        a.source-link { color: #f59e0b; font-size: 12px; text-decoration: none; }
        a.source-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<header>
    <h1>HellOotel Portal</h1>
    <div class="user-bar">
        <span>{{ $auth['name'] ?? $auth['username'] }}</span>
        <form class="logout" method="POST" action="{{ route('portal.logout') }}">
            @csrf
            <button type="submit">Sign out</button>
        </form>
    </div>
</header>

<main>
    <h2>My Processed Bookings ({{ $bookings->count() }})</h2>

    @if ($bookings->isEmpty())
        <div class="empty">No processed bookings found.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Hotel</th>
                    <th>Room</th>
                    <th>Dates</th>
                    <th>Guests</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Tourists</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bookings as $b)
                <tr>
                    <td>
                        <strong>{{ $b->booking_code ?? '—' }}</strong>
                        @if ($b->sourceBooking?->source_url)
                            <br>
                            <a class="source-link" href="{{ $b->sourceBooking->source_url }}" target="_blank">↗ source</a>
                        @endif
                    </td>
                    <td>
                        {{ $b->hotel_name ?? '—' }}
                        @if ($b->hotel_id)
                            <div class="tourists">ID: {{ $b->hotel_id }}</div>
                        @endif
                    </td>
                    <td>{{ $b->room_type_name ?? '—' }}</td>
                    <td>
                        @if ($b->arrival_at && $b->departure_at)
                            {{ $b->arrival_at->format('d.m.Y') }} –
                            {{ $b->departure_at->format('d.m.Y') }}
                            @if ($b->nights)
                                <div class="tourists">{{ $b->nights }} nights</div>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @php
                            $parts = array_filter([
                                $b->person_count_adults   ? $b->person_count_adults . ' ad.' : null,
                                $b->person_count_children ? $b->person_count_children . ' ch.' : null,
                                $b->person_count_teens    ? $b->person_count_teens . ' inf.' : null,
                            ]);
                        @endphp
                        {{ implode(', ', $parts) ?: '—' }}
                    </td>
                    <td>
                        @if ($b->price)
                            {{ number_format($b->price, 2) }} {{ $b->currency_code }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if ($b->confirmed_at)
                            <span class="badge badge--confirmed">Confirmed</span>
                            <div class="tourists">{{ $b->confirmed_at->format('d.m.Y') }}</div>
                        @else
                            <span class="badge badge--pending">Pending</span>
                        @endif
                    </td>
                    <td>
                        @if (!empty($b->tourists))
                            @foreach ($b->tourists as $t)
                                <div class="tourists">{{ trim(($t['last_name'] ?? '') . ' ' . ($t['first_name'] ?? '')) ?: '—' }}</div>
                            @endforeach
                        @else
                            <span class="tourists">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</main>
</body>
</html>
