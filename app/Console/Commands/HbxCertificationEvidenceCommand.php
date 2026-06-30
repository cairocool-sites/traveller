<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Hbx\HbxCertificationEvidenceException;
use App\Services\Hbx\HbxCertificationEvidenceService;
use Illuminate\Console\Command;

class HbxCertificationEvidenceCommand extends Command
{
    protected $signature = 'hbx:certification:evidence {--booking=}';

    protected $description = 'Collect sanitized HBX sandbox certification evidence for one existing booking.';

    public function handle(HbxCertificationEvidenceService $evidence): int
    {
        $reference = (string) $this->option('booking');

        if ($reference === '') {
            $this->error('A local booking reference is required.');

            return self::FAILURE;
        }

        $booking = Booking::query()
            ->with(['supplier', 'currency', 'rooms', 'guests', 'rateCheck'])
            ->where('booking_reference', $reference)
            ->first();

        if (! $booking) {
            $this->error('The requested local booking was not found.');

            return self::FAILURE;
        }

        try {
            $result = $evidence->collect($booking);
        } catch (HbxCertificationEvidenceException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $detail = $result['booking_detail'];

        $this->info('HBX certification evidence');
        $this->line('Booking Detail retrieved: '.($detail['retrieved'] ? 'yes' : 'no'));
        $this->line('Supplier reference: '.$detail['supplier_reference']);
        $this->line('Supplier status: '.$detail['supplier_status']);
        $this->line('Hotel: '.($detail['hotel_name'] ?: 'not supplied').' / '.($detail['hotel_code'] ?: 'not supplied'));
        $this->line('Check-in: '.($detail['check_in'] ?: 'not supplied'));
        $this->line('Check-out: '.($detail['check_out'] ?: 'not supplied'));
        $this->line('Room count: '.$detail['room_count']);
        $this->line('Room type: '.($detail['room_type'] ?: 'not supplied'));
        $this->line('Board: '.($detail['board'] ?: 'not supplied'));
        $this->line('Passenger count: '.$detail['passenger_count']);
        $this->line('Currency: '.($detail['currency'] ?: 'not supplied'));
        $this->line('Public total category: '.$detail['customer_public_total_category']);
        $this->line('Cancellation policy present: '.($detail['cancellation_policy_present'] ? 'yes' : 'no'));
        $this->line('Remarks present: '.($detail['remarks_present'] ? 'yes' : 'no'));
        $this->line('Reconfirmation present: '.($detail['reconfirmation_number_present'] ? 'yes' : 'no'));
        $this->line('Reconciliation result: '.$result['reconciliation']['summary_status']);
        $this->line('Voucher completeness: '.$this->completionSummary($result['voucher']));
        $this->line('Cancellation simulation: '.($result['cancellation_simulation']['result_category'] ?? $result['cancellation_simulation']['status']));
        $this->line('Supplier booking remains confirmed: '.($result['supplier_booking_remains_confirmed'] ? 'yes' : 'no'));
        $this->line('Payment status: '.__('public.booking.payment_statuses.'.$result['payment_status']));
        $this->line('Content API blocker: '.$result['content_api_blocker']);
        $this->line('Production remains blocked: '.($result['production_blocked'] ? 'yes' : 'no'));
        $this->line('Outstanding manual evidence: '.($result['outstanding_manual_evidence'] === [] ? 'none' : implode(', ', $result['outstanding_manual_evidence'])));

        return self::SUCCESS;
    }

    private function completionSummary(array $voucher): string
    {
        $counts = collect($voucher)->countBy()->all();

        return collect($counts)
            ->map(fn (int $count, string $status): string => "{$status}: {$count}")
            ->implode('; ');
    }
}
