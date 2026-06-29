<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\BookingSupplierStatus;
use App\Enums\GuestType;
use App\Enums\PaymentStatus;
use App\Enums\SupplierOperation;
use App\Models\Booking;
use App\Models\RateCheck;
use App\Notifications\BookingStatusNotification;
use App\Services\Supplier\Data\GuestData;
use App\Services\Supplier\Data\SupplierBookingRequestData;
use App\Services\Supplier\SupplierManager;
use App\Support\Money\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class BookingService
{
    public function __construct(
        private readonly SupplierManager $suppliers,
        private readonly BookingReferenceGenerator $references,
        private readonly BookingStateMachine $states,
        private readonly HbxSandboxBookingGuard $hbxGuard,
    ) {}

    public function createAndSubmit(RateCheck $rateCheck, array $payload): Booking
    {
        $this->validateRate($rateCheck, (bool) ($payload['accept_price_change'] ?? false));
        $this->hbxGuard->assertAllowed($rateCheck->supplier);

        $idempotencyKey = (string) ($payload['idempotency_key'] ?? '');
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('A booking idempotency key is required.');
        }

        $hash = hash('sha256', json_encode(Arr::except($payload, ['idempotency_key']), JSON_THROW_ON_ERROR));

        if ($existing = Booking::query()->where('idempotency_key', $idempotencyKey)->first()) {
            if (! hash_equals($existing->idempotency_payload_hash, $hash)) {
                throw new InvalidArgumentException('Idempotency key was already used with different booking details.');
            }

            return $existing;
        }

        return Cache::lock('booking:'.$idempotencyKey, 10)->block(5, fn (): Booking => DB::transaction(function () use ($rateCheck, $payload, $idempotencyKey, $hash): Booking {
            if ($existing = Booking::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first()) {
                if (! hash_equals($existing->idempotency_payload_hash, $hash)) {
                    throw new InvalidArgumentException('Idempotency key was already used with different booking details.');
                }

                return $existing;
            }

            $room = $rateCheck->room_snapshot;
            $occupancy = $rateCheck->occupancy_snapshot;
            $guests = $this->guestData($payload['guests'] ?? []);
            $leadGuest = collect($guests)->first(fn (GuestData $guest): bool => $guest->isLead);
            $expectedAdults = collect($occupancy)->sum('adults');
            $expectedChildren = collect($occupancy)->sum('children');
            $expectedChildAges = collect($occupancy)->flatMap(fn (array $room): array => $room['child_ages'] ?? [])->map(fn ($age): int => (int) $age)->sort()->values()->all();
            $actualChildAges = collect($guests)->filter(fn (GuestData $guest): bool => $guest->type === GuestType::Child)->map(fn (GuestData $guest): ?int => $guest->age)->sort()->values()->all();

            if (! $leadGuest) {
                throw new InvalidArgumentException('A lead adult guest is required.');
            }

            if (collect($guests)->filter(fn (GuestData $guest): bool => $guest->type === GuestType::Adult)->count() !== $expectedAdults || collect($guests)->filter(fn (GuestData $guest): bool => $guest->type === GuestType::Child)->count() !== $expectedChildren) {
                throw new InvalidArgumentException('Guest details must match the checked occupancy.');
            }

            if ($actualChildAges !== $expectedChildAges) {
                throw new InvalidArgumentException('Child ages must match the checked occupancy.');
            }

            $booking = Booking::query()->create([
                'public_uuid' => (string) Str::uuid(),
                'booking_reference' => $this->references->make(),
                'user_id' => auth()->id(),
                'search_session_id' => $rateCheck->search_session_id,
                'rate_check_id' => $rateCheck->id,
                'supplier_id' => $rateCheck->supplier_id,
                'hotel_id' => $rateCheck->hotel_id,
                'currency_id' => $rateCheck->currency_id,
                'status' => BookingStatus::Draft,
                'payment_status' => PaymentStatus::NotRequired,
                'locale' => $rateCheck->searchSession->locale,
                'check_in' => $rateCheck->searchSession->check_in,
                'check_out' => $rateCheck->searchSession->check_out,
                'rooms_count' => count($occupancy),
                'adults_count' => $expectedAdults,
                'children_count' => $expectedChildren,
                'total_amount_minor' => $rateCheck->checked_amount_minor ?? $rateCheck->original_amount_minor,
                'taxes_amount_minor' => Arr::get($room, 'tax.minor_amount'),
                'fees_amount_minor' => Arr::get($room, 'fee.minor_amount'),
                'cancellation_policy_snapshot' => $rateCheck->cancellation_policy_snapshot,
                'hotel_snapshot' => [
                    'name' => $rateCheck->searchSession->results_snapshot[0]['name'] ?? null,
                    'canonical_hotel_id' => $rateCheck->hotel_id,
                ],
                'room_snapshot' => $room,
                'occupancy_snapshot' => $occupancy,
                'correlation_id' => $rateCheck->correlation_id,
                'idempotency_key' => $idempotencyKey,
                'idempotency_payload_hash' => $hash,
                'contact_email' => Str::lower(trim((string) ($payload['contact_email'] ?? ''))),
                'contact_phone' => trim((string) ($payload['contact_phone'] ?? '')),
                'customer_nationality' => Str::upper((string) ($payload['customer_nationality'] ?? '')),
                'special_requests' => $this->cleanText($payload['special_requests'] ?? null, 1000),
                'expires_at' => now()->addMinutes(config('travel.booking.draft_lifetime_minutes')),
            ]);

            $this->states->transition($booking, BookingStatus::RateConfirmed, 'Rate check accepted.');

            $bookingRoom = $booking->rooms()->create([
                'room_index' => 1,
                'room_name' => $room['room_name'],
                'board_basis' => $room['board_basis'] ?? null,
                'adults' => collect($occupancy)->sum('adults'),
                'children' => collect($occupancy)->sum('children'),
                'child_ages' => collect($occupancy)->flatMap(fn (array $room): array => $room['child_ages'] ?? [])->values()->all(),
                'amount_minor' => $booking->total_amount_minor,
                'cancellation_policy_snapshot' => $rateCheck->cancellation_policy_snapshot,
                'supplier_room_reference' => $rateCheck->supplier_room_reference,
            ]);

            foreach ($payload['guests'] as $index => $guest) {
                $booking->guests()->create([
                    'booking_room_id' => $bookingRoom->id,
                    'type' => $guest['type'],
                    'title' => $guest['title'] ?? null,
                    'first_name' => $this->cleanName($guest['first_name']),
                    'last_name' => $this->cleanName($guest['last_name']),
                    'age' => $guest['age'] ?? null,
                    'is_lead_guest' => (bool) ($guest['is_lead_guest'] ?? false),
                    'sort_order' => $index + 1,
                ]);
            }

            $this->states->transition($booking, BookingStatus::GuestDetailsCompleted, 'Guest details captured.');
            $this->states->transition($booking, BookingStatus::PendingSupplierConfirmation, 'Submitting to supplier.');

            $adapter = $this->suppliers->resolve($rateCheck->supplier->code, SupplierOperation::Book);
            $result = $adapter->book(new SupplierBookingRequestData(
                idempotencyKey: $idempotencyKey,
                supplierRateKey: $rateCheck->supplier_rate_reference,
                supplierHotelId: $rateCheck->supplier_hotel_reference,
                rooms: [$room],
                leadGuest: $leadGuest,
                guests: array_map(fn (GuestData $guest): array => $guest->jsonSerialize(), $guests),
                customerContactData: ['email' => $booking->contact_email, 'phone' => $booking->contact_phone, 'nationality' => $booking->customer_nationality],
                expectedTotal: new Money($booking->total_amount_minor, $booking->currency->code),
                specialRequests: $booking->special_requests,
                correlationId: $booking->correlation_id,
                metadata: ['scenario' => $payload['scenario'] ?? null],
            ));

            $booking->forceFill([
                'supplier_booking_reference' => $result->supplierBookingReference,
                'supplier_confirmation_reference' => $result->supplierConfirmationReference,
                'supplier_status' => $result->status->value,
                'net_amount_minor' => $result->confirmedTotal?->minorAmount,
                'supplier_response_snapshot' => $result->jsonSerialize(),
            ])->save();

            $target = match ($result->status) {
                BookingSupplierStatus::Confirmed => BookingStatus::Confirmed,
                BookingSupplierStatus::Uncertain => BookingStatus::ManualReview,
                default => BookingStatus::SupplierFailed,
            };

            $this->states->transition($booking, $target, 'Supplier booking response received.');

            $booking->forceFill([
                'confirmed_at' => $target === BookingStatus::Confirmed ? now() : null,
                'failed_at' => $target === BookingStatus::SupplierFailed ? now() : null,
            ])->save();

            $this->notifySafely($booking);

            return $booking->refresh();
        }));
    }

    private function validateRate(RateCheck $rateCheck, bool $acceptPriceChange): void
    {
        if ($rateCheck->isExpired() || ! $rateCheck->status->allowsBooking()) {
            throw BookingFlowException::invalidRate();
        }

        if ($rateCheck->price_changed && ! $acceptPriceChange) {
            throw BookingFlowException::invalidRate('The checked price changed and must be accepted before booking.');
        }
    }

    private function guestData(array $guests): array
    {
        return array_map(fn (array $guest): GuestData => new GuestData(
            firstName: $this->cleanName($guest['first_name'] ?? ''),
            lastName: $this->cleanName($guest['last_name'] ?? ''),
            type: GuestType::from($guest['type'] ?? GuestType::Adult->value),
            age: isset($guest['age']) ? (int) $guest['age'] : null,
            isLead: (bool) ($guest['is_lead_guest'] ?? false),
        ), $guests);
    }

    private function cleanName(string $value): string
    {
        return Str::of(strip_tags($value))->squish()->limit(80, '')->toString();
    }

    private function cleanText(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = Str::of(strip_tags($value))->squish()->limit($limit, '')->toString();

        return $clean === '' ? null : $clean;
    }

    private function notifySafely(Booking $booking): void
    {
        if (blank($booking->contact_email)) {
            return;
        }

        try {
            Notification::route('mail', $booking->contact_email)->notify(new BookingStatusNotification($booking));
        } catch (Throwable) {
            report(new BookingFlowException('Booking notification failed but booking state was preserved.'));
        }
    }
}
