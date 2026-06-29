<?php

namespace App\Services\PublicSearch;

use App\Enums\CancellationPenaltyType;
use App\Services\Supplier\Data\CancellationPolicyData;

class CancellationSummaryService
{
    public function summarize(array $policies, string $locale = 'ar'): string
    {
        if ($policies === []) {
            return __('public.cancellation.unknown');
        }

        foreach ($policies as $policy) {
            if (! $policy instanceof CancellationPolicyData) {
                continue;
            }

            if ($policy->isNonRefundable) {
                return __('public.cancellation.non_refundable');
            }

            if ($policy->penaltyType === CancellationPenaltyType::None && $policy->validUntil) {
                return __('public.cancellation.free_until', ['date' => $policy->validUntil->timezone(config('app.timezone'))->toDateString()]);
            }
        }

        return __('public.cancellation.penalty_applies');
    }
}
