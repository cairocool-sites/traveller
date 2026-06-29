<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\SupplierStatus;
use App\Models\City;
use App\Models\RateCheck;
use App\Models\SearchSession;
use App\Models\Supplier;
use App\Services\Booking\BookingService;
use App\Services\Booking\RateCheckService;
use App\Services\PublicSearch\HotelSearchService;
use App\Services\PublicSearch\MoneyFormatter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

class HbxVerifySandboxBookingCommand extends Command
{
    private const SUPPLIER_CODE = 'hbx_hotels';

    private const SANDBOX_BASE_URL = 'https://api.test.hotelbeds.com';

    protected $signature = 'hbx:verify-sandbox-booking {--dry-run : Validate search and CheckRate without sending a booking request}';

    protected $description = 'Manually verify exactly one guarded HBX sandbox booking and internal voucher readiness.';

    public function handle(
        HotelSearchService $searches,
        RateCheckService $rateChecks,
        BookingService $bookings,
        MoneyFormatter $money,
    ): int {
        try {
            $supplier = $this->guardSupplier();
            $this->confirmManualIntent();

            $session = $searches->search($this->criteria(), 'hbx-manual-verification');
            [$hotel, $rate] = $this->firstAvailableRate($session);
            $rateCheck = $rateChecks->check($session, $hotel['public_token'], $rate['public_rate_token']);

            if (! $rateCheck->status->allowsBooking()) {
                $this->error('HBX CheckRate did not return a bookable sandbox rate.');

                return self::FAILURE;
            }

            $this->displaySanitizedSummary($session, $rateCheck, $money);

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
        }
    }

    private function guardSupplier(): Supplier
    {
        if (! (bool) config('services.hbx.sandbox_booking_enabled')) {
            throw new RuntimeException('HBX sandbox booking verification is disabled. Set HBX_SANDBOX_BOOKING_ENABLED=true only for the controlled manual run.');
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
        if (! $this->confirm('This command is for one controlled HBX sandbox verification only. Continue?')) {
            throw new RuntimeException('Verification cancelled before any booking request.');
        }
    }

    private function criteria(): array
    {
        $city = City::query()->where('name_en', 'Cairo')->firstOrFail();

        return [
            'destination' => "city:{$city->id}",
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

    private function firstAvailableRate(SearchSession $session): array
    {
        $hotel = collect($session->results_snapshot)->first(fn (array $candidate): bool => filled($candidate['rates'] ?? []));

        if (! $hotel) {
            throw new RuntimeException('HBX sandbox search returned no available hotel rates.');
        }

        $rate = collect($hotel['rates'])->first();

        if (! $rate) {
            throw new RuntimeException('HBX sandbox search returned a hotel without rates.');
        }

        return [$hotel, $rate];
    }

    private function displaySanitizedSummary(SearchSession $session, RateCheck $rateCheck, MoneyFormatter $money): void
    {
        $this->line('Hotel: '.(string) ($rateCheck->searchSession->results_snapshot[0]['name'] ?? 'Selected HBX sandbox hotel'));
        $this->line('Dates: '.$session->check_in->toDateString().' to '.$session->check_out->toDateString());
        $this->line('Currency: '.$session->currency);
        $this->line('Selling total: '.$money->formatMinor((int) ($rateCheck->checked_amount_minor ?? $rateCheck->original_amount_minor), $session->currency));
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
