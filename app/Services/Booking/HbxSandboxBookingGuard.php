<?php

namespace App\Services\Booking;

use App\Enums\SupplierStatus;
use App\Models\Supplier;

class HbxSandboxBookingGuard
{
    private const TEST_BASE_URL = 'https://api.test.hotelbeds.com';

    public function assertAllowed(Supplier $supplier): void
    {
        if ($supplier->code !== 'hbx_hotels') {
            return;
        }

        if ($supplier->status !== SupplierStatus::Active) {
            throw new BookingFlowException('HBX sandbox booking requires an active sandbox supplier.');
        }

        if (! (bool) config('services.hbx.sandbox_booking_enabled')) {
            throw new BookingFlowException('HBX sandbox booking submission is disabled.');
        }

        if ($this->baseUrl($supplier) !== self::TEST_BASE_URL) {
            throw new BookingFlowException('HBX booking is blocked because the configured endpoint is not the sandbox endpoint.');
        }

        if (blank(config('services.hbx.api_key')) || blank(config('services.hbx.api_secret'))) {
            throw new BookingFlowException('HBX sandbox booking credentials are not configured.');
        }
    }

    private function baseUrl(Supplier $supplier): string
    {
        return rtrim((string) ($supplier->base_url ?: config('services.hbx.base_url')), '/');
    }
}
