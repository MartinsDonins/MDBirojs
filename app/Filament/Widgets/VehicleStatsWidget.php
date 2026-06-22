<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceLog;
use App\Models\MaintenancePlan;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VehicleStatsWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        // Kopējais nesamaksātais atlikums par apkopēm/remontiem.
        $outstanding = MaintenanceLog::query()
            ->get(['total_cost', 'amount_paid'])
            ->sum(fn ($m) => max(0, (float) $m->total_cost - (float) $m->amount_paid));

        // Šī gada izmaksas (apkopes/remonti).
        $yearCost = (float) MaintenanceLog::whereYear('performed_at', now()->year)->sum('total_cost');

        // Apkopju plāni, kas nokavēti vai drīz pienākas.
        $plans = MaintenancePlan::where('is_active', true)->with('vehicle')->get();
        $overduePlans = $plans->filter(fn (MaintenancePlan $p) => $p->due_status === 'overdue')->count();
        $soonPlans = $plans->filter(fn (MaintenancePlan $p) => $p->due_status === 'soon')->count();

        // Tuvākie derīguma termiņi (OCTA / tehniskā apskate).
        $expiringSoon = Vehicle::where('is_active', true)->get()->filter(function (Vehicle $v): bool {
            foreach (['insurance_expires_at', 'inspection_expires_at'] as $field) {
                if ($v->$field && $v->$field->isBetween(now(), now()->addDays(30))) {
                    return true;
                }
                if ($v->$field && $v->$field->isPast()) {
                    return true;
                }
            }

            return false;
        })->count();

        return [
            Stat::make('Jāsamaksā', number_format($outstanding, 2, ',', ' ') . ' €')
                ->description('Nesamaksātās apkopes/remonti')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color($outstanding > 0 ? 'danger' : 'success'),

            Stat::make('Izmaksas ' . now()->year, number_format($yearCost, 2, ',', ' ') . ' €')
                ->description('Apkopes un remonti šogad')
                ->descriptionIcon('heroicon-o-wrench-screwdriver')
                ->color('gray'),

            Stat::make('Gaidošās apkopes', $overduePlans + $soonPlans)
                ->description($overduePlans > 0 ? "Nokavētas: {$overduePlans}" : "Drīz: {$soonPlans}")
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color($overduePlans > 0 ? 'danger' : ($soonPlans > 0 ? 'warning' : 'success')),

            Stat::make('Termiņi (30 d.)', $expiringSoon)
                ->description('OCTA / tehniskā apskate')
                ->descriptionIcon('heroicon-o-shield-check')
                ->color($expiringSoon > 0 ? 'warning' : 'success'),
        ];
    }
}
