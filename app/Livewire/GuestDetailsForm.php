<?php

namespace App\Livewire;

use App\Enums\GuestType;
use App\Models\Country;
use App\Models\RateCheck;
use App\Services\Booking\BookingFlowException;
use App\Services\Booking\BookingService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Component;

class GuestDetailsForm extends Component
{
    public string $rateCheckUuid;

    public string $contact_email = '';

    public string $contact_phone = '';

    public string $customer_nationality = 'EG';

    public string $special_requests = '';

    public string $idempotency_key = '';

    public bool $accept_price_change = false;

    public bool $confirmation_accepted = false;

    public array $guests = [];

    public function mount(string $rateCheckUuid): void
    {
        $this->rateCheckUuid = $rateCheckUuid;
        $this->idempotency_key = (string) Str::uuid();
        $rateCheck = RateCheck::query()->where('public_uuid', $rateCheckUuid)->firstOrFail();

        $guestIndex = 0;
        foreach ($rateCheck->occupancy_snapshot as $room) {
            for ($adult = 0; $adult < $room['adults']; $adult++) {
                $this->guests[] = [
                    'type' => GuestType::Adult->value,
                    'first_name' => '',
                    'last_name' => '',
                    'age' => null,
                    'is_lead_guest' => $guestIndex === 0,
                ];
                $guestIndex++;
            }

            foreach ($room['child_ages'] ?? [] as $age) {
                $this->guests[] = [
                    'type' => GuestType::Child->value,
                    'first_name' => '',
                    'last_name' => '',
                    'age' => $age,
                    'is_lead_guest' => false,
                ];
            }
        }
    }

    public function submit(BookingService $bookings): mixed
    {
        $this->validate([
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:50', 'regex:/^[0-9+\-\s().]{7,50}$/'],
            'customer_nationality' => ['required', 'string', 'size:2', 'exists:countries,iso2'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'confirmation_accepted' => ['accepted'],
            'guests' => ['required', 'array', 'min:1'],
            'guests.*.first_name' => ['required', 'string', 'max:80', "regex:/^[\pL\pM .'-]+$/u"],
            'guests.*.last_name' => ['required', 'string', 'max:80', "regex:/^[\pL\pM .'-]+$/u"],
            'guests.*.type' => ['required', 'in:adult,child'],
            'guests.*.age' => ['nullable', 'integer', 'min:0', 'max:17'],
            'guests.*.is_lead_guest' => ['boolean'],
        ]);

        if (collect($this->guests)->where('is_lead_guest', true)->where('type', GuestType::Adult->value)->count() !== 1) {
            throw ValidationException::withMessages(['guests' => __('public.booking.validation.lead_guest')]);
        }

        $rateCheck = RateCheck::query()->where('public_uuid', $this->rateCheckUuid)->firstOrFail();

        try {
            $booking = $bookings->createAndSubmit($rateCheck, [
                'contact_email' => $this->contact_email,
                'contact_phone' => $this->contact_phone,
                'customer_nationality' => $this->customer_nationality,
                'special_requests' => $this->special_requests,
                'idempotency_key' => $this->idempotency_key,
                'accept_price_change' => $this->accept_price_change,
                'guests' => $this->guests,
            ]);
        } catch (BookingFlowException|InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['rate' => $exception->getMessage()]);
        }

        return $this->redirectRoute('bookings.show', ['booking' => $booking->public_uuid, 'locale' => app()->getLocale()], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.guest-details-form', [
            'countries' => Country::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name_en')->get(),
            'rateCheck' => RateCheck::query()->where('public_uuid', $this->rateCheckUuid)->firstOrFail(),
        ]);
    }
}
