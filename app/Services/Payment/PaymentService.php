<?php

namespace App\Services\Payment;

use App\Enums\BookingStatus;
use App\Enums\ManualPaymentStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\ManualPaymentMethod;
use App\Models\Payment;
use App\Notifications\PaymentStatusNotification;
use App\Services\Documents\DocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PaymentService
{
    public function __construct(
        private readonly PaymentStatusMachine $statuses,
        private readonly DocumentService $documents,
    ) {}

    public function submit(Booking $booking, ManualPaymentMethod $method, array $data, ?UploadedFile $evidence = null): Payment
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            throw new PaymentFlowException('Only confirmed bookings can receive manual payments.');
        }

        if ($booking->payments()->whereIn('status', [ManualPaymentStatus::Pending, ManualPaymentStatus::Submitted, ManualPaymentStatus::UnderReview, ManualPaymentStatus::Approved, ManualPaymentStatus::Paid])->exists()) {
            throw new PaymentFlowException('An active payment attempt already exists for this booking.');
        }

        if ($method->requires_reference && blank($data['submitted_reference'] ?? null)) {
            throw new PaymentFlowException('Payment reference is required for this method.');
        }

        if ($method->supports_attachment && ! $evidence) {
            throw new PaymentFlowException('Payment evidence is required for this method.');
        }

        return DB::transaction(function () use ($booking, $method, $data, $evidence): Payment {
            $payment = Payment::query()->create([
                'public_uuid' => (string) Str::uuid(),
                'booking_id' => $booking->id,
                'manual_payment_method_id' => $method->id,
                'status' => ManualPaymentStatus::Pending,
                'currency_id' => $booking->currency_id,
                'amount_minor' => $booking->total_amount_minor,
                'submitted_reference' => $data['submitted_reference'] ?? null,
                'customer_notes' => $data['customer_notes'] ?? null,
                'submitted_at' => now(),
                'submitted_by' => Auth::id(),
                'correlation_id' => (string) Str::uuid(),
            ]);

            if ($evidence) {
                $this->storeEvidence($payment, $evidence);
            }

            $this->statuses->transition($payment, ManualPaymentStatus::Submitted, 'Manual payment submitted.');
            $booking->forceFill(['payment_status' => PaymentStatus::Pending])->save();
            $this->notifySafely($payment, 'Payment submitted');

            return $payment->refresh();
        });
    }

    public function markUnderReview(Payment $payment): Payment
    {
        $payment = $this->statuses->transition($payment, ManualPaymentStatus::UnderReview, 'Payment moved under review.');
        $payment->forceFill(['reviewed_at' => now(), 'reviewed_by' => Auth::id()])->save();

        return $payment->refresh();
    }

    public function approve(Payment $payment, string $reason = '', bool $superAdminOverride = false): Payment
    {
        $this->guardMakerChecker($payment, $reason, $superAdminOverride);

        if ($payment->status === ManualPaymentStatus::Submitted) {
            $payment = $this->markUnderReview($payment);
        }

        $payment = $this->statuses->transition($payment, ManualPaymentStatus::Approved, $reason ?: 'Payment approved.');
        $payment->forceFill(['reviewed_at' => now(), 'approved_at' => now(), 'reviewed_by' => Auth::id()])->save();
        $this->statuses->transition($payment, ManualPaymentStatus::Paid, 'Receipt and booking payment state issued.');

        $payment->booking->forceFill(['payment_status' => PaymentStatus::Paid])->save();
        $this->documents->issueForPaidBooking($payment->booking, $payment->refresh());
        $this->notifySafely($payment, 'Payment approved');

        return $payment->refresh();
    }

    public function reject(Payment $payment, string $reason): Payment
    {
        if (blank($reason)) {
            throw new PaymentFlowException('Rejection reason is required.');
        }

        if ($payment->status === ManualPaymentStatus::Submitted) {
            $payment = $this->markUnderReview($payment);
        }

        $payment = $this->statuses->transition($payment, ManualPaymentStatus::Rejected, $reason);
        $payment->forceFill(['reviewed_at' => now(), 'rejected_at' => now(), 'reviewed_by' => Auth::id(), 'internal_notes' => $reason])->save();
        $this->notifySafely($payment, 'Payment rejected');

        return $payment->refresh();
    }

    public function storeEvidence(Payment $payment, UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();

        if (! in_array($extension, config('travel.payments.evidence_mimes'), true) || ! in_array($mime, config('travel.payments.evidence_mime_types'), true)) {
            throw new PaymentFlowException('Unsupported payment evidence file type.');
        }

        if ($file->getSize() > config('travel.payments.evidence_max_kilobytes') * 1024) {
            throw new PaymentFlowException('Payment evidence file is too large.');
        }

        $directory = trim(config('travel.payments.private_directory'), '/').'/'.$payment->public_uuid;
        $path = $file->storeAs($directory, Str::uuid().'.'.$extension, 'local');

        $payment->evidences()->create([
            'file_path' => $path,
            'original_name' => basename($file->getClientOriginalName()),
            'mime_type' => $mime,
            'file_size' => $file->getSize(),
            'checksum' => hash_file('sha256', Storage::disk('local')->path($path)),
            'uploaded_at' => now(),
        ]);
    }

    private function guardMakerChecker(Payment $payment, string $reason, bool $superAdminOverride): void
    {
        $user = Auth::user();

        if (! $user) {
            throw new PaymentFlowException('Reviewer is required.');
        }

        if ($payment->submitted_by && $payment->submitted_by === $user->id) {
            if (! $user->hasRole('super_admin') || ! $superAdminOverride || blank($reason)) {
                throw new PaymentFlowException('Maker-checker control prevents approving your own payment submission.');
            }
        }
    }

    private function notifySafely(Payment $payment, string $subject): void
    {
        try {
            if (filled($payment->booking->contact_email)) {
                Notification::route('mail', $payment->booking->contact_email)->notify(new PaymentStatusNotification($payment, $subject));
            }
        } catch (Throwable) {
            report(new PaymentFlowException('Payment notification failed but payment state was preserved.'));
        }
    }
}
