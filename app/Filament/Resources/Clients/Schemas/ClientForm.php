<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Razão social / Nome')
                    ->placeholder('Ex.: ACME Importações Ltda')
                    ->required()
                    ->maxLength(255),
                TextInput::make('document_number')
                    ->label('CNPJ / CPF')
                    ->placeholder('00.000.000/0000-00')
                    ->maxLength(32),
                TextInput::make('email')
                    ->label('E-mail de contato')
                    ->email()
                    ->placeholder('contato@cliente.com')
                    ->maxLength(255),
            ]);
    }
}
