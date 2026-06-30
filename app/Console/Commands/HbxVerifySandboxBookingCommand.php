<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\SupplierStatus;
use App\Models\HbxDestination;
use App\Models\HbxHotel;
use App\Models\RateCheck;
use App\Models\SearchSession;
use App\Models\Supplier;
use App\Services\Booking\BookingService;
use App\Services\Booking\RateCheckService;
use App\Services\PublicSearch\DestinationLookupService;
use App\Services\PublicSearch\HotelSearchService;
use App\Services\PublicSearch\MoneyFormatter;
use App\Services\PublicSearch\SupplierDestinationResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class HbxVerifySandboxBookingCommand extends Command
{
    private const SUPPLIER_CODE = 'hbx_hotels';

    private const SANDBOX_BASE_URL = 'https://api.test.hotelbeds.com';

    protected $signature = 'hbx:verify-sandbox-booking
        {--dry-run : Validate real HBX Sandbox Availability without sending a booking request}
        {--destination= : Local HBX destination id to verify}
        {--hotel= : Local HBX hotel id to verify}';

    protected $description = 'Manually verify exactly one guarded HBX sandbox booking and internal voucher readiness.';

    public function handle(
        HotelSearchService $searches,
        RateCheckService $rateChecks,
        BookingService $bookings,
        MoneyFormatter $money,
        SupplierDestinationResolver $supplierDestinations,
    ): int {
        try {
            $supplier = $this->guardSupplier();
            config(['travel.public_search.suppliers' => [self::SUPPLIER_CODE]]);

            $this->confirmManualIntent();

            $criteria = $this->criteria();
            $localDestination = app(DestinationLookupService::class)->resolve($criteria['destination'], $criteria['locale']);
            $hbxDestination = $supplierDestinations->forHbx($localDestination);
            $session = $searches->search($criteria, 'hbx-manual-verification');
            [$hotel, $rate] = $this->firstAvailableRate($session);

            $rateCheck = null;
            if (! $this->option('dry-run') || (bool) ($rate['requires_check_rate'] ?? false)) {
                $rateCheck = $rateChecks->check($session, $hotel['public_token'], $rate['public_rate_token']);
                $this->assertHbxCheckRate($rateCheck);

                if (! $rateCheck->status->allowsBooking()) {
                    $this->error('HBX CheckRate did not return a bookable sandbox rate.');

                    return self::FAILURE;
                }
            }

            $this->displaySanitizedSummary($session, $rateCheck, $rate, $money, $supplier, $localDestination->label, $hbxDestination['destination_code'], count($hbxDestination['hotel_codes']));

            if ($this->option('dry-run')) {
                $this->info('Dry run complete. No booking request was sent.');

                return self::SUCCESS;
            }

            if (! $this->confirm('Send exactly one HBX sandbox booking request now?')) {
                $this->warn('Verification stopped before booking. No booking request was sent.');

                return self::FAILURE;
            }

            $booking = $bookings->createAndSubmit($rateCheck, $this->bookingPayload());

            $this->line('Local reference: '.$booking->booking_reference);
            $this->line('HBX reference: '.($booking->supplier_confirmation_reference ?: $booking->supplier_booking_reference ?: 'pending manual review'));
            $this->line('Status: '.$booking->status->value);

            if ($booking->status === BookingStatus::ManualReview) {
                $this->warn('Supplier outcome requires manual lookup. No cancellation was attempted.');
            }

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('HBX sandbox verification failed safely before completion. Check sanitized supplier logs for details.');

            return self::FAILURE;
        }
    }

    private function guardSupplier(): Supplier
    {
        if (! (bool) config('services.hbx.sandbox_booking_enabled')) {
            if (! $this->option('dry-run')) {
                throw new RuntimeException('HBX sandbox booking verification is disabled. Set HBX_SANDBOX_BOOKING_ENABLED=true only for the controlled manual run.');
            }
        }

        $supplier = Supplier::query()->where('code', self::SUPPLIER_CODE)->first();

        if (! $supplier) {
            throw new RuntimeException('The hbx_hotels sandbox supplier is not configured.');
        }

        if ($supplier->status !== SupplierStatus::Active) {
            throw new RuntimeException('The hbx_hotels sandbox supplier must be active.');
        }

        $baseUrl = rtrim((string) ($supplier->base_url ?: config('services.hbx.base_url')), '/');

        if ($baseUrl !== self::SANDBOX_BASE_URL) {
            throw new RuntimeException('HBX sandbox booking verification is blocked because the configured endpoint is not https://api.test.hotelbeds.com.');
        }

        if (blank(config('services.hbx.api_key')) || blank(config('services.hbx.api_secret'))) {
            throw new RuntimeException('HBX sandbox credentials are not configured.');
        }

        return $supplier;
    }

    private function confirmManualIntent(): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        if (! $this->confirm('This command is for one controlled HBX sandbox verification only. Continue?')) {
            throw new RuntimeException('Verification cancelled before any booking request.');
        }
    }

    private function criteria(): array
    {
        $destination = $this->destinationToken();

        return [
            'destination' => $destination,
            'check_in' => now()->addDays(21)->toDateString(),
            'check_out' => now()->addDays(22)->toDateString(),
            'rooms' => 1,
            'adults' => 2,
            'children' => 0,
            'currency' => config('travel.currency.default', 'EGP'),
            'locale' => 'en',
            'nationality' => 'EG',
            'residency_country' => 'EG',
        ];
    }

    private function destinationToken(): string
    {
        if ($this->option('destination')) {
            return 'hbx_destination:'.(int) $this->option('destination');
        }

        if ($this->option('hotel')) {
            return 'hbx_hotel:'.(int) $this->option('hotel');
        }

        $destination = HbxDestination::query()
            ->where('supplier_code', self::SUPPLIER_CODE)
            ->where('supplier_active', true)
            ->where('public_enabled', true)
            ->orderBy('country_code')
            ->orderBy('destination_name')
            ->first();

        if ($destination) {
            return 'hbx_destination:'.$destination->id;
        }

        $hotel = HbxHotel::query()
            ->where('supplier_code', self::SUPPLIER_CODE)
            ->where('supplier_active', true)
            ->where('public_enabled', true)
            ->orderBy('hotel_code')
            ->first();

        if ($hotel) {
            return 'hbx_hotel:'.$hotel->id;
        }

        throw new RuntimeException('No public synchronized HBX destination or hotel is available for sandbox verification.');
    }

    private function firstAvailableRate(SearchSession $session): array
    {
        $hotel = collect($session->results_snapshot)->first(fn (array $candidate): bool => filled($candidate['rates'] ?? []));

        if (! $hotel) {
            throw new RuntimeException($this->availabilityFailureMessage($session));
        }

        if (($hotel['supplier_code'] ?? null) !== self::SUPPLIER_CODE) {
            throw new RuntimeException('HBX sandbox verification refused a non-HBX search result. Mock fallback is not allowed.');
        }

        $rate = collect($hotel['rates'])->first();

        if (! $rate) {
            throw new RuntimeException('HBX sandbox search returned a hotel without rates.');
        }

        return [$hotel, $rate];
    }

    private function assertHbxCheckRate(RateCheck $rateCheck): void
    {
        if ($rateCheck->supplier?->code !== self::SUPPLIER_CODE) {
            throw new RuntimeException('HBX sandbox verification refused a non-HBX CheckRate result. Mock fallback is not allowed.');
        }
    }

    private function availabilityFailureMessage(SearchSession $session): string
    {
        $warning = collect($session->warnings ?? [])
            ->filter(fn (string $value): bool => filled($value))
            ->map(fn (string $value): string => Str::of($value)->squish()->limit(120, '')->toString())
            ->first();

        return $warning
            ? 'HBX sandbox availability search returned no offers. '.$warning
            : 'HBX sandbox availability search returned no offers.';
    }

    private function displaySanitizedSummary(SearchSession $session, ?RateCheck $rateCheck, array $rate, MoneyFormatter $money, Supplier $supplier, string $localDestination, string $hbxDestinationCode, int $hotelCodeCount): void
    {
        $this->line('Supplier: '.$supplier->code);
        $this->line('Local destination: '.$localDestination);
        $this->line('HBX destination code: '.$hbxDestinationCode);
        $this->line('Number of hotel codes searched: '.$hotelCodeCount);
        $this->line('Dates: '.$session->check_in->toDateString().' to '.$session->check_out->toDateString());
        $this->line('Currency: '.$session->currency);
        $this->line('Availability result count: '.count($session->results_snapshot ?? []));
        $this->line('Availability source: HBX Sandbox');
        if ($rateCheck) {
            $this->line('CheckRate source: HBX Sandbox');
            $this->line('Selling total: '.$money->formatMinor((int) ($rateCheck->checked_amount_minor ?? $rateCheck->original_amount_minor), $session->currency));
        } else {
            $this->line('CheckRate source: not required for selected BOOKABLE rate');
            $this->line('Selling total: '.$money->formatMinor((int) data_get($rate, 'total.minor_amount', 0), $session->currency));
        }
    }

    private function bookingPayload(): array
    {
        return [
            'contact_email' => 'sandbox.guest@example.test',
            'contact_phone' => '+200000000000',
            'customer_nationality' => 'EG',
            'idempotency_key' => 'hbx-manual-'.Str::uuid(),
            'accept_price_change' => true,
            'guests' => [
                ['type' => 'adult', 'first_name' => 'Sandbox', 'last_name' => 'Tester', 'is_lead_guest' => true],
                ['type' => 'adult', 'first_name' => 'Sandbox', 'last_name' => 'Traveler', 'is_lead_guest' => false],
            ],
            'special_requests' => 'Sandbox verification booking only.',
        ];
    }
}
