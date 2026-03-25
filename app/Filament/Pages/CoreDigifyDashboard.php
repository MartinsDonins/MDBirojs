<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Models\Transaction;
use App\Services\CoreDigifyService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class CoreDigifyDashboard extends Page implements HasActions
{
    use InteractsWithActions;

    protected static string $view = 'filament.pages.coredigify-dashboard';

    protected static ?string $navigationLabel = 'CoreDigify';
    protected static ?string $navigationIcon  = 'heroicon-o-cloud-arrow-up';
    protected static ?string $title           = 'CoreDigify — Maksājumu sinhronizācija';
    protected static ?int    $navigationSort  = 10;

    /** VID columns that qualify as business income */
    private const VID_COLS = [4, 5, 6];

    public int    $filterYear    = 0;
    public string $filterStatus  = 'all'; // all | pending | sent | error
    public array  $transactions  = [];
    public array  $stats         = ['total' => 0, 'sent' => 0, 'pending' => 0, 'error' => 0];
    public bool   $integrationEnabled = false;

    public function mount(): void
    {
        $this->filterYear        = (int) date('Y');
        $this->integrationEnabled = (bool) AppSetting::get('coredigify_enabled', false);
        $this->loadTransactions();
    }

    public function loadTransactions(): void
    {
        $base = Transaction::with(['account', 'category', 'cashOrder'])
            ->where('type', 'INCOME')
            ->where('status', 'COMPLETED')
            ->whereHas('category', fn ($q) => $q->whereIn('vid_column', self::VID_COLS))
            ->whereYear('occurred_at', $this->filterYear)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        // Stats (independent of status filter)
        $all = (clone $base)->get();
        $this->stats = [
            'total'   => $all->count(),
            'sent'    => $all->whereNotNull('coredigify_sent_at')->count(),
            'pending' => $all->whereNull('coredigify_sent_at')->whereNull('coredigify_sync_error')->count(),
            'error'   => $all->whereNotNull('coredigify_sync_error')->whereNull('coredigify_sent_at')->count(),
        ];

        // Apply status filter
        $query = clone $base;
        if ($this->filterStatus === 'sent') {
            $query->whereNotNull('coredigify_sent_at');
        } elseif ($this->filterStatus === 'pending') {
            $query->whereNull('coredigify_sent_at')->whereNull('coredigify_sync_error');
        } elseif ($this->filterStatus === 'error') {
            $query->whereNotNull('coredigify_sync_error')->whereNull('coredigify_sent_at');
        }

        $this->transactions = $query->limit(200)->get()->map(fn ($tx) => [
            'id'                   => $tx->id,
            'occurred_at'          => $tx->occurred_at?->format('d.m.Y'),
            'counterparty_name'    => $tx->counterparty_name,
            'description'          => $tx->description,
            'reference'            => $tx->reference,
            'amount_eur'           => number_format((float) $tx->amount_eur, 2, ',', ' '),
            'currency'             => $tx->currency,
            'category_name'        => $tx->category?->name,
            'cash_order_number'    => $tx->cashOrder?->number,
            'coredigify_sent_at'   => $tx->coredigify_sent_at?->format('d.m.Y H:i'),
            'coredigify_sync_error'=> $tx->coredigify_sync_error,
            'sync_status'          => $this->resolveSyncStatus($tx),
        ])->toArray();
    }

    private function resolveSyncStatus(Transaction $tx): string
    {
        if ($tx->coredigify_sent_at) return 'sent';
        if ($tx->coredigify_sync_error) return 'error';
        return 'pending';
    }

    public function updatedFilterYear(): void
    {
        $this->loadTransactions();
    }

    public function updatedFilterStatus(): void
    {
        $this->loadTransactions();
    }

    public function getAvailableYears(): array
    {
        return Transaction::where('type', 'INCOME')
            ->where('status', 'COMPLETED')
            ->whereHas('category', fn ($q) => $q->whereIn('vid_column', self::VID_COLS))
            ->selectRaw('EXTRACT(YEAR FROM occurred_at)::integer AS yr')
            ->distinct()
            ->orderByDesc('yr')
            ->pluck('yr')
            ->toArray();
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function syncPendingAction(): Action
    {
        return Action::make('syncPending')
            ->label('Sinhronizēt gaidošās')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(fn () => "Nosūtīt visus {$this->stats['pending']} nesinhronizētos darījumus {$this->filterYear}. gadā uz CoreDigify?")
            ->action('runSyncPending');
    }

    public function syncErrorsAction(): Action
    {
        return Action::make('syncErrors')
            ->label('Atkārtot kļūdainās')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(fn () => "Atkārtoti nosūtīt {$this->stats['error']} darījumus ar kļūdām?")
            ->action('runSyncErrors');
    }

    public function resendTransactionAction(): Action
    {
        return Action::make('resendTransaction')
            ->label('Nosūtīt atkārtoti')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->action(function (array $arguments) {
                $tx = Transaction::with(['account', 'category', 'cashOrder'])->find($arguments['id']);
                if (!$tx) return;

                $result = app(CoreDigifyService::class)->sendPayment($tx);
                $tx->update([
                    'coredigify_sent_at'    => $result['success'] ? now() : null,
                    'coredigify_sync_error' => $result['error'],
                ]);

                $this->loadTransactions();

                if ($result['success']) {
                    Notification::make()->title('Veiksmīgi nosūtīts')->success()->send();
                } else {
                    Notification::make()->title('Kļūda: ' . $result['error'])->danger()->send();
                }
            });
    }

    public function testConnectionAction(): Action
    {
        return Action::make('testConnection')
            ->label('Savienojuma tests')
            ->icon('heroicon-o-signal')
            ->color('gray')
            ->action(function () {
                $result = app(CoreDigifyService::class)->testConnection();

                if ($result['success']) {
                    Notification::make()
                        ->title('Savienojums veiksmīgs')
                        ->success()
                        ->send();
                } else {
                    $url = \App\Models\AppSetting::getRaw('coredigify_api_url');
                    
                    Notification::make()
                        ->title('Savienojuma kļūda')
                        ->body("**URL:** {$url}\n\n**Error:** {$result['error']}")
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    public function getActions(): array
    {
        return [
            $this->testConnectionAction(),
            $this->syncPendingAction(),
            $this->syncErrorsAction(),
            $this->resendTransactionAction(),
        ];
    }

    public function runSyncPending(): void
    {
        $transactions = Transaction::with(['account', 'category', 'cashOrder'])
            ->where('type', 'INCOME')
            ->where('status', 'COMPLETED')
            ->whereHas('category', fn ($q) => $q->whereIn('vid_column', self::VID_COLS))
            ->whereNull('coredigify_sent_at')
            ->whereNull('coredigify_sync_error')
            ->whereYear('occurred_at', $this->filterYear)
            ->get();

        $result = app(CoreDigifyService::class)->sendBatch($transactions);
        $this->loadTransactions();

        Notification::make()
            ->title("Sinhronizēti: {$result['sent']}, kļūdas: " . count($result['errors']))
            ->{empty($result['errors']) ? 'success' : 'warning'}()
            ->send();
    }

    public function runSyncErrors(): void
    {
        $transactions = Transaction::with(['account', 'category', 'cashOrder'])
            ->where('type', 'INCOME')
            ->where('status', 'COMPLETED')
            ->whereHas('category', fn ($q) => $q->whereIn('vid_column', self::VID_COLS))
            ->whereNotNull('coredigify_sync_error')
            ->whereNull('coredigify_sent_at')
            ->whereYear('occurred_at', $this->filterYear)
            ->get();

        $result = app(CoreDigifyService::class)->sendBatch($transactions);
        $this->loadTransactions();

        Notification::make()
            ->title("Atkārtots: {$result['sent']} veiksmīgi, kļūdas: " . count($result['errors']))
            ->{empty($result['errors']) ? 'success' : 'warning'}()
            ->send();
    }
}
