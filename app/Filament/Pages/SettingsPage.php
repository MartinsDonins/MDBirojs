<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SettingsPage extends Page implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static string $view = 'filament.pages.settings';

    protected static ?string $navigationLabel = 'Iestatījumi';
    protected static ?string $navigationIcon  = 'heroicon-o-cog-6-tooth';
    protected static ?string $title           = 'Iestatījumi';
    protected static ?int    $navigationSort  = 99;

    // ── Tab state ─────────────────────────────────────────────────────────────
    public string $activeTab = 'profile';

    // ── Profile form ──────────────────────────────────────────────────────────
    public ?array $profileData = [];

    // ── CoreDigify settings form ───────────────────────────────────────────────
    public ?array $coredigifyData = [];

    // ── User management ────────────────────────────────────────────────────────
    public array $users = [];

    // ── New user modal ─────────────────────────────────────────────────────────
    public ?array $newUserData = [];

    // ── Change password modal ──────────────────────────────────────────────────
    public ?int   $changePasswordUserId = null;
    public ?array $changePasswordData   = [];

    public function mount(): void
    {
        $this->profileForm->fill([
            'name'  => Auth::user()->name,
            'email' => Auth::user()->email,
        ]);

        $this->coredigifyForm->fill([
            'enabled'      => (bool) AppSetting::get('coredigify_enabled', false),
            'api_url'      => AppSetting::getRaw('coredigify_api_url'),
            'api_key'      => AppSetting::getRaw('coredigify_api_key'),
            'incoming_key' => AppSetting::getRaw('coredigify_incoming_key'),
        ]);

        $this->loadUsers();
    }

    private function loadUsers(): void
    {
        $this->users = User::orderBy('name')->get(['id', 'name', 'email', 'created_at'])->toArray();
    }

    // ── Forms ─────────────────────────────────────────────────────────────────

    protected function getForms(): array
    {
        return ['profileForm', 'coredigifyForm', 'newUserForm', 'changePasswordForm'];
    }

    public function profileForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Vārds')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('E-pasts')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->label('Jauna parole')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->nullable(),
                Forms\Components\TextInput::make('password_confirmation')
                    ->label('Apstiprināt paroli')
                    ->password()
                    ->revealable()
                    ->same('password')
                    ->nullable(),
            ])
            ->statePath('profileData');
    }

    public function saveProfile(): void
    {
        $data = $this->profileForm->getState();

        $user = Auth::user();
        $user->name  = $data['name'];
        $user->email = $data['email'];

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        $this->profileForm->fill([
            'name'                  => $user->name,
            'email'                 => $user->email,
            'password'              => null,
            'password_confirmation' => null,
        ]);

        Notification::make()->title('Profils saglabāts')->success()->send();
    }

    public function coredigifyForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('enabled')
                    ->label('Iespējot CoreDigify integrāciju')
                    ->inline(false),
                Forms\Components\TextInput::make('api_url')
                    ->label('CoreDigify API URL (nosūtīšana uz CoreDigify)')
                    ->placeholder('https://my.coredigify.com/api/payments/incoming')
                    ->url()
                    ->maxLength(500),
                Forms\Components\TextInput::make('api_key')
                    ->label('CoreDigify API atslēga (izejošā)')
                    ->password()
                    ->revealable()
                    ->maxLength(500)
                    ->helperText('Atslēga, ko MDBirojs izmanto, lai autentificētos CoreDigify sistēmā'),
                Forms\Components\TextInput::make('incoming_key')
                    ->label('MDBirojs API atslēga (ienākošā)')
                    ->readOnly()
                    ->helperText('Šo atslēgu ievadiet CoreDigify sistēmā, lai tā varētu pieprasīt datus no MDBirojs')
                    ->suffixAction(
                        Forms\Components\Actions\Action::make('regenerate_key')
                            ->label('Reģenerēt')
                            ->icon('heroicon-o-arrow-path')
                            ->color('warning')
                            ->requiresConfirmation()
                            ->modalDescription('Jaunā atslēga aizstās esošo. CoreDigify sistēmā būs jāievada jaunā atslēga.')
                            ->action(function (Forms\Set $set) {
                                $newKey = (string) Str::uuid();
                                AppSetting::set('coredigify_incoming_key', $newKey);
                                $set('incoming_key', $newKey);
                                Notification::make()->title('API atslēga reģenerēta')->warning()->send();
                            })
                    ),
            ])
            ->statePath('coredigifyData');
    }

    public function saveCoredigify(): void
    {
        $data = $this->coredigifyForm->getState();

        AppSetting::set('coredigify_enabled', $data['enabled'] ? '1' : '0');
        AppSetting::set('coredigify_api_url', $data['api_url'] ?? '');
        AppSetting::set('coredigify_api_key', $data['api_key'] ?? '');
        // incoming_key is read-only; only updated via regenerate action

        Notification::make()->title('CoreDigify iestatījumi saglabāti')->success()->send();
    }

    // ── New user form / modal ─────────────────────────────────────────────────

    public function newUserForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Vārds')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('E-pasts')
                    ->email()
                    ->required()
                    ->unique(User::class, 'email')
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->label('Parole')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8),
            ])
            ->statePath('newUserData');
    }

    public function changePasswordForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('password')
                    ->label('Jauna parole')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8),
                Forms\Components\TextInput::make('password_confirmation')
                    ->label('Apstiprināt paroli')
                    ->password()
                    ->revealable()
                    ->same('password')
                    ->required(),
            ])
            ->statePath('changePasswordData');
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function createUserAction(): Action
    {
        return Action::make('createUser')
            ->label('Jauns lietotājs')
            ->icon('heroicon-o-user-plus')
            ->color('success')
            ->form($this->newUserForm(Form::make($this))->getComponents())
            ->action(function (array $data) {
                User::create([
                    'name'     => $data['name'],
                    'email'    => $data['email'],
                    'password' => Hash::make($data['password']),
                ]);
                $this->loadUsers();
                Notification::make()->title("Lietotājs '{$data['name']}' izveidots")->success()->send();
            });
    }

    public function changePasswordAction(): Action
    {
        return Action::make('changePassword')
            ->label('Mainīt paroli')
            ->icon('heroicon-o-key')
            ->color('warning')
            ->form([
                Forms\Components\TextInput::make('password')
                    ->label('Jauna parole')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8),
                Forms\Components\TextInput::make('password_confirmation')
                    ->label('Apstiprināt paroli')
                    ->password()
                    ->revealable()
                    ->same('password')
                    ->required(),
            ])
            ->action(function (array $data, array $arguments) {
                $user = User::findOrFail($arguments['userId']);
                $user->update(['password' => Hash::make($data['password'])]);
                Notification::make()->title("Parole mainīta lietotājam '{$user->name}'")->success()->send();
            });
    }

    public function deleteUserAction(): Action
    {
        return Action::make('deleteUser')
            ->label('Dzēst')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Vai tiešām dzēst šo lietotāju? Šī darbība nav atgriezeniska.')
            ->action(function (array $arguments) {
                if ((int) $arguments['userId'] === Auth::id()) {
                    Notification::make()->title('Nevar dzēst sevi')->danger()->send();
                    return;
                }
                $user = User::findOrFail($arguments['userId']);
                $name = $user->name;
                $user->delete();
                $this->loadUsers();
                Notification::make()->title("Lietotājs '{$name}' dzēsts")->success()->send();
            });
    }

    public function getActions(): array
    {
        return [
            $this->createUserAction(),
            $this->changePasswordAction(),
            $this->deleteUserAction(),
        ];
    }
}
