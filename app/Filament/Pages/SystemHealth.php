<?php

namespace App\Filament\Pages;

use App\Support\Operations\SystemHealthService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class SystemHealth extends Page
{
    protected string $view = 'filament.pages.system-health';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 90;

    public static function canAccess(): bool
    {
        return Gate::allows('view_system_health');
    }

    public function getTitle(): string
    {
        return __('admin.system_health.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.system_health.navigation_label');
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return app(SystemHealthService::class)->adminSummary();
    }
}
