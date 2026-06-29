# Security Hardening

Phase 10 adds practical defaults and documents server expectations without introducing real credentials or production integrations.

## Application Configuration

- `APP_DEBUG=false` in production.
- HTTPS `APP_URL` in production.
- Secure, HTTP-only, SameSite session cookies.
- Redis-ready cache, queue, session, and lock configuration.
- MySQL 8 with strict mode.
- Private local disk for sensitive evidence and generated files.

## Headers

Application middleware sets:

- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy`
- `X-Frame-Options: SAMEORIGIN`
- CSP in report-only mode by default
- HSTS only for HTTPS production requests

Nginx may duplicate these as `always` headers after testing.

## Trusted Proxies

Configure `TRUSTED_PROXIES` for the known reverse proxy only. Do not trust arbitrary proxies. Nginx must forward `X-Forwarded-Proto` so Laravel generates secure URLs correctly.

## Logging

Daily channels exist for application, supplier, financial, and security events. Continue redacting passwords, API keys, authorization headers, payment evidence contents, card data, identity documents, and full guest PII.

## Error Handling

Localized public 404, 419, 429, 500, and 503 pages are provided. Production errors must not expose stack traces. Correlation IDs support support-team lookup without exposing internals.

## Rate Limits

Named limiters cover health, public search, booking submission, payment submission, evidence downloads, document verification, cancellation requests, and status pages. Limits are configurable through safe environment values.

## Supplier Security

No real supplier is connected. Future supplier endpoints must avoid SSRF risks, require HTTPS in production, and keep credentials encrypted in the database.

## Deferred Security Decisions

- Final CSP enforcement policy.
- WAF/CDN provider.
- Central log aggregation provider.
- Security monitoring and alert thresholds.
