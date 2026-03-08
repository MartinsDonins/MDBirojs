<?php

namespace App\Filament\Pages;

use App\Models\Account;
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

    public const RULE_NAME      = '⚙ Maksājumi starp kontiem';
    public const KASE_RULE_NAME = '⚙ Kases darījumi';

    public ?array $data = [];

    public function mount(): void
    {
        $rule     = Rule::where('name', self::RULE_NAME)->first();
        $kaseRule = Rule::where('name', self::KASE_RULE_NAME)->first();

        // Extract keywords from or_criteria for the main rule
        $keywords = $this->extractKeywords($rule);
        // Extract keywords from or_criteria for the kase rule
        $kaseKeywords = $this->extractKeywords($kaseRule);

        $this->form->fill([
            // --- Starp kontiem ---
            'is_active'           => $rule?->is_active ?? true,
            'keywords'            => $keywords ?: [['keyword' => 'Maksājums starp saviem kontiem']],
            'income_category_id'  => $rule?->action['income_category_id']  ?? null,
            'expense_category_id' => $rule?->action['expense_category_id'] ?? null,
            'auto_link_matching'  => $rule?->action['auto_link_matching']  ?? true,

            // --- Kases darījumi ---
            'kase_is_active'           => $kaseRule?->is_active ?? false,
            'kase_account_id'          => $kaseRule?->action['reverse_account_id'] ?? null,
            'kase_keywords'            => $kaseKeywords ?: [
                ['keyword' => 'Naudas pārskaitīšana no kases uz kontu'],
                ['keyword' => 'Naudas pārskaitīšana no Konta uz kasi'],
            ],
            'kase_income_category_id'  => $kaseRule?->action['income_category_id']  ?? null,
            'kase_expense_category_id' => $kaseRule?->action['expense_category_id'] ?? null,
        ]);
    }

    private function extractKeywords(?Rule $rule): array
    {
        $keywords = [];
        foreach ($rule?->criteria['or_criteria'] ?? [] as $c) {
            if (($c['field'] ?? '') === 'description' && !empty($c['value'])) {
                $keywords[] = ['keyword' => $c['value']];
            }
        }
        return $keywords;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([

                // =====================================================
                // BLOKS 1: PĀRSKAITĪJUMI STARP SAVIEM BANKAS KONTIEM
                // =====================================================
                Forms\Components\Section::make('Pārskaitījumi starp saviem kontiem')
                    ->description('Automātiski klasificē darījumus, kur nauda tiek pārvietota no viena sava bankas konta uz citu. Atpazīšana notiek pēc apraksta atslēgvārdiem un/vai konta numura sakritības.')
                    ->icon('heroicon-o-building-library')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktīvs')
                            ->helperText('Darbojas importa laikā un manuāli izpildot "Izpildīt kārtulas".')
                            ->default(true)
                            ->columnSpanFull(),

                        Forms\Components\Repeater::make('keywords')
                            ->label('Atslēgvārdi aprakstā')
                            ->schema([
                                Forms\Components\TextInput::make('keyword')
                                    ->label('Atslēgvārds')
                                    ->required()
                                    ->placeholder('Piemēram: Maksājums starp saviem kontiem'),
                            ])
                            ->addActionLabel('+ Pievienot atslēgvārdu')
                            ->defaultItems(1)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('income_category_id')
                            ->label('Kategorija — nauda ienāk')
                            ->options(Category::whereIn('type', ['INCOME', 'TRANSFER'])->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->helperText('Darījums, kur nauda IENĀK šajā kontā no cita konta.'),

                        Forms\Components\Select::make('expense_category_id')
                            ->label('Kategorija — nauda iziet')
                            ->options(Category::whereIn('type', ['EXPENSE', 'FEE', 'TRANSFER'])->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->helperText('Darījums, kur nauda IZIET no šī konta uz citu kontu.'),

                        Forms\Components\Toggle::make('auto_link_matching')
                            ->label('Auto-sasaiste: saistīt abus darījumus')
                            ->helperText('Meklēs atbilstošu darījumu citā kontā (±1 diena, vienāda summa, vienāds apraksts) un izveidos savstarpējo sasaisti.')
                            ->default(true)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // =====================================================
                // BLOKS 2: KASES DARĪJUMI
                // =====================================================
                Forms\Components\Section::make('Kases darījumi')
                    ->description('Kad bankas kontā parādās darījums ar pārskaitījumu uz/no kases, sistēma automātiski izveido pretējo darījumu kases kontā un saista abus. Piemēram: banka rāda izdevumu "Nauda uz kasi" → kases kontā tiek izveidots ieņēmums.')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Forms\Components\Toggle::make('kase_is_active')
                            ->label('Aktīvs')
                            ->helperText('Darbojas importa laikā un manuāli izpildot "Izpildīt kārtulas".')
                            ->default(false)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('kase_account_id')
                            ->label('Kases konts')
                            ->options(Account::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->required(false)
                            ->helperText('Norādiet, kurš konts ir Kase. Pretējais darījums tiks izveidots šajā kontā.')
                            ->columnSpanFull(),

                        Forms\Components\Repeater::make('kase_keywords')
                            ->label('Atslēgvārdi aprakstā (kases darījumu atpazīšanai)')
                            ->schema([
                                Forms\Components\TextInput::make('keyword')
                                    ->label('Atslēgvārds')
                                    ->required()
                                    ->placeholder('Piemēram: Naudas pārskaitīšana no kases uz kontu'),
                            ])
                            ->addActionLabel('+ Pievienot atslēgvārdu')
                            ->defaultItems(0)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('kase_income_category_id')
                            ->label('Kategorija — ieņēmumu darījumam')
                            ->options(Category::whereIn('type', ['INCOME', 'TRANSFER'])->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->helperText('Piemēro INCOME tipam (nauda ienāk no kases).'),

                        Forms\Components\Select::make('kase_expense_category_id')
                            ->label('Kategorija — izdevumu darījumam')
                            ->options(Category::whereIn('type', ['EXPENSE', 'FEE', 'TRANSFER'])->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->helperText('Piemēro EXPENSE tipam (nauda iziet uz kasi).'),
                    ])
                    ->columns(2),

            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // --- Save main rule ---
        $orCriteria = $this->buildOrCriteria($data['keywords'] ?? []);
        Rule::updateOrCreate(
            ['name' => self::RULE_NAME],
            [
                'priority'  => 90,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'criteria'  => ['and_criteria' => [], 'or_criteria' => $orCriteria],
                'action'    => [
                    'income_category_id'  => !empty($data['income_category_id'])  ? (int) $data['income_category_id']  : null,
                    'expense_category_id' => !empty($data['expense_category_id']) ? (int) $data['expense_category_id'] : null,
                    'auto_link_matching'  => (bool) ($data['auto_link_matching'] ?? true),
                ],
            ]
        );

        // --- Save kase rule ---
        $kaseOrCriteria = $this->buildOrCriteria($data['kase_keywords'] ?? []);
        Rule::updateOrCreate(
            ['name' => self::KASE_RULE_NAME],
            [
                'priority'  => 85,
                'is_active' => (bool) ($data['kase_is_active'] ?? false),
                'criteria'  => ['and_criteria' => [], 'or_criteria' => $kaseOrCriteria],
                'action'    => [
                    'reverse_account_id'  => !empty($data['kase_account_id'])          ? (int) $data['kase_account_id']          : null,
                    'income_category_id'  => !empty($data['kase_income_category_id'])  ? (int) $data['kase_income_category_id']  : null,
                    'expense_category_id' => !empty($data['kase_expense_category_id']) ? (int) $data['kase_expense_category_id'] : null,
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

        $service      = app(AutoApprovalService::class);
        $totalApplied = 0;
        $totalProcessed = 0;

        foreach ([self::RULE_NAME, self::KASE_RULE_NAME] as $ruleName) {
            $rule = Rule::where('name', $ruleName)->where('is_active', true)->first();
            if (! $rule) {
                continue;
            }
            $stats = $service->applyCustomRule($rule);
            $totalApplied   += $stats['applied'];
            $totalProcessed += $stats['processed'];
        }

        Notification::make()
            ->title('Izpildīts')
            ->body("Pārskatīti: {$totalProcessed} darījumi, piemēroti: {$totalApplied}")
            ->success()
            ->persistent()
            ->send();
    }

    private function buildOrCriteria(array $keywords): array
    {
        $criteria = [];
        foreach ($keywords as $kw) {
            if (!empty($kw['keyword'])) {
                $criteria[] = [
                    'field'    => 'description',
                    'operator' => 'contains',
                    'value'    => trim($kw['keyword']),
                ];
            }
        }
        return $criteria;
    }
}
