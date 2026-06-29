# Mock Supplier

Phase 5 seeds one supplier:

- name: Mock Hotels Supplier
- code: `mock_hotels`
- integration type: `mock`
- environment: `sandbox`
- status: `active`
- credentials: none

The Mock Supplier implements search, hotel details, check rate, book, get booking, cancel, and health check.

## Destinations

Mock data covers Cairo, Giza, Alexandria, Hurghada, Sharm El Sheikh, Dubai, Makkah, and Istanbul.

## Data Coverage

Mock responses include multiple hotels, multiple room/rate options, bed-and-breakfast and half-board examples, refundable and non-refundable rates, taxes, fees, rate expiry, cancellation windows, multi-room search, child occupancy, deterministic hotel IDs, and deterministic booking references.

## Scenario Controls

Use `metadata['scenario']` or deterministic special identifiers in tests.

Supported scenarios include:

- successful search
- no availability
- timeout or delayed response
- supplier unavailable
- authentication failure
- rate limited
- rate expired
- price changed
- sold out
- successful booking
- duplicate booking request
- conflicting idempotency key reuse
- booking rejected
- uncertain booking result requiring lookup
- successful booking lookup
- booking not found
- free cancellation
- cancellation with penalty
- non-refundable cancellation rejection
- duplicate cancellation
- healthy, degraded, and unavailable health checks

Failures are deterministic. No random failures are used in automated tests.
