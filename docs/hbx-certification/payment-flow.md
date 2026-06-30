# Payment Flow Evidence

Sandbox manual payment remains separate from supplier booking status.

Example valid state:

```text
supplier_status = confirmed
payment_status = pending
```

The public booking and payment pages display localized labels instead of raw enum values. A validation failure on the payment form does not change supplier status.

Payment references are required only for manual payment methods configured with `requires_reference = true`. The page displays a Sandbox notice that no real payment is collected.

The amount shown to customers is the final local selling total stored on the booking. Supplier net amounts are not exposed publicly. The same final customer total is shown on the confirmation page, payment page, and internal voucher.

The HBX Content Hotels API blocker remains separate. Content certification must not be marked complete until real hotel content is successfully retrievable and stored locally.
