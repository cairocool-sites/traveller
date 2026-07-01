# Static Analysis Baseline

Larastan was introduced at level 5 as a CI guard for new PHP type-safety issues.

The first full project scan found a large set of pre-existing issues across commands, Eloquent model casts, Filament resources, tests, and supplier integration code. To avoid blocking unrelated delivery all at once, those findings were captured in `phpstan-baseline.neon`.

## Current Policy

- CI runs `vendor/bin/phpstan analyse --memory-limit=512M`.
- Existing findings are suppressed only through `phpstan-baseline.neon`.
- New findings outside the baseline should fail CI.
- The baseline should be reduced gradually as areas are touched.

## Priority Areas To Burn Down

- `app/Services/Supplier`
- `app/Services/Hbx` and `app/Services/Supplier/Hbx`
- HBX console commands
- booking, payment, cancellation, and document services
- tests that rely on loosely typed snapshots

## Notes

Many findings are caused by Laravel model casts and dynamic Eloquent properties being inferred as strings or generic models. These should be fixed with typed accessors, explicit model return types, collection generics, or smaller DTO boundaries when the relevant module is actively changed.
