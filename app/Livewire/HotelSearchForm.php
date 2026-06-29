<?php

namespace App\Livewire;

use App\Models\Currency;
use App\Services\PublicSearch\DestinationLookupService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class HotelSearchForm extends Component
{
    public string $destination = '';

    public string $destinationTerm = '';

    public string $checkIn = '';

    public string $checkOut = '';

    public int $rooms = 1;

    public int $adults = 2;

    public int $children = 0;

    public array $childAges = [];

    public string $currency = 'EGP';

    public string $locale = 'ar';

    public ?string $nationality = null;

    public ?string $residencyCountry = null;

    public array $destinationOptions = [];

    public function mount(?string $locale = null): void
    {
        $this->locale = $locale ?: app()->getLocale();
        $this->currency = request()->query('currency', config('travel.currency.default'));
        $this->checkIn = CarbonImmutable::now()->addDays(7)->toDateString();
        $this->checkOut = CarbonImmutable::now()->addDays(10)->toDateString();
    }

    public function updatedDestinationTerm(DestinationLookupService $destinations): void
    {
        $this->destinationOptions = $destinations->search($this->destinationTerm, $this->locale)->map->jsonSerialize()->all();
    }

    public function updatedChildren(): void
    {
        $this->children = max(0, min($this->children, config('travel.public_search.max_children_per_room')));
        $this->childAges = array_slice($this->childAges, 0, $this->children);

        while (count($this->childAges) < $this->children) {
            $this->childAges[] = '';
        }
    }

    public function selectDestination(string $token, string $label): void
    {
        $this->destination = $token;
        $this->destinationTerm = $label;
        $this->destinationOptions = [];
    }

    public function submit()
    {
        return redirect()->route('hotels.search', [
            'destination' => $this->destination,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'rooms' => $this->rooms,
            'adults' => $this->adults,
            'children' => $this->children,
            'child_ages' => $this->childAges,
            'nationality' => $this->nationality,
            'residency_country' => $this->residencyCountry,
            'currency' => $this->currency,
            'locale' => $this->locale,
        ]);
    }

    public function render()
    {
        return view('livewire.hotel-search-form', [
            'currencies' => $this->currencies(),
            'maxRooms' => config('travel.public_search.max_rooms'),
            'maxAdults' => config('travel.public_search.max_adults_per_room'),
            'maxChildren' => config('travel.public_search.max_children_per_room'),
            'maxChildAge' => config('travel.public_search.max_child_age'),
        ]);
    }

    private function currencies(): Collection
    {
        if (! Schema::hasTable('currencies')) {
            return collect(config('travel.currency.supported'))->map(fn (string $code): object => (object) ['code' => $code, 'symbol' => $code]);
        }

        return Currency::query()
            ->where('is_active', true)
            ->whereIn('code', config('travel.currency.supported'))
            ->orderBy('sort_order')
            ->get(['code', 'symbol']);
    }
}
