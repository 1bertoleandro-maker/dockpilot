<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Operator = 'operator';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Operator => 'Operador',
            self::Client => 'Usuário do cliente',
        };
    }

    public function isCompanyStaff(): bool
    {
        return $this === self::Admin || $this === self::Operator;
    }
}
