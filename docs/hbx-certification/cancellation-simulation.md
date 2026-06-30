# Cancellation Simulation

Phase 14 supports HBX cancellation simulation for certification evidence only.

The request uses:

```text
DELETE /hotel-api/1.0/bookings/{bookingReference}?cancellationFlag=SIMULATION
```

No real cancellation is sent by the certification evidence command. No public UI action defaults to real HBX cancellation.

The simulation audit stores:

- operation type `cancellation_simulation`
- local reference
- supplier reference
- simulation status
- cancellation amount when supplied
- currency
- refundable classification
- policy/deadline presence
- timestamp
- sanitized result category

After simulation, the command retrieves Booking Detail again and confirms whether the supplier booking remains confirmed.
