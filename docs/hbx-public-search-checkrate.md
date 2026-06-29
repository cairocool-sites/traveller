# Phase 12: Public HBX Search and CheckRate

Phase 12 connects the existing public hotel search and rate-check flow to the HBX Sandbox supplier while preserving the Mock Supplier fallback. It does not add HBX booking, cancellation, payment gateway, quotations, B2B, customer accounts, mobile apps, or deployment work.

## Public Search Flow

- Public search continues to use the existing `/hotels/search` route and `HotelSearchService`.
- Configured supplier order is controlled by `travel.public_search.suppliers`.
- The default order is `hbx_hotels` first, then `mock_hotels`.
- HBX is used only when the seeded supplier is active and supports search.
- If HBX times out, is rate limited, unavailable, or fails safely, the search service logs a sanitized supplier failure and continues to the Mock Supplier.
- Search input is validated before any supplier call: destination, dates, room count, adult count, child ages, locale, and currency.

HBX public searches post normalized Availability criteria to:

```text
POST https://api.test.hotelbeds.com/hotel-api/1.0/hotels
```

The initial HBX city-to-destination-code map is configurable in `config/services.php` under `services.hbx.destination_codes`. The map is intentionally small and must be replaced or extended once the full HBX destination/content contract is finalized.

## Offer Normalization

Supplier responses are normalized into the existing `SearchSession.results_snapshot` structure. The browser receives only public hotel and rate tokens. Supplier hotel codes and HBX rate keys stay in the server-side snapshot.

Each normalized rate contains:

- public rate token
- supplier room and rate references, server-side only
- room name
- board basis
- refundability
- cancellation summary
- occupancy
- supplier total
- net amount when supplied
- customer-visible total
- taxes and fees when supplied
- payment type
- `BOOKABLE` or `RECHECK` state
- short-lived rate expiry metadata
- availability timestamp

The public pages display `Bookable` for HBX `BOOKABLE` rates and `Price requires recheck` for HBX `RECHECK` rates.

## Pricing

`OfferPricingService` calculates the customer-visible selling amount from the normalized supplier total.

- Default markup is `0`.
- Optional markup is configured by `TRAVEL_PUBLIC_SEARCH_MARKUP_BASIS_POINTS`.
- Supplier totals are preserved separately for CheckRate comparison.
- Currency conversion is not performed in this phase.
- Float arithmetic is not used for public offer pricing.

## CheckRate Flow

The existing `/rate-checks` flow is reused.

- The browser submits only the search UUID, public hotel token, and public rate token.
- `RateCheckService` resolves the trusted supplier hotel code and HBX rate key from the server-side search snapshot.
- HBX CheckRate calls:

```text
POST https://api.test.hotelbeds.com/hotel-api/1.0/checkrates
```

CheckRate handles:

- `BOOKABLE` freshness validation
- `RECHECK` confirmation
- price changes
- cancellation-policy changes
- expired or unavailable rates
- sold-out responses where the supplier identifies them

Confirmed CheckRate totals are stored as customer-visible totals. Supplier totals are used only for supplier comparison.

## Booking Guard

HBX booking is explicitly blocked in Phase 12.

`BookingService` throws a `BookingFlowException` before constructing or sending an HBX booking request when the selected rate belongs to `hbx_hotels`.

No Phase 12 code calls:

```text
POST /hotel-api/1.0/bookings
DELETE /hotel-api/1.0/bookings/{reference}
```

Mock Supplier booking remains available for existing regression tests and local platform flow verification.

## Security

- HBX API key, secret, `X-Signature`, and headers are never rendered publicly.
- Public pages do not expose HBX hotel codes or rate keys.
- Supplier operation logs are sanitized through `PayloadSanitizer`.
- Raw supplier payloads are not exposed to public users.
- Search sessions are short-lived.
- Expired, tampered, or cross-session public offer references fail safely.
- HBX Sandbox remains configured with `https://api.test.hotelbeds.com`; production credentials and endpoints are not used.

## Tests

Phase 12 tests use Laravel HTTP fakes only. They do not require internet access, live HBX credentials, MySQL, production Redis, or real supplier endpoints.

Coverage includes:

- HBX Availability payload mapping
- normalized HBX search results
- customer-visible markup calculation
- `BOOKABLE` and `RECHECK` CheckRate flow
- price changes
- cancellation-policy changes
- unavailable or expired rates
- timeout and quota fallback to Mock Supplier
- invalid occupancy validation before supplier calls
- expired, tampered, and cross-session offer reference rejection
- credential redaction
- HBX booking endpoint guard

## Known Limitations

- HBX destination mapping is intentionally small and configurable.
- No HBX content synchronization is implemented.
- No supplier hotel mapping module is implemented beyond optional normalized canonical hotel IDs.
- No customer booking submission to HBX is allowed.
- No cancellation call to HBX is allowed.
- No payment gateway, quotation, B2B, account, or deployment work is included.

## Phase 13 Candidates

Future work may include controlled HBX booking submission, reconciliation, cancellation workflows, expanded destination/content mapping, production credential handling, and operational runbooks. Supplier data must continue to map to canonical internal hotel records rather than overwrite canonical hotel content automatically.
