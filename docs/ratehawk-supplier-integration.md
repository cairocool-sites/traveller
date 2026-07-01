# RateHawk Supplier Groundwork

RateHawk is modeled as a future REST supplier inside the existing supplier architecture.

## Scope Implemented

- Supplier seed record: `ratehawk_hotels`
- Encrypted credential keys:
  - `key_id`
  - `api_key`
- Safe environment placeholders in `.env.example`
- Configurable endpoint paths in `config/services.php`
- Diagnostic command:
  - `php artisan ratehawk:test-connection --diagnostic`
- REST client, configuration, credentials, normalizer, and hotel supplier adapter shell
- Search, hotel details, and prebook/check-rate foundations using normalized DTOs
- Operation logging through the existing sanitized supplier log pipeline
- Tests proving the supplier is inactive by default and no credentials are exposed

## Explicitly Not Implemented

The downloaded standalone RateHawk files included public routes, a separate `ratehawk_bookings` table, standalone booking controllers, and a webhook controller. Those were not copied directly because Traveller already has a canonical booking flow, supplier manager, operation logs, idempotency, and security model.

Not implemented yet:

- Live RateHawk booking
- Live RateHawk cancellation
- RateHawk webhook processing
- Separate RateHawk booking tables
- Customer-facing RateHawk-only routes
- Payment-card or 3DS handling
- Production RateHawk enablement

## Safety Defaults

The seeded supplier is inactive by default:

- `status`: inactive
- search/details/check-rate: disabled
- booking/cancellation/lookup: disabled
- health check: enabled for local diagnostics only

Admins must explicitly configure credentials and enable capabilities before this supplier can participate in live search.

## Credentials

Store credentials through Supplier Management:

- `key_id`
- `api_key`

The `.env` placeholders are fallback configuration only. Do not commit real values.

## Future Activation Checklist

Before enabling RateHawk in production:

- Confirm the exact active endpoint set from RateHawk documentation/account settings.
- Enable only read-only search/details/check-rate first.
- Keep booking and cancellation disabled until supplier certification and reconciliation are complete.
- Add HTTP-fake regression tests for every newly enabled operation.
- Confirm logging redacts Basic Auth and all credential-like values.
