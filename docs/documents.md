# Documents

Phase 8 adds printable HTML booking documents:

- Voucher
- Commercial invoice
- Payment receipt

PDF generation is intentionally deferred because no stable PDF package was required for this phase.

## Numbering

Numbers are generated with configurable prefixes:

- `VCH-YYYY-XXXXXX`
- `INV-YYYY-XXXXXX`
- `RCT-YYYY-XXXXXX`

Numbers are unique and do not expose database IDs.

## Immutable Snapshots

Issued documents store JSON snapshots. Reopening a document later uses the issued snapshot, not mutable hotel, booking, or payment relations.

Vouchers hide supplier identity, supplier internal references, net prices, credentials, and technical metadata.

Phase 9 may revoke a voucher after a successful cancellation. Historical invoice and receipt snapshots remain issued records.

Invoices are commercial invoices only. They do not claim official Egyptian e-invoice compliance. Unknown tax is stored as zero rather than invented.

Receipts are generated only after approved/paid manual payments.

## Verification

Verification routes use unguessable random tokens:

- `/verify/voucher/{token}`
- `/verify/invoice/{token}`
- `/verify/receipt/{token}`

Verification pages are rate-limited, noindexed, and expose minimal data only.

## Deferred

Official e-invoicing, QR-code packages, PDF rendering, tax authority integrations, and online payment provider receipts are deferred.
