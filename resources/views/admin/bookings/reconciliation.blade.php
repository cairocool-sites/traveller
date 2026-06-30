<!doctype html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking identity reconciliation {{ $booking->booking_reference }}</title>
    <style>
        body { background: #f6f8fb; color: #0b1f33; font-family: Inter, "Segoe UI", Tahoma, Arial, sans-serif; margin: 0; padding: 32px; }
        main { background: #fff; border: 1px solid #d8e0ea; border-radius: 10px; margin: 0 auto; max-width: 1120px; padding: 28px; }
        h1, h2 { margin: 0 0 8px; }
        .warning { background: #fff7db; border: 1px solid #c9a227; border-radius: 8px; color: #604800; font-weight: 800; margin: 18px 0; padding: 14px 16px; }
        .summary { color: #506273; font-weight: 700; margin-bottom: 24px; }
        table { border-collapse: collapse; margin-top: 14px; width: 100%; }
        th, td { border-bottom: 1px solid #e5ebf2; padding: 12px; text-align: left; vertical-align: top; }
        th { color: #506273; font-size: 12px; text-transform: uppercase; }
        .badge { background: #fff1d1; border-radius: 999px; color: #7a4d00; display: inline-block; font-size: 12px; font-weight: 800; padding: 5px 10px; }
        .form { border: 1px solid #e5ebf2; border-radius: 8px; margin-top: 24px; padding: 16px; }
        input, textarea { border: 1px solid #cbd5e1; border-radius: 6px; display: block; margin-top: 6px; padding: 10px; width: 100%; }
        label { display: block; font-weight: 800; margin-top: 12px; }
        button { background: #0f766e; border: 0; border-radius: 8px; color: #fff; cursor: pointer; font-weight: 900; margin-top: 14px; padding: 11px 16px; }
        .error { color: #b91c1c; font-weight: 800; }
    </style>
</head>
<body>
    <main>
        <h1>Booking identity reconciliation</h1>
        <p class="summary">
            {{ $booking->booking_reference }} · Stored supplier {{ $booking->supplier_booking_reference ?: 'not supplied' }} · Classification
            <span class="badge">{{ $audit['classification'] }}</span>
        </p>

        <div class="warning">
            Supplier identity is unresolved until an exact Booking List candidate matches clientReference, hotel code, dates, occupancy, and currency. Customer voucher, payment, cancellation, and modification actions remain blocked while unresolved.
        </div>

        <h2>Sanitized comparison</h2>
        <table aria-label="Supplier identity comparison">
            <thead>
                <tr>
                    <th>Source</th>
                    <th>Reference</th>
                    <th>ClientReference match</th>
                    <th>Hotel code</th>
                    <th>Dates</th>
                    <th>Status</th>
                    <th>Currency</th>
                    <th>Identity classification</th>
                </tr>
            </thead>
            <tbody>
                @foreach ([
                    'Local record' => $audit['local'],
                    'Original booking response' => $audit['original'],
                    'HBX detail result' => $audit['detail'],
                    'Booking-list candidate' => $audit['candidates'][0] ?? [],
                ] as $label => $row)
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ $row['supplier_reference'] ?? $row['booking_reference_value'] ?? $row['reference'] ?? 'not supplied' }}</td>
                        <td>{{ array_key_exists('client_reference_match', $row) ? ($row['client_reference_match'] ? 'yes' : 'no') : 'not comparable' }}</td>
                        <td>{{ $row['hotel_code'] ?? 'not supplied' }}</td>
                        <td>{{ ($row['check_in'] ?? 'not supplied').' to '.($row['check_out'] ?? 'not supplied') }}</td>
                        <td>{{ $row['local_supplier_status'] ?? $row['status'] ?? $row['supplier_status'] ?? 'not supplied' }}</td>
                        <td>{{ $row['currency'] ?? 'not supplied' }}</td>
                        <td>{{ $audit['classification'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2 style="margin-top:24px">Booking-list candidates</h2>
        <table aria-label="Booking list candidates">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>ClientReference match</th>
                    <th>Hotel</th>
                    <th>Dates</th>
                    <th>Status</th>
                    <th>Currency</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($audit['candidates'] as $candidate)
                    <tr>
                        <td>{{ $candidate['reference'] ?? 'not supplied' }}</td>
                        <td>{{ $candidate['client_reference_match'] ? 'yes' : 'no' }}</td>
                        <td>{{ ($candidate['hotel_code'] ?? 'not supplied').' / '.($candidate['hotel_name'] ?? 'not supplied') }}</td>
                        <td>{{ ($candidate['check_in'] ?? 'not supplied').' to '.($candidate['check_out'] ?? 'not supplied') }}</td>
                        <td>{{ $candidate['status'] ?? 'not supplied' }}</td>
                        <td>{{ $candidate['currency'] ?? 'not supplied' }}</td>
                        <td>{{ $candidate['creation_date'] ?? 'not supplied' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">No bounded Booking List candidate was returned.</td></tr>
                @endforelse
            </tbody>
        </table>

        <form method="POST" action="{{ route('admin.bookings.reconciliation.resolve', $booking) }}" class="form">
            @csrf
            <h2>Resolve supplier reference</h2>
            <p class="summary">Only enter a reference returned by Booking List that exactly matches the identity fingerprint. The server revalidates the evidence before saving.</p>
            @error('supplier_reference') <p class="error">{{ $message }}</p> @enderror
            <label>Supplier reference
                <input name="supplier_reference" value="{{ old('supplier_reference') }}" required>
            </label>
            <label>Reason
                <textarea name="reason" rows="3" required>{{ old('reason') }}</textarea>
            </label>
            <label>
                <input type="checkbox" name="confirm" value="1" style="display:inline;width:auto"> I confirm this reference is proven by exact clientReference and identity matching.
            </label>
            <button type="submit">Resolve supplier reference</button>
        </form>
    </main>
</body>
</html>
