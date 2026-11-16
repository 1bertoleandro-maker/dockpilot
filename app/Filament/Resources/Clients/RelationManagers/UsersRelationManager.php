<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Usuários do cliente (portal)';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Criado')
                    ->since(),
            ])
            ->headerActions([
                Action::make('createPortalUser')
                    ->label('Criar usuário do portal')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('E-mail de acesso')
                            ->email()
                            ->required()
                            ->unique(User::class, 'email')
                            ->maxLength(255),
                        TextInput::make('password')
                            ->label('Senha temporária')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->helperText('O cliente usa este login em /portal'),
                    ])
                    ->action(function (array $data): void {
                        /** @var Client $client */
                        $client = $this->getOwnerRecord();

                        User::query()->create([
                            'company_id' => $client->company_id,
                            'client_id' => $client->id,
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'password' => Hash::make($data['password']),
                            'role' => UserRole::Client,
                            'email_verified_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Usuário do portal criado')
                            ->body('Acesso: '.url('/portal').' · '.$data['email'])
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('resetPassword')
                    ->label('Nova senha')
                    ->icon('heroicon-o-key')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $password = Str::password(12);

                        $record->update([
                            'password' => Hash::make($password),
                        ]);

                        Notification::make()
                            ->title('Senha redefinida')
                            ->body('Nova senha temporária: '.$password)
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                DeleteAction::make()->label('Remover'),
            ])
            ->emptyStateHeading('Nenhum usuário do portal')
            ->emptyStateDescription('Crie logins para o cliente acompanhar os processos em /portal.');
    }
}
