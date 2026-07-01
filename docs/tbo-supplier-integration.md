# TBO Hotels Supplier Integration

The platform is multi-supplier ready. HBX, Mock, and TBO suppliers must be isolated behind `HotelSupplierInterface` so public search, rate check, booking, cancellation, and supplier logs do not depend on a provider-specific API shape.

## Current Status

- Supplier code: `tbo_hotels`
- Integration type: `rest`
- Default status: inactive
- Default booking/cancellation capability: disabled
- Real credentials: not committed
- Real TBO network calls: not enabled by this foundation

This first TBO step intentionally adds only the safe supplier shell. It does not send requests to TBO, does not book, does not cancel, and does not alter HBX behavior.

## Credentials

Store TBO credentials in the supplier credentials table using encrypted values:

- `username`
- `password`

The `.env` placeholders exist only as a local fallback for controlled development:

- `TBO_USERNAME`
- `TBO_PASSWORD`
- `TBO_BASE_URL`

Do not commit real values.

## Next Implementation Steps

1. Extract the exact TBO API endpoints and payloads from `TBOHAPI_v7.pdf`.
2. Add a `TboHttpClient` with sanitized logging and no automatic retries for booking or cancellation.
3. Implement a read-only health/auth test command.
4. Implement search normalization to existing `HotelSearchResultData`.
5. Implement hotel details and CheckRate.
6. Implement booking and cancellation only after sandbox safeguards match the HBX controls.

## Safety Rules

- TBO must never fall back to HBX or Mock silently.
- TBO credentials must never be printed or stored in logs.
- Booking and cancellation stay disabled until explicit sandbox verification is complete.
- Public search should include `tbo_hotels` only after search normalization tests pass.
