<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcessStatus: string
{
    case Draft = 'draft';
    case DocumentsPending = 'documents_pending';
    case Extracting = 'extracting';
    case Review = 'review';
    case Approved = 'approved';
    case ReadyForSiscomex = 'ready_for_siscomex';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::DocumentsPending => 'Aguardando documentos',
            self::Extracting => 'Extraindo com IA',
            self::Review => 'Em revisão',
            self::Approved => 'Aprovado',
            self::ReadyForSiscomex => 'Pronto p/ Siscomex',
        };
    }
}
