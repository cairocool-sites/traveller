# Hotel Search Foundation

Phase 6 adds the public customer-facing hotel search and hotel-details flow. It uses Blade, Livewire, Tailwind CSS, Vite, reference destinations, canonical hotel content, and the existing Mock Supplier through the supplier abstraction.

No real supplier integration, booking, Check Rate/pre-booking, guest checkout, payment, quotation, cancellation workflow, customer accounts, agency/B2B, or deployment work is implemented.

## Public Routes

- `/`: homepage with the primary hotel search form.
- `/hotels`: hotel search page.
- `/hotels/search`: executes a validated search or filters an existing search session.
- `/hotels/{hotel}`: displays either an active published canonical hotel by slug or a public result token with a `search` UUID query parameter.

Supplier hotel identifiers, supplier codes, supplier rate keys, and net prices are not exposed in public URLs or views.

## Search Criteria

Search supports destination, check-in, check-out, rooms, adults per room, children per room, child ages, nationality/residence placeholders, currency, and locale.

Configurable limits live in `config/travel.php` under `public_search`: maximum rooms, adults, children, child age, stay length, search-session lifetime, public supplier allowlist, and result limit.

## Destination Lookup

Destination lookup uses active countries, cities, and areas from Phase 3 reference data. Arabic and English names are searched. Results are typed as country, city, or area and converted into validated destination tokens such as `city:1`.

For the Mock Supplier, the English city/destination name is passed as the deterministic supplier destination identifier. No supplier destination mapping table is created in this phase.

## Supplier Orchestration

`HotelSearchService` validates criteria, resolves the destination, generates a correlation ID, asks `SupplierManager` for enabled search-capable suppliers, and calls the Mock Supplier through `HotelSupplierInterface`.

Failures are converted into safe customer warnings. Raw supplier payloads, endpoints, credentials, stack traces, and internal exception names are not shown publicly.

## Search Sessions

`search_sessions` stores minimal anonymous search state: non-sequential public UUID, destination, dates, occupancy, optional nationality/residence country, currency, locale, hashed anonymous session identifier, nullable future user ID, correlation ID, criteria snapshot, normalized results snapshot, non-sensitive warnings, and expiry timestamp.

Default retention is 30 minutes. Expired sessions cannot be used for hotel-detail result tokens.

## Results And Filters

Results display hotel name, safe placeholder image, star rating, location, facilities, lowest customer-visible amount, currency, taxes/fees state, board basis, refundability, cancellation summary, and a View hotel action.

Filters operate on normalized stored result data for name, star rating, refundability, board basis, area, and sorting. Sorting supports recommended, price low-to-high, price high-to-low, and star rating.

## Hotel Details

Details prefer canonical hotel content when a canonical hotel ID is available in the normalized supplier result. Supplier content is used only as an explicit fallback. Customer search never creates or overwrites canonical hotel records.

The rate action is disabled and states that booking will be enabled in the next phase.

## Price Presentation

The public layer displays supplier-provided customer-visible Mock amounts using the `Money` value object and active currency decimal places. It does not use floats for financial calculations, does not apply markup, and does not silently convert currencies.

If future conversion is needed, missing exchange rates must fail explicitly through the currency service.

## Cancellation Display

Cancellation summaries are derived from normalized cancellation policies: free cancellation before a proven date, non-refundable, cancellation penalty may apply, or unavailable policy.

The UI does not claim free cancellation unless the normalized policy proves it.

## Localization And SEO

Arabic is the default public locale and renders RTL. English renders LTR. Pages include title, meta description, canonical URL, Open Graph basics, breadcrumbs, and semantic heading structure.

Volatile search-result URLs are not intended as thin indexed destination pages.

## Admin Diagnostics

Phase 6 adds `view_search_sessions`. Search-session diagnostics are read-only in Filament and do not expose supplier credentials.

## Testing

```bash
composer validate --strict
php artisan test
vendor/bin/pint --test
npm run build
php artisan route:list
php artisan route:list --path=admin
```

## Known Limitations

- Mock Supplier only.
- No real supplier mappings.
- No booking, Check Rate/pre-booking, payment, guest capture, customer account, quotation, cancellation workflow, agency, B2B, or deployment.
- Placeholder hotel imagery only.
- No map provider integration.
- No public destination landing-page expansion.
