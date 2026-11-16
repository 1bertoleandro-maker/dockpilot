<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Stored = 'stored';
    case QueuedAi = 'queued_ai';
    case Extracted = 'extracted';
    case ValidationDone = 'validation_done';
    case Approved = 'approved';
    case NeedsFix = 'needs_fix';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Enviado',
            self::Stored => 'Armazenado',
            self::QueuedAi => 'Na fila da IA',
            self::Extracted => 'Extraído',
            self::ValidationDone => 'Validado',
            self::Approved => 'Aprovado',
            self::NeedsFix => 'Precisa correção',
        };
    }
}
