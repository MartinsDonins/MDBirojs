<?php

namespace App\Filament\Widgets;

use App\Models\FuelLog;
use App\Models\Vehicle;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

/**
 * Vidējais degvielas patēriņš (L/100km) pa mēnešiem, atsevišķi benzīnam un gāzei.
 * Aprēķina no pilnām uzpildēm. Filtrē pēc viena transportlīdzekļa.
 */
class VehicleConsumptionChart extends ChartWidget
{
    protected static ?string $heading = 'Vidējais patēriņš (L/100km)';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = null;

    protected function getFilters(): ?array
    {
        return Vehicle::orderBy('sort_order')->get()
            ->mapWithKeys(fn (Vehicle $v) => [(string) $v->id => $v->display_name])
            ->toArray();
    }

    protected function getData(): array
    {
        $vehicleId = filled($this->filter)
            ? (int) $this->filter
            : (int) Vehicle::where('is_active', true)->orderBy('sort_order')->value('id');

        $months = collect(range(11, 0))->map(fn (int $i) => Carbon::today()->startOfMonth()->subMonths($i));
        $monthKeys = $months->map(fn (Carbon $m) => $m->format('Y-m'));
        $labels = $months->map(fn (Carbon $m) => $m->translatedFormat('M Y'))->toArray();

        $petrol = $this->monthlyConsumption($vehicleId, 'petrol', $monthKeys);
        $diesel = $this->monthlyConsumption($vehicleId, 'diesel', $monthKeys);
        $lpg = $this->monthlyConsumption($vehicleId, 'lpg', $monthKeys);

        $datasets = [];

        if (array_filter($petrol, fn ($v) => $v !== null)) {
            $datasets[] = $this->dataset('Benzīns', $petrol, '245, 158, 11');
        }
        if (array_filter($diesel, fn ($v) => $v !== null)) {
            $datasets[] = $this->dataset('Dīzelis', $diesel, '107, 114, 128');
        }
        if (array_filter($lpg, fn ($v) => $v !== null)) {
            $datasets[] = $this->dataset('Gāze (LPG)', $lpg, '34, 197, 94');
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    /**
     * Vidējais patēriņš mēnesī konkrētam degvielas veidam.
     * Katram pilnās tvertnes uzpildes pārim aprēķina L/100km un piesaista
     * otrās uzpildes mēnesim; ja mēnesī vairākas — vidējais.
     *
     * @return array<string, float|null> atslēga "Y-m"
     */
    private function monthlyConsumption(int $vehicleId, string $fuelType, $monthKeys): array
    {
        $result = $monthKeys->mapWithKeys(fn (string $k) => [$k => null])->toArray();

        if (! $vehicleId) {
            return $result;
        }

        $logs = FuelLog::where('vehicle_id', $vehicleId)
            ->where('fuel_type', $fuelType)
            ->where('full_tank', true)
            ->orderBy('odometer')
            ->get(['odometer', 'liters', 'filled_at']);

        $sums = [];   // month => [sum, count]
        $prev = null;

        foreach ($logs as $log) {
            if ($prev !== null) {
                $distance = $log->odometer - $prev->odometer;
                if ($distance > 0) {
                    $consumption = (float) $log->liters / $distance * 100;
                    $key = $log->filled_at->format('Y-m');
                    $sums[$key] ??= [0.0, 0];
                    $sums[$key][0] += $consumption;
                    $sums[$key][1]++;
                }
            }
            $prev = $log;
        }

        foreach ($sums as $key => [$sum, $count]) {
            if (array_key_exists($key, $result)) {
                $result[$key] = round($sum / $count, 2);
            }
        }

        return $result;
    }

    private function dataset(string $label, array $data, string $rgb): array
    {
        return [
            'label' => $label,
            'data' => array_values($data),
            'borderColor' => "rgb({$rgb})",
            'backgroundColor' => "rgba({$rgb}, 0.1)",
            'spanGaps' => true,
            'tension' => 0.3,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
