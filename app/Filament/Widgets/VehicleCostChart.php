<?php

namespace App\Filament\Widgets;

use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\Vehicle;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

/**
 * Auto izmaksas pa mēnešiem (degviela vs apkopes/remonti), pēdējie 12 mēneši.
 * Ar filtru pēc transportlīdzekļa.
 */
class VehicleCostChart extends ChartWidget
{
    protected static ?string $heading = 'Auto izmaksas pa mēnešiem';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = null;

    protected function getFilters(): ?array
    {
        $vehicles = Vehicle::orderBy('sort_order')->get()
            ->mapWithKeys(fn (Vehicle $v) => [(string) $v->id => $v->display_name])
            ->toArray();

        return ['' => 'Visi auto'] + $vehicles;
    }

    protected function getData(): array
    {
        $vehicleId = filled($this->filter) ? (int) $this->filter : null;

        $months = collect(range(11, 0))->map(fn (int $i) => Carbon::today()->startOfMonth()->subMonths($i));

        $labels = $months->map(fn (Carbon $m) => $m->translatedFormat('M Y'))->toArray();

        $fuel = $months->map(function (Carbon $m) use ($vehicleId): float {
            $q = FuelLog::whereYear('filled_at', $m->year)->whereMonth('filled_at', $m->month);
            if ($vehicleId) {
                $q->where('vehicle_id', $vehicleId);
            }

            return round((float) $q->sum('total_cost'), 2);
        })->toArray();

        $maintenance = $months->map(function (Carbon $m) use ($vehicleId): float {
            $q = MaintenanceLog::whereYear('performed_at', $m->year)->whereMonth('performed_at', $m->month);
            if ($vehicleId) {
                $q->where('vehicle_id', $vehicleId);
            }

            return round((float) $q->sum('total_cost'), 2);
        })->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Degviela / gāze',
                    'data' => $fuel,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.7)',
                    'borderColor' => 'rgb(245, 158, 11)',
                ],
                [
                    'label' => 'Apkopes / remonti',
                    'data' => $maintenance,
                    'backgroundColor' => 'rgba(99, 102, 241, 0.7)',
                    'borderColor' => 'rgb(99, 102, 241)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
