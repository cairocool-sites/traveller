<?php

namespace App\Services\PublicSearch;

use App\Support\Money\Money;

class OfferPricingService
{
    public function sellingPrice(Money $supplierTotal): Money
    {
        $basisPoints = max(0, (int) config('travel.public_search.markup_basis_points', 0));

        if ($basisPoints === 0) {
            return $supplierTotal;
        }

        $markup = intdiv(($supplierTotal->minorAmount * $basisPoints) + 9999, 10000);

        return new Money(
            minorAmount: $supplierTotal->minorAmount + $markup,
            currency: $supplierTotal->currency,
            decimalPlaces: $supplierTotal->decimalPlaces,
        );
    }
}
