<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Hbx\HbxBookingIdentityService;
use App\Services\Hbx\HbxCertificationEvidenceException;
use Illuminate\Console\Command;

class HbxBookingIdentityAuditCommand extends Command
{
    protected $signature = 'hbx:booking-identity:audit {--booking=} {--skip-list}';

    protected $description = 'Run a sanitized read-only HBX booking identity forensic audit.';

    public function handle(HbxBookingIdentityService $identity): int
    {
        $reference = (string) $this->option('booking');
        $booking = Booking::query()
            ->with(['supplier', 'currency', 'rateCheck', 'rooms', 'guests', 'searchSession'])
            ->where('booking_reference', $reference)
            ->first();

        if (! $booking) {
            $this->error('The requested local booking was not found.');

            return self::FAILURE;
        }

        try {
            $audit = $identity->audit($booking, includeBookingList: ! $this->option('skip-list'));
        } catch (HbxCertificationEvidenceException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('HBX booking identity forensic audit');
        $this->line('Local booking ID: '.$audit['local']['local_booking_id']);
        $this->line('Local reference: '.$audit['local']['local_reference']);
        $this->line('Supplier: '.$audit['local']['supplier_code'].' #'.$audit['local']['supplier_id']);
        $this->line('Stored supplier reference: '.($audit['local']['supplier_reference'] ?: 'not supplied'));
        $this->line('Client reference: '.$audit['local']['client_reference']);
        $this->line('Idempotency identifier: '.$audit['local']['idempotency_identifier']);
        $this->line('Local hotel: '.($audit['local']['hotel_code'] ?: 'not supplied').' / '.($audit['local']['hotel_name'] ?: 'not supplied'));
        $this->line('Local dates: '.$audit['local']['check_in'].' to '.$audit['local']['check_out']);
        $this->line('Local room/board: '.($audit['local']['room_code'] ?: 'not supplied').' / '.($audit['local']['room_name'] ?: 'not supplied').' / '.($audit['local']['board'] ?: 'not supplied'));
        $this->line('Local occupancy: '.$audit['local']['occupancy_count']);
        $this->line('Local currency/amount: '.$audit['local']['currency'].' '.$audit['local']['final_customer_amount_minor'].' minor units');
        $this->line('Local supplier/payment status: '.$audit['local']['local_supplier_status'].' / '.$audit['local']['payment_status']);
        $this->line('Original response reference path: '.($audit['original']['booking_reference_path'] ?? 'not found'));
        $this->line('Original response reference value: '.($audit['original']['booking_reference_value'] ?? 'not found'));
        $this->line('Booking Detail identity: '.($audit['detail']['reference'] ?? 'not supplied').' / '.($audit['detail']['hotel_code'] ?? 'not supplied').' / '.($audit['detail']['hotel_name'] ?? 'not supplied').' / '.($audit['detail']['status'] ?? 'not supplied'));
        $this->line('Booking List candidates: '.count($audit['candidates']));
        foreach ($audit['candidates'] as $candidate) {
            $this->line('Candidate: '.($candidate['reference'] ?? 'not supplied').' clientMatch='.($candidate['client_reference_match'] ? 'yes' : 'no').' hotel='.($candidate['hotel_code'] ?? 'not supplied').' dates='.($candidate['check_in'] ?? 'not supplied').' to '.($candidate['check_out'] ?? 'not supplied').' status='.($candidate['status'] ?? 'not supplied').' currency='.($candidate['currency'] ?? 'not supplied'));
        }
        $this->line('Cause classification: '.$audit['classification']);
        $this->line('Local actual cancellation request found: '.$this->actualCancellationFound($audit['cancellationAudit']));
        foreach ($audit['cancellationAudit'] as $entry) {
            $this->line('Cancellation audit: mode='.($entry['mode'] ?? 'unknown').' timestamp='.($entry['operation_timestamp'] ?? 'not supplied').' correlation='.($entry['correlation_id'] ?? 'not supplied').' result='.($entry['result_category'] ?? 'not supplied'));
        }
        $this->line('No booking, cancellation, modification, CheckRate, or production request was sent by this command.');

        return self::SUCCESS;
    }

    private function actualCancellationFound(array $entries): string
    {
        return collect($entries)->contains(fn (array $entry): bool => ($entry['mode'] ?? null) === 'cancellation') ? 'yes' : 'no';
    }
}
