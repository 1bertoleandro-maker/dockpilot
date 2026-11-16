<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\ComexProcesses\ComexProcessResource;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use App\Services\Ingest\EmailIngestService;
use App\Support\CurrentCompany;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\File;
use UnitEnum;

class InboundInbox extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Operação';

    protected static ?string $navigationLabel = 'Caixa de entrada';

    protected static ?string $title = 'Ingestão automática';

    protected static ?string $slug = 'inbound-inbox';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.inbound-inbox';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        return $user !== null && $user->role->isCompanyStaff();
    }

    public function getInboxPath(): string
    {
        $companyId = CurrentCompany::id() ?? (int) Filament::auth()->user()?->company_id;

        return storage_path('app/inbound/'.$companyId);
    }

    /**
     * @return list<string>
     */
    public function pendingFiles(): array
    {
        $path = $this->getInboxPath();
        if (! is_dir($path)) {
            return [];
        }

        $files = [];
        foreach (File::allFiles($path) as $file) {
            if (str_contains($file->getPath(), DIRECTORY_SEPARATOR.'_processed')) {
                continue;
            }
            if (strtolower($file->getExtension()) !== 'pdf') {
                continue;
            }
            $files[] = str_replace($path.DIRECTORY_SEPARATOR, '', $file->getPathname());
        }

        return $files;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('processInbox')
                ->label('Processar caixa')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Processar PDFs da caixa de entrada?')
                ->modalDescription('Cria processos, classifica documentos, dispara IA e atualiza o checklist.')
                ->action(function (EmailIngestService $ingest): void {
                    $company = $this->company();
                    /** @var User $user */
                    $user = Filament::auth()->user();
                    $result = $ingest->poll($company, $user);

                    Notification::make()
                        ->title('Ingestão concluída')
                        ->body(sprintf(
                            '%d arquivo(s) · %d processo(s) criado(s) · %d ignorado(s)',
                            $result['fetched'],
                            $result['created_processes'],
                            $result['skipped']
                        ))
                        ->success()
                        ->send();

                    if (($result['process_ids'][0] ?? null) !== null) {
                        $this->redirect(ComexProcessResource::getUrl('view', [
                            'record' => $result['process_ids'][0],
                        ]));
                    }
                }),
            Action::make('simulate')
                ->label('Simular e-mail demo')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->form([
                    Select::make('client_id')
                        ->label('Cliente')
                        ->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data, EmailIngestService $ingest): void {
                    $company = $this->company();
                    $client = Client::query()->findOrFail($data['client_id']);
                    /** @var User $user */
                    $user = Filament::auth()->user();

                    $result = $ingest->simulateDemo($company, $client, $user);

                    Notification::make()
                        ->title('Simulação executada')
                        ->body(sprintf(
                            '%d PDFs · processo(s): %s',
                            $result['fetched'],
                            implode(', ', $result['process_ids'] ?: ['—'])
                        ))
                        ->success()
                        ->send();

                    if (($result['process_ids'][0] ?? null) !== null) {
                        $this->redirect(ComexProcessResource::getUrl('view', [
                            'record' => $result['process_ids'][0],
                        ]));
                    }
                }),
        ];
    }

    private function company(): Company
    {
        $id = CurrentCompany::id() ?? (int) Filament::auth()->user()?->company_id;

        return Company::query()->findOrFail($id);
    }
}
