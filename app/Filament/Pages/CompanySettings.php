<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Support\CurrentCompany;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CompanySettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?string $navigationLabel = 'Empresa';

    protected static ?string $title = 'Configurações da empresa';

    protected static ?string $slug = 'company-settings';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.company-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        return $user !== null && $user->role === UserRole::Admin;
    }

    public function mount(): void
    {
        $company = $this->company();

        $validation = is_array($company->settings['validation'] ?? null)
            ? $company->settings['validation']
            : [];
        $mailbox = is_array($company->settings['mailbox'] ?? null)
            ? $company->settings['mailbox']
            : [];

        $this->form->fill([
            'name' => $company->name,
            'drive_folder_root' => $company->drive_folder_root,
            'gross_weight_tolerance_pct' => $validation['gross_weight_tolerance_pct'] ?? 1.0,
            'invoice_total_tolerance_pct' => $validation['invoice_total_tolerance_pct'] ?? 0.5,
            'mailbox_enabled' => (bool) ($mailbox['enabled'] ?? false),
            'mailbox_address' => $mailbox['address'] ?? '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identidade')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome da empresa de despacho')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('drive_folder_root')
                            ->label('Pasta raiz Google Drive')
                            ->placeholder('DockPilot / ID da pasta')
                            ->helperText('Caminho lógico usado na preparação de pastas (API Google na próxima etapa).'),
                    ]),
                Section::make('Caixa de e-mail (ingestão)')
                    ->description('Hoje a ingestão lê storage/app/inbound. IMAP/Outlook usará este endereço.')
                    ->schema([
                        Toggle::make('mailbox_enabled')
                            ->label('Ingestão automática habilitada')
                            ->inline(false),
                        TextInput::make('mailbox_address')
                            ->label('E-mail da caixa Comex')
                            ->email()
                            ->placeholder('docs@sua-despachante.com.br'),
                    ]),
                Section::make('Tolerâncias de validação cruzada')
                    ->description('Controla quando a IA / regras geram fail vs. ok numérico.')
                    ->schema([
                        TextInput::make('gross_weight_tolerance_pct')
                            ->label('Peso bruto — tolerância (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required()
                            ->suffix('%'),
                        TextInput::make('invoice_total_tolerance_pct')
                            ->label('Total Invoice — tolerância (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required()
                            ->suffix('%'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $company = $this->company();

        $settings = is_array($company->settings) ? $company->settings : [];
        $settings['validation'] = [
            'gross_weight_tolerance_pct' => (float) $state['gross_weight_tolerance_pct'],
            'invoice_total_tolerance_pct' => (float) $state['invoice_total_tolerance_pct'],
        ];
        $settings['mailbox'] = [
            'enabled' => (bool) ($state['mailbox_enabled'] ?? false),
            'address' => $state['mailbox_address'] ?: null,
        ];

        $company->update([
            'name' => $state['name'],
            'drive_folder_root' => $state['drive_folder_root'] ?: null,
            'settings' => $settings,
        ]);

        Notification::make()
            ->title('Configurações salvas')
            ->success()
            ->send();
    }

    private function company(): Company
    {
        $id = CurrentCompany::id() ?? (int) Filament::auth()->user()?->company_id;

        return Company::query()->findOrFail($id);
    }
}
