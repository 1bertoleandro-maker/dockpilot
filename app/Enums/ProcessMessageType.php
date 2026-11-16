<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcessMessageType: string
{
    case Observation = 'observation';
    case Request = 'request';
    case Reply = 'reply';

    public function label(): string
    {
        return match ($this) {
            self::Observation => 'Observação',
            self::Request => 'Pedido',
            self::Reply => 'Resposta',
        };
    }
}
