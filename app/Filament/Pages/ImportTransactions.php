<?php

namespace App\Filament\Pages;

use App\Models\Account;
use App\Services\TransactionImportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class ImportTransactions extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    
    protected static ?string $navigationLabel = 'Darījumu imports';
    
    protected static ?string $title = 'Bankas izrakstu imports';

    protected static string $view = 'filament.pages.import-transactions';
    
    protected static ?int $navigationSort = 2;

    public ?array $data = [];
    
    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Bankas izraksta imports')
                    ->description('Augšupielādējiet bankas izraksta failu un izvēlieties formātu')
                    ->schema([
                        Forms\Components\Select::make('account_id')
                            ->label('Konts')
                            ->options(Account::all()->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->helperText('Izvēlieties kontu, kurā importēt darījumus'),
                        
                        Forms\Components\Select::make('source')
                            ->label('Bankas formāts')
                            ->options([
                                'SWED' => 'Swedbank (ISO 20022 XML)',
                                'SEB' => 'SEB (ISO 20022 XML)',
                                'PAYPAL' => 'PayPal',
                            ])
                            ->required()
                            ->helperText('Izvēlieties banku vai maksājumu pakalpojumu sniedzēju'),
                        
                        Forms\Components\FileUpload::make('file')
                            ->label('Izraksta fails')
                            ->required()
                            ->acceptedFileTypes(['text/xml', 'application/xml'])
                            ->maxSize(10240) // 10MB
                            ->helperText('Augšupielādējiet ISO 20022 XML failu no savas bankas')
                            ->disk('local')
                            ->directory('imports')
                            ->visibility('private'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function import(): void
    {
        $data = $this->form->getState();
        
        try {
            // Get the uploaded file path
            $filePath = Storage::disk('local')->path($data['file']);
            
            // Import transactions
            $importService = app(TransactionImportService::class);
            $stats = $importService->importFromXml(
                $filePath,
                $data['source'],
                $data['account_id']
            );
            
            // Show success notification
            $message = "Imports pabeigts: {$stats['imported']} importēti, {$stats['skipped']} izlaisti";
            
            if (!empty($stats['auto_approved'])) {
                $message .= ", {$stats['auto_approved']} automātiski apstiprināti";
            }
            
            if (!empty($stats['errors'])) {
                $message .= ", " . count($stats['errors']) . " kļūdas";
                
                Notification::make()
                    ->title('Imports pabeigts ar kļūdām')
                    ->body($message)
                    ->warning()
                    ->send();
            } else {
                Notification::make()
                    ->title('Imports veiksmīgs')
                    ->body($message)
                    ->success()
                    ->send();
            }
            
            // Clear form
            $this->form->fill();
            
            // Redirect to transactions list
            $this->redirect(route('filament.admin.resources.transactions.index'));
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Neizdevās importēt')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('import')
                ->label('Importēt darījumus')
                ->submit('import')
                ->color('primary'),
        ];
    }
}
