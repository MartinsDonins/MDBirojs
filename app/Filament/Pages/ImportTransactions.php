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
    
    protected static ?string $navigationLabel = 'Import Transactions';
    
    protected static ?string $title = 'Import Bank Statements';

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
                Forms\Components\Section::make('Import Bank Statement')
                    ->description('Upload your bank statement file and select the format')
                    ->schema([
                        Forms\Components\Select::make('account_id')
                            ->label('Account')
                            ->options(Account::all()->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->helperText('Select the account to import transactions into'),
                        
                        Forms\Components\Select::make('source')
                            ->label('Bank Format')
                            ->options([
                                'SWED' => 'Swedbank (ISO 20022 XML)',
                                'SEB' => 'SEB (ISO 20022 XML)',
                                'PAYPAL' => 'PayPal',
                            ])
                            ->required()
                            ->helperText('Select your bank or payment provider'),
                        
                        Forms\Components\FileUpload::make('file')
                            ->label('Statement File')
                            ->required()
                            ->acceptedFileTypes(['text/xml', 'application/xml'])
                            ->maxSize(10240) // 10MB
                            ->helperText('Upload ISO 20022 XML file from your bank')
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
            $message = "Import completed: {$stats['imported']} imported, {$stats['skipped']} skipped";
            
            if (!empty($stats['errors'])) {
                $message .= ", " . count($stats['errors']) . " errors";
                
                Notification::make()
                    ->title('Import completed with errors')
                    ->body($message)
                    ->warning()
                    ->send();
            } else {
                Notification::make()
                    ->title('Import successful')
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
                ->title('Import failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('import')
                ->label('Import Transactions')
                ->submit('import')
                ->color('primary'),
        ];
    }
}
