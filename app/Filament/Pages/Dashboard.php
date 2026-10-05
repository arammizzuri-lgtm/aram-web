<?php

namespace App\Filament\Pages;

use App\Support\DashboardRange;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function getColumns(): int|array
    {
        return 3;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('range')
                ->label('Time range')
                ->options(DashboardRange::options())
                ->default(DashboardRange::defaultValue())
                ->native(false)
                ->live(),
            DatePicker::make('startDate')
                ->label('Start date')
                ->native(false)
                ->visible(fn (Get $get) => $get('range') === DashboardRange::CUSTOM)
                ->required(fn (Get $get) => $get('range') === DashboardRange::CUSTOM),
            DatePicker::make('endDate')
                ->label('End date')
                ->native(false)
                ->visible(fn (Get $get) => $get('range') === DashboardRange::CUSTOM)
                ->required(fn (Get $get) => $get('range') === DashboardRange::CUSTOM),
        ]);
    }
}
