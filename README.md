# Cairo Cool Travel

Cairo Cool Travel is a Laravel hotel booking platform planned for Egypt first, with future expansion to Saudi Arabia and the UAE.

This repository is currently in Phase 14: HBX Sandbox Verification, voucher readiness, supplier-independent public-site hardening, and production deployment compatibility hardening.

## Runtime Requirement

This project requires PHP 8.4.1 or newer because the locked Symfony 8.1 dependencies require PHP `>=8.4.1`. PHP 8.5 is recommended for deployment. Do not use Composer `--ignore-platform-reqs`.

## Current Scope

- Laravel foundation installed in the repository root.
- MySQL 8 configuration baseline.
- Redis configuration baseline for cache, queues, and locks, with local fallbacks.
- Arabic as the default locale.
- English as the fallback locale.
- `Africa/Cairo` as the application timezone.
- USD as the default payable business currency, with EGP shown as an approximate local display estimate when an active USD to EGP exchange rate exists.
- Supported currencies configured: EGP, USD, EUR, SAR, AED, GBP.
- Pest test foundation.
- Laravel Pint formatting.
- Standard Laravel Vite frontend foundation.
- Filament admin panel at `/admin`.
- Spatie Laravel Permission roles and permissions foundation.
- Admin user management with active/inactive status and preferred locale.
- Read-only roles and permissions matrix.
- Core reference data for countries, cities, areas, currencies, exchange rates, and facilities.
- Manual exchange-rate management foundation and decimal-safe conversion service.
- Bilingual Arabic/English facility translations.
- Canonical internal hotel catalog foundation.
- Hotel translations, facilities, contacts, image metadata, and hotel policies.
- Protected Filament hotel content management.
- Supplier integration foundation for mock, REST, JSON, XML, SOAP, and OTA XML provider types.
- Secure supplier credentials, sanitized operation logs, correlation IDs, idempotency records, transport abstractions, and deterministic Mock Supplier.
- Public Blade/Livewire hotel search and hotel-details foundation using HBX Sandbox search when enabled, with Mock Supplier fallback.
- Secure expiring search sessions with non-sequential public UUIDs and public result tokens.
- Public Check Rate, guest details, internal booking creation, Mock Supplier booking confirmation, and booking reconciliation foundations.
- Manual payment methods, customer payment submission, private evidence storage, admin payment review, printable vouchers, commercial invoices, receipts, and document verification tokens.
- Customer cancellation requests, cancellation-policy evaluation, Mock Supplier cancellation, cancellation status history, manual refund tracking, refund histories, and customer-safe cancellation/refund status pages.
- Production-readiness checks, safe health endpoints, scheduler heartbeat, named rate limiters, security headers, operational cleanup, DirectAdmin/Nginx deployment documentation, backup/restore documentation, and CI workflow.
- HBX Hotels Sandbox adapter foundation behind the supplier contract, with authentication/signature generation, safe sandbox health checks, availability, CheckRate, booking, lookup, cancellation normalization, and fake-HTTP automated tests.
- Public HBX Availability, CheckRate, and sandbox booking flow using short-lived server-side offer snapshots, public rate tokens, sanitized logging, and explicit guards that block production HBX booking submission.
- Internal voucher readiness, supplier identity forensic audit tooling, and public trust/policy pages.

## Out of Scope

The following are intentionally not implemented yet:

- Production supplier adapters or integrations
- Supplier hotel mappings
- Room types, room inventory, or rate plans
- Production supplier booking integrations
- Real online payment gateways
- Payment webhooks
- Real payment-gateway refunds
- Chargebacks
- Booking PDFs
- Quotations
- Customer accounts
- B2B or B2B2C features
- Real API credentials
- Deployment automation
- Real production deployment

## Admin Documentation

See [docs/admin-foundation.md](docs/admin-foundation.md) for admin setup, roles, permissions, and first super admin instructions.

See [docs/core-reference-data.md](docs/core-reference-data.md) for reference-data entities, seeders, permissions, and currency behavior.

See [docs/hotel-catalog.md](docs/hotel-catalog.md) for hotel catalog entities, publication rules, permissions, and media metadata behavior.

See [docs/supplier-integration.md](docs/supplier-integration.md), [docs/supplier-contract.md](docs/supplier-contract.md), [docs/mock-supplier.md](docs/mock-supplier.md), and [docs/supplier-security.md](docs/supplier-security.md) for the Phase 5 supplier foundation.

See [docs/hotel-search.md](docs/hotel-search.md) for the Phase 6 public search and hotel details foundation.

See [docs/booking-flow.md](docs/booking-flow.md) for the Phase 7 Check Rate, guest details, and booking creation foundation.

See [docs/manual-payments.md](docs/manual-payments.md) and [docs/documents.md](docs/documents.md) for the Phase 8 manual payment and document foundation.

See [docs/cancellations.md](docs/cancellations.md) and [docs/refunds.md](docs/refunds.md) for the Phase 9 cancellation and manual refund foundation.

See [docs/deployment-directadmin-nginx.md](docs/deployment-directadmin-nginx.md), [docs/operations-runbook.md](docs/operations-runbook.md), [docs/backup-restore.md](docs/backup-restore.md), [docs/security-hardening.md](docs/security-hardening.md), and [docs/data-retention.md](docs/data-retention.md) for Phase 10 operations readiness.

See [docs/deployment/directadmin-staging.md](docs/deployment/directadmin-staging.md) and [docs/deployment/mysql-verification.md](docs/deployment/mysql-verification.md) for DirectAdmin, PHP 8.5, MySQL, and staging deployment verification.

See [docs/hbx-sandbox-integration.md](docs/hbx-sandbox-integration.md) for the Phase 11 HBX Hotels Sandbox supplier integration.

See [docs/hbx-public-search-checkrate.md](docs/hbx-public-search-checkrate.md) for the Phase 12 public HBX search and CheckRate integration.

See [docs/hbx-sandbox-booking.md](docs/hbx-sandbox-booking.md) for the Phase 13 HBX Sandbox booking completion.

See [docs/hbx-sandbox-verification-voucher.md](docs/hbx-sandbox-verification-voucher.md), [docs/hbx-certification/external-blockers.md](docs/hbx-certification/external-blockers.md), and [docs/public-site-readiness.md](docs/public-site-readiness.md) for Phase 14 verification, external blocker, voucher, and public readiness notes.

## Local Documentation

See [docs/local-development.md](docs/local-development.md) for local setup notes.

See [docs/implementation-plan.md](docs/implementation-plan.md) for the phased project plan.
