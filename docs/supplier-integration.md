# Supplier Integration Foundation

Phase 5 adds the supplier integration foundation for OTA, REST, JSON, XML, SOAP, and OTA XML providers. It includes one deterministic Mock Supplier only. No real supplier endpoint, credential, booking checkout, payment, B2B, quotation, deployment, or customer-facing search website is implemented.

## Database

- `suppliers`: supplier identity, integration type, environment, status, operation capability flags, priority, timeout, retry defaults, health timestamps, and audit users.
- `supplier_credentials`: encrypted credential values keyed per supplier. Values are hidden from serialization and are never seeded with real data.
- `supplier_operation_logs`: sanitized operation diagnostics with correlation ID, operation, request/response metadata, duration, success state, error type, and booking reference.
- `supplier_idempotency_records`: booking and cancellation idempotency records with request hash, sanitized response snapshot, status, and expiry.

## Integration Types

Modeled integration types are `mock`, `rest`, `json`, `xml`, `soap`, and `ota_xml`. Only `mock_hotels` has an adapter in Phase 5.

## Services

- `SupplierManager`: resolves adapters by supplier code, rejects inactive/disabled suppliers, checks capability flags, and lists enabled suppliers by priority.
- `SupplierOperationLogger`: writes sanitized supplier operation logs.
- `PayloadSanitizer`: recursively redacts tokens, authorization headers, passwords, card data, identity data, and contact data.
- `CorrelationIdFactory`: generates or preserves operation correlation IDs.
- `IdempotencyService`: protects booking and cancellation from duplicate or conflicting retries.
- `SupplierHealthCheckService`: runs authorized supplier health checks.
- `MockHotelSupplier`: implements the full supplier contract without external calls.

Supplier hotels may include an optional canonical hotel ID in normalized DTOs. Supplier content must not overwrite canonical hotel catalog records automatically.

## Retry Rules

Safe automatic retry candidates: search, hotel details, health check, and selected get-booking calls.

Conditional retry: check rate only when a future supplier explicitly documents safe semantics.

Never automatically retry without reconciliation: book and cancel.

## Production Extensions

Future real suppliers will require formal API documentation, sandbox credentials, production credentials, rate-limit rules, idempotency semantics, booking reconciliation rules, cancellation semantics, IP allowlists if needed, and commercial approval. Credentials must be entered through secure operational channels, never committed to source or `.env.example`.
