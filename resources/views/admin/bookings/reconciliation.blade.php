<!doctype html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking reconciliation {{ $booking->booking_reference }}</title>
    <style>
        body { background: #f6f8fb; color: #0b1f33; font-family: Inter, "Segoe UI", Tahoma, Arial, sans-serif; margin: 0; padding: 32px; }
        main { background: #fff; border: 1px solid #d8e0ea; border-radius: 10px; margin: 0 auto; max-width: 980px; padding: 28px; }
        h1 { margin: 0 0 8px; }
        .summary { color: #506273; font-weight: 700; margin-bottom: 24px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid #e5ebf2; padding: 12px; text-align: left; vertical-align: top; }
        th { color: #506273; font-size: 12px; text-transform: uppercase; }
        .badge { border-radius: 999px; display: inline-block; font-size: 12px; font-weight: 800; padding: 5px 10px; }
        .matched { background: #dff7ee; color: #075c45; }
        .mismatched, .manual_review { background: #fff1d1; color: #7a4d00; }
        .missing_local, .missing_supplier, .not_comparable { background: #eef2f6; color: #334155; }
    </style>
</head>
<body>
    <main>
        <h1>Booking reconciliation</h1>
        <p class="summary">
            {{ $booking->booking_reference }} · Supplier {{ $booking->supplier_booking_reference ?: 'not supplied' }} · Result {{ $evidence->summary_status }}
        </p>

        <table aria-label="Booking reconciliation field results">
            <thead>
                <tr>
                    <th>Field</th>
                    <th>Classification</th>
                    <th>Local present</th>
                    <th>Supplier present</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($evidence->field_results as $field => $result)
                    <tr>
                        <td>{{ str_replace('_', ' ', $field) }}</td>
                        <td><span class="badge {{ $result['classification'] }}">{{ $result['classification'] }}</span></td>
                        <td>{{ $result['local_present'] ? 'yes' : 'no' }}</td>
                        <td>{{ $result['supplier_present'] ? 'yes' : 'no' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </main>
</body>
</html>
