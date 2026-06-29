<!doctype html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cairo Cool Travel Voucher {{ $booking->booking_reference }}</title>
    <style>
        :root {
            color: #0b1f33;
            font-family: Inter, "Segoe UI", Tahoma, Arial, sans-serif;
        }

        body {
            background: #f6f8fb;
            margin: 0;
            padding: 32px;
        }

        .sheet {
            background: #fff;
            border: 1px solid #d8e0ea;
            border-radius: 10px;
            margin: 0 auto;
            max-width: 920px;
            padding: 32px;
        }

        .brand {
            align-items: center;
            border-bottom: 2px solid #0f766e;
            display: flex;
            justify-content: space-between;
            padding-bottom: 18px;
        }

        .mark {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: .02em;
        }

        .notice {
            background: #fff7db;
            border: 1px solid #c9a227;
            border-radius: 8px;
            color: #604800;
            font-weight: 700;
            margin: 22px 0;
            padding: 14px 16px;
        }

        .notice.provisional {
            background: #eef8ff;
            border-color: #5aa5d8;
            color: #104461;
        }

        .grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 18px;
        }

        .item {
            border: 1px solid #e5ebf2;
            border-radius: 8px;
            padding: 14px;
        }

        .label {
            color: #506273;
            display: block;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .value {
            color: #0b1f33;
            font-size: 16px;
            font-weight: 700;
        }

        .footer {
            border-top: 1px solid #d8e0ea;
            color: #506273;
            font-size: 13px;
            margin-top: 26px;
            padding-top: 16px;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .sheet {
                border: 0;
                border-radius: 0;
                max-width: none;
            }
        }

        @media (max-width: 700px) {
            body {
                padding: 12px;
            }

            .sheet {
                padding: 20px;
            }

            .brand {
                align-items: flex-start;
                flex-direction: column;
                gap: 8px;
            }

            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="sheet">
        <header class="brand">
            <div>
                <div class="mark">Cairo Cool Travel</div>
                <div>Internal booking voucher</div>
            </div>
            <div>
                <span class="label">Issue date</span>
                <span class="value">{{ $issuedAt->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span>
            </div>
        </header>

        <div class="notice {{ $isProvisional ? 'provisional' : '' }}">
            @if ($isProvisional)
                Provisional manual-review notice. This is not a final supplier-confirmed voucher.
            @else
                Sandbox / Test Booking. This voucher is for HBX sandbox verification only.
            @endif
        </div>

        <section class="grid" aria-label="Booking voucher details">
            <div class="item">
                <span class="label">Local booking reference</span>
                <span class="value">{{ $booking->booking_reference }}</span>
            </div>
            <div class="item">
                <span class="label">HBX confirmation reference</span>
                <span class="value">{{ $booking->supplier_confirmation_reference ?: $booking->supplier_booking_reference ?: 'Pending manual review' }}</span>
            </div>
            <div class="item">
                <span class="label">Booking status</span>
                <span class="value">{{ $booking->status->label() }}</span>
            </div>
            <div class="item">
                <span class="label">Hotel</span>
                <span class="value">{{ data_get($booking->hotel_snapshot, 'name', 'Selected hotel') }}</span>
            </div>
            <div class="item">
                <span class="label">Room</span>
                <span class="value">{{ data_get($booking->room_snapshot, 'room_name', 'Selected room') }}</span>
            </div>
            <div class="item">
                <span class="label">Board</span>
                <span class="value">{{ data_get($booking->room_snapshot, 'board_basis', 'Not specified') }}</span>
            </div>
            <div class="item">
                <span class="label">Check-in / Check-out</span>
                <span class="value">{{ $booking->check_in->toDateString() }} to {{ $booking->check_out->toDateString() }}</span>
            </div>
            <div class="item">
                <span class="label">Guest summary</span>
                <span class="value">{{ $booking->adults_count }} adult(s){{ $booking->children_count ? ', '.$booking->children_count.' child(ren)' : '' }}</span>
            </div>
            <div class="item">
                <span class="label">Selling total</span>
                <span class="value">{{ $money->formatMinor((int) $booking->total_amount_minor, $booking->currency->code) }}</span>
            </div>
            <div class="item">
                <span class="label">Cancellation summary</span>
                <span class="value">{{ data_get($booking->room_snapshot, 'cancellation_summary', 'See booking conditions.') }}</span>
            </div>
        </section>

        <footer class="footer">
            Printable HTML fallback. This document excludes API credentials, signatures, supplier net prices, raw supplier payloads, contact details, payment receipts, and tax invoice data.
        </footer>
    </main>
</body>
</html>
