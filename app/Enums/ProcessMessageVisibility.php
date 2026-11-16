<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcessMessageVisibility: string
{
    case Internal = 'internal';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Só equipe do despachante',
            self::Client => 'Visível ao cliente',
        };
    }
}
