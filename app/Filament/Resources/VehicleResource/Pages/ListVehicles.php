<?php

namespace App\Filament\Resources\VehicleResource\Pages;

use App\Filament\Resources\VehicleResource;
use App\Filament\Widgets\VehicleConsumptionChart;
use App\Filament\Widgets\VehicleCostChart;
use App\Filament\Widgets\VehicleStatsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVehicles extends ListRecords
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            VehicleStatsWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            VehicleCostChart::class,
            VehicleConsumptionChart::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Pievienot auto'),
        ];
    }
}
