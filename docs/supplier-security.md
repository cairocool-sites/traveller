# Supplier Security

Phase 5 establishes the security posture for supplier integrations without connecting any real provider.

## Credentials

Supplier credentials are stored in `supplier_credentials.encrypted_value` using Laravel encrypted casts. Values are hidden from model serialization and are not seeded. Secret values are replaceable in admin screens but are not revealed after save.

Never commit real supplier usernames, passwords, tokens, API keys, endpoints, database backups, logs, or payload exports.

## Logging

`SupplierOperationLogger` uses `PayloadSanitizer` before persisting request or response metadata. Sensitive keys such as authorization, tokens, API keys, passwords, credentials, card data, passport/national ID data, email, phone, and mobile are recursively redacted.

Raw diagnostics are permission-controlled. Standard users cannot create, update, or delete supplier operation logs.

## XML And SOAP

XML parsing rejects DTD and entity declarations and uses network-disabled parsing. Malformed XML returns controlled supplier exceptions. SOAP support is only scaffolded; no real WSDL or endpoint is configured.

## SSRF Controls

Normal admin users do not trigger arbitrary external URL calls in Phase 5. Future non-Mock suppliers must validate base URLs, require HTTPS for production, and block loopback, private-network, link-local, and metadata-service targets unless an explicit safe local-development override is enabled.

## Idempotency

Booking and cancellation require idempotency keys. Reusing a key with the same request returns the deterministic existing response. Reusing the same key with different request content fails explicitly.

## Correlation IDs

Every supplier operation receives a correlation ID. The ID propagates through the adapter, logs, and responses. Correlation metadata must not contain secrets.
