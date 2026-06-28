<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AdminOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = Auth::user();

        return [
            Stat::make(__('admin.dashboard.stats.project'), config('travel.brand.name')),
            Stat::make(__('admin.dashboard.stats.locale'), app()->getLocale()),
            Stat::make(__('admin.dashboard.stats.timezone'), config('app.timezone')),
            Stat::make(__('admin.dashboard.stats.currency'), config('travel.currency.default')),
            Stat::make(__('admin.dashboard.stats.role'), $user?->roles->pluck('name')->implode(', ') ?: __('admin.common.not_available')),
        ];
    }
}
