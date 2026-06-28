<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    public function getTitle(): string
    {
        return __('admin.dashboard.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.dashboard.navigation_label');
    }
}
