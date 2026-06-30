# Cairo Cool Travel Implementation Plan

## Repository Assessment

The repository at `C:\Users\harbi\Documents\traveler site` currently has no application code or project files. After Phase 0, the only intentionally created project file is this documentation file.

Observed state:

- No Laravel application files are present.
- No `composer.json`, `package.json`, `.env.example`, `artisan`, migrations, routes, controllers, tests, or frontend assets are present.
- No existing framework, version, dependency, or package constraints can be detected from the repository.
- Hidden metadata directories `.git` and `.agents` are present, but no application files were found.
- The `git` executable is not available in the current shell, so Git status could not be inspected through Git.
- `.git/config` is absent, so the visible `.git` directory does not appear to be a normal initialized Git repository in this environment.

Implication:

- This should be treated as a greenfield Laravel application build.
- Phase 1 should begin with a clean Laravel installation and explicit dependency selection.
- No existing implementation conflicts were found because no application code exists yet.

## Architecture Proposal

Cairo Cool Travel should be built as a modular Laravel hotel booking platform with a supplier abstraction layer from the beginning. The first release should support hotels only, B2C users, Arabic as the default language, EGP as the default currency, and a Mock Supplier that allows the full booking flow to be tested without external APIs.

Recommended baseline stack:

- Laravel 11 or current stable Laravel version at implementation time
- PHP 8.4.1+; PHP 8.5 is recommended for deployment
- MySQL 8
- Redis for cache, queues, sessions, locks, and rate limiting
- Laravel queues for supplier calls, booking confirmation workflows, payment workflows, notifications, and reconciliation jobs
- Filament for admin/back-office operations
- Laravel localization with Arabic default and English secondary locale
- Nginx on VPS with DirectAdmin
- Horizon or queue monitoring if compatible with the selected hosting setup
- Pest or PHPUnit for automated tests

Core application areas:

- Customer website for hotel search, details, rates, booking, payment selection, and booking status
- Admin panel for content, destinations, hotels, bookings, payments, users, suppliers, currencies, and manual operations
- Supplier layer with a stable internal contract and adapter-specific request/response mapping
- Booking engine that owns internal booking lifecycle independently from external supplier lifecycle
- Payment abstraction that supports manual payment first and later online gateways
- Localization and currency services
- Audit logging for booking, payment, admin, and supplier actions

Supplier adapter architecture:

- Define supplier contracts before real supplier integrations are built.
- Implement only a Mock Supplier in the first functional phase.
- Each supplier adapter should normalize supplier-specific API styles into one internal domain contract.
- The architecture must support REST API, XML API, SOAP API, and JSON API suppliers.
- Supplier features must include Search, Hotel Details, Check Rate, Book, Cancel, and Get Booking.

Recommended supplier contract methods:

- `search(SearchRequest $request): SearchResponse`
- `hotelDetails(HotelDetailsRequest $request): HotelDetailsResponse`
- `checkRate(CheckRateRequest $request): CheckRateResponse`
- `book(BookRequest $request): BookResponse`
- `cancel(CancelRequest $request): CancelResponse`
- `getBooking(GetBookingRequest $request): GetBookingResponse`

Supplier design principles:

- Never expose supplier payloads directly to frontend or admin views.
- Store normalized booking records internally.
- Store raw supplier request/response logs separately for diagnostics and reconciliation.
- Use idempotency keys for booking and payment-sensitive operations.
- Treat supplier prices as volatile until Check Rate succeeds.
- Keep supplier credentials encrypted and managed through configuration, never committed to the repository.

High-level modules:

- Identity and access
- Localization
- Currency and exchange rates
- Destination and hotel content
- Hotel search
- Rate checking
- Booking lifecycle
- Payment lifecycle
- Supplier abstraction
- Admin operations
- Notifications
- Audit and logs
- API layer for future B2B/B2B2C expansion

## Database Proposal

Main entities and relationships:

- `users`
  - Customers, admins, and future agents can share the base user model with roles/permissions.

- `roles`, `permissions`, `model_has_roles`
  - Recommended through Spatie Laravel Permission or Filament-compatible authorization.

- `customers`
  - Extends customer profile data for B2C users.
  - Belongs to `users`.

- `countries`
  - Supports Egypt first, with Saudi Arabia and UAE later.

- `cities`
  - Belongs to `countries`.
  - Used for hotel destinations and search.

- `areas`
  - Optional city subdivision.
  - Belongs to `cities`.

- `hotels`
  - Canonical internal hotel record.
  - Belongs to `cities`.
  - Has many translations, images, facilities, supplier mappings, and bookings.

- `hotel_translations`
  - Arabic and English hotel names, descriptions, policies, and address text.
  - Belongs to `hotels`.

- `hotel_images`
  - Belongs to `hotels`.

- `facilities`
  - Canonical facility list.

- `facility_translations`
  - Arabic and English facility labels.

- `facility_hotel`
  - Many-to-many relation between hotels and facilities.

- `suppliers`
  - Stores supplier identity, status, type, priority, supported markets, supported currencies, and configuration references.

- `supplier_hotel_mappings`
  - Maps internal hotels to supplier hotel IDs.
  - Belongs to `hotels` and `suppliers`.

- `search_sessions`
  - Stores normalized search criteria, locale, currency, user/session reference, and expiry.

- `search_results`
  - Optional cached normalized supplier results linked to a search session.

- `rate_checks`
  - Stores selected room/rate, supplier, price, cancellation policy, expiry, and validation status.

- `bookings`
  - Internal booking record.
  - Belongs to customer/user, hotel, supplier, selected rate check, currency, and payment status.
  - Should include booking reference, supplier reference, status, check-in, check-out, occupancy, totals, locale, source channel, and cancellation status.

- `booking_guests`
  - Guest names, age types, child ages, nationality, and lead guest flag.
  - Belongs to `bookings`.

- `booking_rooms`
  - Room-level booking data, normalized room name, board basis, occupancy, supplier room reference, and price allocation.
  - Belongs to `bookings`.

- `booking_status_histories`
  - Tracks booking lifecycle changes.
  - Belongs to `bookings`.

- `payments`
  - Manual payment and future online payment records.
  - Belongs to `bookings`.

- `payment_transactions`
  - Gateway transaction attempts, manual approval records, refunds, and reconciliation data.
  - Belongs to `payments`.

- `currencies`
  - EGP, USD, EUR, SAR, AED, GBP.

- `exchange_rates`
  - Stores conversion rates with source, effective date, and status.

- `manual_payment_methods`
  - Bank transfer, cash, wallet, office payment, or other manual methods.

- `supplier_operation_logs`
  - Records supplier action type, request metadata, response metadata, latency, status, errors, and correlation IDs.
  - Sensitive payloads must be redacted or encrypted.

- `audit_logs`
  - Admin and system actions affecting bookings, payments, suppliers, or configuration.

- `notifications`
  - Laravel notification table for booking, payment, and admin alerts.

Future B2B/B2B2C entities:

- `agencies`
- `agency_users`
- `agency_credit_limits`
- `agency_markups`
- `agency_commissions`
- `agency_wallets`
- `agency_booking_policies`
- `channels`

Booking status model:

- `draft`
- `pending_rate_check`
- `rate_confirmed`
- `pending_payment`
- `payment_submitted`
- `pending_manual_confirmation`
- `confirmed`
- `failed`
- `cancel_requested`
- `cancelled`
- `refunded`

Payment status model:

- `not_required`
- `pending`
- `submitted`
- `under_review`
- `approved`
- `rejected`
- `paid`
- `failed`
- `refunded`
- `partially_refunded`

## Security Risks

Primary risks:

- Supplier credentials may be leaked if stored in code, logs, or unencrypted database fields.
- Booking endpoints are vulnerable to duplicate submissions without idempotency keys and locking.
- Hotel prices can change between search and booking if Check Rate is skipped or not enforced.
- Manual payment confirmation can be abused without strong admin permissions, audit logs, and maker/checker workflows.
- Admin panel exposure is high risk on a public VPS.
- Personally identifiable information for guests must be protected.
- Raw supplier XML, SOAP, REST, or JSON logs may contain sensitive data.
- Currency conversion can produce financial disputes if rate source, rounding, and effective date are not stored.
- Localization can create inconsistent legal, cancellation, or payment text if Arabic and English content drift.
- Future B2B credit booking can create financial exposure without credit limits and approval rules.

Recommended controls:

- Use HTTPS only.
- Use encrypted environment variables and never commit real API keys.
- Encrypt sensitive supplier configuration values.
- Redact or encrypt supplier request/response logs.
- Use role-based access control for admin operations.
- Require strong admin passwords and two-factor authentication where possible.
- Use queue locks and idempotency keys for booking and payment operations.
- Store immutable price snapshots after Check Rate.
- Store cancellation policy snapshots at booking time.
- Use audit logs for all booking, payment, supplier, and admin changes.
- Apply rate limiting to search, booking, login, and future API endpoints.
- Validate all guest, date, occupancy, locale, and currency inputs.
- Separate customer-facing routes from admin routes.
- Add database backups and tested restore procedures before production launch.

## Implementation Phases

### Phase 0: Analysis and Planning

Scope:

- Inspect the repository.
- Identify current state, risks, missing requirements, and implementation path.
- Create `docs/implementation-plan.md`.

Definition of Done:

- Repository state is documented.
- Architecture proposal is documented.
- Database proposal is documented.
- Risks and missing requirements are documented.
- Implementation phases and first tasks are documented.
- No application code, supplier adapters, deployment, or real integrations are created.

### Phase 1: Laravel Foundation

Scope:

- Install Laravel.
- Configure MySQL, Redis, queues, timezone, localization defaults, and environment files.
- Add testing baseline.
- Add code style tooling.

Definition of Done:

- Laravel app boots locally.
- `.env.example` documents required configuration without secrets.
- Arabic is default locale, English is available.
- Timezone is `Africa/Cairo`.
- Queue connection and Redis configuration are present.
- Database connection targets MySQL 8.
- Base tests pass.

### Phase 2: Admin Foundation

Scope:

- Install and configure Filament.
- Add admin authentication, roles, permissions, and protected admin routes.
- Create admin user workflow.

Definition of Done:

- Filament admin panel is installed and protected.
- Roles and permissions exist for super admin and operations admin.
- Admin access is tested.
- No public admin registration is exposed.

### Phase 3: Core Reference Data

Scope:

- Add countries, cities, currencies, exchange rates, facilities, and localization tables.
- Seed Egypt, Cairo, default currencies, and base hotel facilities.

Definition of Done:

- Reference data migrations exist.
- Seeders create default supported locales, currencies, and Egypt market data.
- Admin can manage core reference data.
- Tests verify default locale, timezone, and currency assumptions.

### Phase 4: Hotel Content Model

Scope:

- Add hotels, hotel translations, images, facilities, and supplier hotel mappings.
- Add admin hotel management.

Definition of Done:

- Hotels can be created and translated in Arabic and English.
- Hotel images and facilities can be managed.
- Supplier mapping records can be stored without real supplier calls.
- Tests cover hotel creation and translation retrieval.

### Phase 5: Supplier Contract Design and Mock Supplier

Scope:

- Define supplier contracts, DTOs, enums, exceptions, and normalized responses.
- Implement Mock Supplier only.
- Add supplier operation logging.

Definition of Done:

- Supplier contract supports Search, Hotel Details, Check Rate, Book, Cancel, and Get Booking.
- Mock Supplier returns deterministic test data.
- No real supplier integration exists.
- Supplier logs are stored with safe redaction.
- Contract tests cover the Mock Supplier flow.

### Phase 6: Search and Hotel Details Flow

Scope:

- Build customer-facing hotel search and hotel details pages.
- Use Mock Supplier via the supplier abstraction.
- Support Arabic default UI and English secondary UI.
- Support EGP default currency and configured secondary currencies.

Definition of Done:

- Users can search hotels by destination, date, rooms, guests, nationality, locale, and currency.
- Search results are normalized and displayed.
- Hotel details can be viewed.
- Search and details work with Mock Supplier only.
- Feature tests cover successful and empty searches.

### Phase 7: Rate Check and Booking Flow

Scope:

- Add Check Rate before booking.
- Create booking draft and guest capture flow.
- Add booking lifecycle transitions.

Definition of Done:

- Selected rates must be checked before booking.
- Booking stores price and cancellation snapshots.
- Duplicate booking submissions are prevented.
- Booking statuses are recorded in history.
- Tests cover successful booking, expired rate, duplicate submission, and failed supplier response.

### Phase 8: Manual Payment and Manual Confirmation

Scope:

- Add manual payment methods.
- Add payment submission workflow.
- Add admin review and manual booking confirmation.

Definition of Done:

- Customer can select manual payment.
- Customer can submit manual payment details.
- Admin can approve, reject, or request review.
- Admin can manually confirm booking.
- All payment and confirmation changes are audited.

### Phase 9: Notifications and Documents

Scope:

- Add customer and admin notifications.
- Add booking confirmation email.
- Add voucher or confirmation document generation if required.

Definition of Done:

- Customers receive booking status notifications.
- Admins receive pending payment and pending confirmation alerts.
- Notification templates support Arabic and English.
- Tests verify notification dispatch.

### Phase 10: Production Hardening

Scope:

- Add logging, monitoring, backups, queue supervision, cache strategy, security headers, and deployment checklist.

Definition of Done:

- Production deployment checklist exists for DirectAdmin and Nginx.
- Queue workers are documented.
- Backup and restore process is documented.
- Error logging and alerting are configured.
- Security checklist is complete.

### Phase 11: Future API and B2B Readiness

Scope:

- Add REST API foundation.
- Prepare authentication, rate limits, channel concepts, agency model, markups, commissions, and credit controls.

Definition of Done:

- API architecture is documented and partially scaffolded only when requested.
- B2B entities are designed before implementation.
- B2C flows remain unaffected.

### Phase 12: Real Supplier Integration Readiness

Scope:

- Select one real supplier.
- Build adapter using existing contract.
- Add sandbox credentials through secure environment configuration.

Definition of Done:

- Real supplier implementation is isolated behind supplier contract.
- Mock Supplier still works.
- Contract tests compare normalized behavior.
- No credentials are committed.

## First 10 Tasks

1. Create a fresh Laravel project in the repository.
2. Configure `.env.example` for MySQL 8, Redis, queues, Arabic default locale, EGP default currency, and `Africa/Cairo` timezone.
3. Add baseline testing setup with Pest or PHPUnit.
4. Install and configure Filament.
5. Install and configure roles and permissions.
6. Create base enums for locales, currencies, booking statuses, payment statuses, supplier operations, and supplier status.
7. Create migrations for countries, cities, currencies, exchange rates, suppliers, and supplier operation logs.
8. Create migrations for hotels, hotel translations, hotel images, facilities, and supplier hotel mappings.
9. Define supplier contract interfaces and DTO namespaces without implementing real suppliers.
10. Implement the Mock Supplier in a later phase after the contract is approved.

## Proposed Files

Only this file should exist after Phase 0:

- `docs/implementation-plan.md`

Proposed future structure once Phase 1 begins:

```text
app/
  Actions/
    Booking/
    Payment/
    Supplier/
  DTO/
    Booking/
    Hotel/
    Supplier/
  Enums/
  Filament/
    Resources/
    Pages/
    Widgets/
  Http/
    Controllers/
      Web/
      Api/
  Models/
  Services/
    Currency/
    Localization/
    Booking/
    Payment/
    Supplier/
      Contracts/
      DTO/
      Exceptions/
      Mock/
      Normalizers/
  Support/
    Money/
    Dates/
    Idempotency/
database/
  migrations/
  seeders/
lang/
  ar/
  en/
resources/
  views/
    web/
    emails/
routes/
  web.php
  api.php
  admin.php
tests/
  Feature/
  Unit/
docs/
  implementation-plan.md
  deployment-directadmin-nginx.md
  supplier-contract.md
```

Notes:

- `docs/deployment-directadmin-nginx.md` and `docs/supplier-contract.md` are proposed future files only.
- They should not be created in Phase 0.

## Technical Risks, Conflicts, and Missing Requirements

Technical risks:

- DirectAdmin VPS environments often need careful queue worker, Redis, scheduler, and permission setup.
- Some shared or semi-managed VPS setups do not provide reliable long-running process supervision.
- Supplier response formats will vary widely across REST, XML, SOAP, and JSON APIs.
- Supplier rate validity can be very short, making stale search prices likely.
- Hotel deduplication across suppliers can become complex.
- Multi-currency display and settlement rules are not yet defined.
- Manual confirmation creates operational risk if admin workflow is not strict.
- Future B2B/B2B2C requirements can affect pricing, credit, invoice, and user model design.

Potential conflicts:

- Default Arabic UI requires right-to-left frontend support from the start.
- B2C simplicity may conflict with future B2B pricing, agency, and credit requirements if not modeled carefully.
- Mock Supplier should be deterministic for testing but realistic enough to exercise edge cases.
- Filament admin structure should not leak supplier-specific concepts into core booking logic.

Missing requirements to decide before later phases:

- Exact Laravel version to install at Phase 1.
- Frontend approach: Blade, Livewire, Inertia, or separate SPA.
- Authentication requirements for customers.
- Guest checkout support.
- Required manual payment methods in Egypt.
- Invoice, tax, and e-receipt requirements.
- Cancellation policy display and approval rules.
- Markup rules for B2C and future B2B.
- Exchange rate source and update frequency.
- SMS, WhatsApp, or email notification provider.
- Whether voucher PDF generation is required for Phase 1 launch.
- Admin two-factor authentication requirement.
- Backup provider and retention policy.

## Strictly Blocking Questions

None for Phase 0.

The repository is empty, and the planning document can stand without further input. Future implementation phases will need business decisions around payment methods, frontend approach, tax/invoicing, notification providers, and operational workflows, but those are not blocking this analysis phase.
