<?php

namespace Tests\Feature;

use App\Filament\Resources\FuelLogResource;
use App\Filament\Resources\MaintenanceLogResource;
use App\Filament\Resources\MaintenancePlanResource;
use App\Filament\Resources\VehicleResource;
use App\Filament\Widgets\VehicleConsumptionChart;
use App\Filament\Widgets\VehicleCostChart;
use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Renderēšanas (smoke) testi "Auto" sadaļai.
 *
 * Izmanto esošo pgsql datubāzi (skat. .env), jo pārējās projekta migrācijas
 * satur PostgreSQL specifisku DDL, kas nav saderīgs ar sqlite :memory:.
 * Katrs tests tiek ietīts transakcijā un atrīts atpakaļ.
 */
class VehicleSectionTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['pgsql'];

    protected function setUp(): void
    {
        // phpunit.xml uzspiež DB_DATABASE=:memory: (sqlite). Pārrakstām to pirms
        // sāknēšanas, lai pgsql savienojums norādītu uz reālo izstrādes datubāzi.
        foreach (['DB_DATABASE' => 'mdbirojs', 'DB_CONNECTION' => 'pgsql'] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        parent::setUp();
        config(['database.default' => 'pgsql']);
    }

    private function makeVehicle(): Vehicle
    {
        return Vehicle::create([
            'name' => 'Test auto',
            'make' => 'VW',
            'primary_fuel' => 'petrol',
            'has_lpg' => true,
            'initial_odometer' => 100000,
        ]);
    }

    public function test_vehicle_pages_render(): void
    {
        $this->actingAs(User::factory()->create());
        $vehicle = $this->makeVehicle();

        Livewire::test(VehicleResource\Pages\ListVehicles::class)->assertOk();
        Livewire::test(VehicleResource\Pages\CreateVehicle::class)->assertOk();
        Livewire::test(VehicleResource\Pages\EditVehicle::class, ['record' => $vehicle->getKey()])->assertOk();
    }

    public function test_fuel_log_pages_render(): void
    {
        $this->actingAs(User::factory()->create());
        $vehicle = $this->makeVehicle();
        $log = FuelLog::create([
            'vehicle_id' => $vehicle->id,
            'filled_at' => now(),
            'odometer' => 100100,
            'fuel_type' => 'petrol',
            'liters' => 40,
            'total_cost' => 60,
            'full_tank' => true,
        ]);

        Livewire::test(FuelLogResource\Pages\ListFuelLogs::class)->assertOk();
        Livewire::test(FuelLogResource\Pages\CreateFuelLog::class)->assertOk();
        Livewire::test(FuelLogResource\Pages\EditFuelLog::class, ['record' => $log->getKey()])->assertOk();
    }

    public function test_maintenance_pages_render(): void
    {
        $this->actingAs(User::factory()->create());
        $vehicle = $this->makeVehicle();
        $log = MaintenanceLog::create([
            'vehicle_id' => $vehicle->id,
            'performed_at' => now(),
            'type' => 'service',
            'title' => 'Eļļas maiņa',
            'total_cost' => 120,
            'amount_paid' => 50,
        ]);

        Livewire::test(MaintenanceLogResource\Pages\ListMaintenanceLogs::class)->assertOk();
        Livewire::test(MaintenanceLogResource\Pages\CreateMaintenanceLog::class)->assertOk();
        Livewire::test(MaintenanceLogResource\Pages\EditMaintenanceLog::class, ['record' => $log->getKey()])->assertOk();
    }

    public function test_maintenance_plan_pages_render(): void
    {
        $this->actingAs(User::factory()->create());
        $vehicle = $this->makeVehicle();
        $plan = MaintenancePlan::create([
            'vehicle_id' => $vehicle->id,
            'title' => 'Eļļas maiņa',
            'interval_km' => 10000,
            'interval_months' => 12,
            'last_done_odometer' => 95000,
            'last_done_at' => now()->subMonths(13),
        ]);

        Livewire::test(MaintenancePlanResource\Pages\ListMaintenancePlans::class)->assertOk();
        Livewire::test(MaintenancePlanResource\Pages\CreateMaintenancePlan::class)->assertOk();
        Livewire::test(MaintenancePlanResource\Pages\EditMaintenancePlan::class, ['record' => $plan->getKey()])->assertOk();
    }

    public function test_consumption_and_outstanding_calculations(): void
    {
        $vehicle = $this->makeVehicle();

        foreach ([[100000, 40], [100500, 35], [101000, 40]] as [$odo, $liters]) {
            FuelLog::create([
                'vehicle_id' => $vehicle->id,
                'filled_at' => now(),
                'odometer' => $odo,
                'fuel_type' => 'petrol',
                'liters' => $liters,
                'total_cost' => 60,
                'full_tank' => true,
            ]);
        }

        MaintenanceLog::create([
            'vehicle_id' => $vehicle->id,
            'performed_at' => now(),
            'type' => 'service',
            'title' => 'Apkope',
            'total_cost' => 120,
            'amount_paid' => 50,
        ]);

        $vehicle->refresh();

        // (35 + 40) litri / 1000 km * 100 = 7.5 L/100km
        $this->assertSame(7.5, $vehicle->averageConsumption('petrol'));
        $this->assertSame(101000, $vehicle->current_odometer);
        $this->assertSame(70.0, $vehicle->outstanding_amount);
    }

    public function test_plan_due_status_is_overdue_by_time(): void
    {
        $vehicle = $this->makeVehicle();
        $plan = MaintenancePlan::create([
            'vehicle_id' => $vehicle->id,
            'title' => 'Tehniskā apskate',
            'interval_months' => 12,
            'last_done_at' => now()->subMonths(13),
        ]);

        $this->assertSame('overdue', $plan->due_status);
    }

    public function test_chart_widgets_render(): void
    {
        $this->actingAs(User::factory()->create());
        $this->makeVehicle();

        Livewire::test(VehicleCostChart::class)->assertOk();
        Livewire::test(VehicleConsumptionChart::class)->assertOk();
    }

    public function test_reminder_command_reports_overdue_plan_and_documents(): void
    {
        $vehicle = Vehicle::create([
            'name' => 'Reminder auto',
            'primary_fuel' => 'petrol',
            'initial_odometer' => 50000,
            'inspection_expires_at' => now()->addDays(10),
            'insurance_expires_at' => now()->subDays(3),
        ]);
        MaintenancePlan::create([
            'vehicle_id' => $vehicle->id,
            'title' => 'Eļļas maiņa',
            'interval_months' => 12,
            'last_done_at' => now()->subMonths(13),
        ]);

        $exit = Artisan::call('auto:reminders', ['--dry-run' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Eļļas maiņa', $output);
        $this->assertStringContainsString('OCTA', $output);
        $this->assertStringContainsString('Tehniskā apskate', $output);
    }

    public function test_reminder_command_sends_when_due(): void
    {
        Mail::fake();
        config(['services.auto.reminder_email' => 'test@example.com']);

        $vehicle = $this->makeVehicle();
        MaintenancePlan::create([
            'vehicle_id' => $vehicle->id,
            'title' => 'Apkope',
            'interval_months' => 12,
            'last_done_at' => now()->subMonths(13),
        ]);

        $this->artisan('auto:reminders')
            ->expectsOutputToContain('Atgādinājumi nosūtīti')
            ->assertSuccessful();
    }

    public function test_telegram_service_disabled_without_config(): void
    {
        config(['services.telegram.bot_token' => '', 'services.telegram.chat_id' => '']);

        $service = app(TelegramService::class);

        $this->assertFalse($service->isConfigured());
        $this->assertFalse($service->send('test'));
    }
}
