<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Rule;
use App\Services\AutoApprovalService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class InterAccountSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.inter-account-settings';

    protected static ?string $navigationLabel = 'Starp kontiem';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $title = 'Maksājumi starp kontiem';

    protected static ?string $navigationGroup = 'Kārtulas';

    protected static ?int $navigationSort = 20;

    public const RULE_NAME = '⚙ Maksājumi starp kontiem';

    public ?array $data = [];

    public function mount(): void
    {
        $rule = Rule::where('name', self::RULE_NAME)->first();

        // Extract keywords from or_criteria
        $keywords = [];
        if ($rule) {
            foreach ($rule->criteria['or_criteria'] ?? [] as $c) {
                if (($c['field'] ?? '') === 'description' && !empty($c['value'])) {
                    $keywords[] = ['keyword' => $c['value']];
                }
            }
        }

        $this->form->fill([
            'is_active'           => $rule?->is_active ?? true,
            'keywords'            => $keywords ?: [['keyword' => 'Maksājums starp saviem kontiem']],
            'income_category_id'  => $rule?->action['income_category_id'] ?? null,
            'expense_category_id' => $rule?->action['expense_category_id'] ?? null,
            'auto_link_matching'  => $rule?->action['auto_link_matching'] ?? true,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Aktivizēšana')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktīvs')
                            ->helperText('Kārtula darbosies importa laikā un manuāli izpildot "Izpildīt kārtulas".')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Atpazīšanas vārdi aprakstā')
                    ->description('Darījumi, kuru aprakstā ir kāds no šiem vārdiem, tiks automātiski klasificēti kā pārskaitījumi starp kontiem. Papildus: ja konta numurs sakrīt ar kādu no jūsu kontiem — darījums arī tiks atpazīts automātiski.')
                    ->schema([
                        Forms\Components\Repeater::make('keywords')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('keyword')
                                    ->label('Atslēgvārds aprakstā')
                                    ->required()
                                    ->placeholder('Piemēram: Maksājums starp saviem kontiem'),
                            ])
                            ->addActionLabel('+ Pievienot atslēgvārdu')
                            ->defaultItems(1)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Kategorijas')
                    ->description('Piemēros atbilstošo kategoriju atkarībā no tā, vai nauda nāk klāt vai iet prom no konta.')
                    ->schema([
                        Forms\Components\Select::make('income_category_id')
                            ->label('Kategorija — nauda ienāk (Ieņēmumu puse)')
                            ->options(Category::whereIn('type', ['INCOME', 'TRANSFER'])->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->helperText('Piemēro darījumam, kur nauda IENĀK šajā kontā no cita konta.'),

                        Forms\Components\Select::make('expense_category_id')
                            ->label('Kategorija — nauda iziet (Izdevumu puse)')
                            ->options(Category::whereIn('type', ['EXPENSE', 'FEE', 'TRANSFER'])->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->helperText('Piemēro darījumam, kur nauda IZIET no šī konta uz citu kontu.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Auto-sasaiste')
                    ->schema([
                        Forms\Components\Toggle::make('auto_link_matching')
                            ->label('Automātiski sasaistīt abu kontu darījumus')
                            ->helperText('Meklēs darījumu CITĀ kontā ar vienādu aprakstu, datumu (±1 diena) un summu, un izveidos savstarpējo sasaisti starp abiem darījumiem.')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Build or_criteria from keywords
        $orCriteria = [];
        foreach ($data['keywords'] ?? [] as $kw) {
            if (!empty($kw['keyword'])) {
                $orCriteria[] = [
                    'field'    => 'description',
                    'operator' => 'contains',
                    'value'    => trim($kw['keyword']),
                ];
            }
        }

        Rule::updateOrCreate(
            ['name' => self::RULE_NAME],
            [
                'priority'  => 90,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'criteria'  => [
                    'and_criteria' => [],
                    'or_criteria'  => $orCriteria,
                ],
                'action' => [
                    'income_category_id'  => !empty($data['income_category_id'])  ? (int) $data['income_category_id']  : null,
                    'expense_category_id' => !empty($data['expense_category_id']) ? (int) $data['expense_category_id'] : null,
                    'auto_link_matching'  => (bool) ($data['auto_link_matching'] ?? true),
                ],
            ]
        );

        Notification::make()
            ->title('Iestatījumi saglabāti')
            ->success()
            ->send();
    }

    public function saveAndRun(): void
    {
        $this->save();

        $rule = Rule::where('name', self::RULE_NAME)->first();
        if (! $rule) {
            return;
        }

        $stats = app(AutoApprovalService::class)->applyCustomRule($rule);

        Notification::make()
            ->title('Izpildīts')
            ->body("Pārskatīti: {$stats['processed']} darījumi, piemēroti: {$stats['applied']}")
            ->success()
            ->persistent()
            ->send();
    }
}
