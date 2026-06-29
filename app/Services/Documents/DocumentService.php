<?php

namespace App\Services\Documents;

use App\Enums\BookingStatus;
use App\Enums\ManualPaymentStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

class DocumentService
{
    public function __construct(private readonly DocumentNumberGenerator $numbers) {}

    public function issueForPaidBooking(Booking $booking, ?Payment $payment = null): array
    {
        return DB::transaction(function () use ($booking, $payment): array {
            return [
                'voucher' => $this->issueVoucher($booking),
                'invoice' => $this->issueInvoice($booking),
                'receipt' => $payment ? $this->issueReceipt($payment) : null,
            ];
        });
    }

    public function issueVoucher(Booking $booking): Voucher
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            throw new \RuntimeException('Voucher can only be issued for confirmed bookings.');
        }

        return Voucher::query()->firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'voucher_number' => $this->numbers->make(config('travel.documents.voucher_prefix'), Voucher::class, 'voucher_number'),
                'status' => 'issued',
                'snapshot' => $this->bookingSnapshot($booking),
                'verification_token' => $this->numbers->token(),
                'issued_at' => now(),
            ],
        );
    }

    public function issueInvoice(Booking $booking): Invoice
    {
        return Invoice::query()->firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'invoice_number' => $this->numbers->make(config('travel.documents.invoice_prefix'), Invoice::class, 'invoice_number'),
                'customer_name' => $booking->guests()->where('is_lead_guest', true)->first()?->first_name.' '.$booking->guests()->where('is_lead_guest', true)->first()?->last_name,
                'customer_email' => $booking->contact_email,
                'currency_id' => $booking->currency_id,
                'subtotal_minor' => $booking->total_amount_minor,
                'tax_minor' => 0,
                'fees_minor' => $booking->fees_amount_minor ?? 0,
                'discount_minor' => 0,
                'total_minor' => $booking->total_amount_minor,
                'issued_at' => now(),
                'status' => 'issued',
                'snapshot' => $this->bookingSnapshot($booking) + ['label' => 'Commercial invoice - not an official e-invoice.'],
                'verification_token' => $this->numbers->token(),
            ],
        );
    }

    public function issueReceipt(Payment $payment): Receipt
    {
        if (! in_array($payment->status, [ManualPaymentStatus::Approved, ManualPaymentStatus::Paid], true)) {
            throw new \RuntimeException('Receipt can only be issued after payment approval.');
        }

        return Receipt::query()->firstOrCreate(
            ['payment_id' => $payment->id],
            [
                'receipt_number' => $this->numbers->make(config('travel.documents.receipt_prefix'), Receipt::class, 'receipt_number'),
                'amount_minor' => $payment->amount_minor,
                'currency_id' => $payment->currency_id,
                'payment_method_snapshot' => [
                    'code' => $payment->method?->code,
                    'name_en' => $payment->method?->name_en,
                    'name_ar' => $payment->method?->name_ar,
                ],
                'approved_at' => $payment->approved_at,
                'issued_at' => now(),
                'status' => 'issued',
                'snapshot' => [
                    'booking_reference' => $payment->booking->booking_reference,
                    'amount_minor' => $payment->amount_minor,
                    'currency' => $payment->currency->code,
                    'method' => $payment->method?->code,
                ],
                'verification_token' => $this->numbers->token(),
            ],
        );
    }

    private function bookingSnapshot(Booking $booking): array
    {
        $booking->loadMissing(['rooms', 'guests', 'currency']);

        return [
            'company_name' => config('travel.documents.company_name'),
            'company_contact' => config('travel.documents.company_contact'),
            'booking_reference' => $booking->booking_reference,
            'booking_status' => $booking->status->value,
            'hotel_name' => $booking->hotel_snapshot['name'] ?? 'Hotel',
            'check_in' => $booking->check_in->toDateString(),
            'check_out' => $booking->check_out->toDateString(),
            'rooms' => $booking->rooms->map->only(['room_name', 'board_basis', 'adults', 'children'])->all(),
            'guests' => $booking->guests->map(fn ($guest): array => [
                'type' => $guest->type->value,
                'name' => trim($guest->first_name.' '.$guest->last_name),
                'is_lead_guest' => $guest->is_lead_guest,
            ])->all(),
            'cancellation_policy' => $booking->cancellation_policy_snapshot ?? [],
            'important_notes' => 'Supplier identity, net price, and technical references are hidden.',
            'currency' => $booking->currency->code,
            'total_minor' => $booking->total_amount_minor,
        ];
    }
}
