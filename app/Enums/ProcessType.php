<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcessType: string
{
    case Import = 'import';
    case Export = 'export';

    public function label(): string
    {
        return match ($this) {
            self::Import => 'Importação',
            self::Export => 'Exportação',
        };
    }
}
