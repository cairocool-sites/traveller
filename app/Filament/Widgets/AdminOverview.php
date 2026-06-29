<?php

namespace App\Filament\Widgets;

use App\Enums\ManualPaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Voucher;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AdminOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = Auth::user();

        $stats = [
            Stat::make(__('admin.dashboard.stats.project'), config('travel.brand.name')),
            Stat::make(__('admin.dashboard.stats.locale'), app()->getLocale()),
            Stat::make(__('admin.dashboard.stats.timezone'), config('app.timezone')),
            Stat::make(__('admin.dashboard.stats.currency'), config('travel.currency.default')),
            Stat::make(__('admin.dashboard.stats.role'), $user?->roles->pluck('name')->implode(', ') ?: __('admin.common.not_available')),
        ];

        if ($user?->hasPermissionTo('view_payments')) {
            $stats[] = Stat::make(__('admin.dashboard.stats.payments_pending_review'), Payment::query()->whereIn('status', [ManualPaymentStatus::Submitted, ManualPaymentStatus::UnderReview])->count());
            $stats[] = Stat::make(__('admin.dashboard.stats.payments_approved'), Payment::query()->whereIn('status', [ManualPaymentStatus::Approved, ManualPaymentStatus::Paid])->count());
            $stats[] = Stat::make(__('admin.dashboard.stats.payments_rejected'), Payment::query()->where('status', ManualPaymentStatus::Rejected)->count());
        }

        if ($user?->hasPermissionTo('view_documents')) {
            $stats[] = Stat::make(__('admin.dashboard.stats.documents_issued'), Voucher::query()->count() + Invoice::query()->count() + Receipt::query()->count());
        }

        return $stats;
    }
}
