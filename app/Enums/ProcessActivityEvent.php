<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcessActivityEvent: string
{
    case ProcessCreated = 'process_created';
    case DocumentUploaded = 'document_uploaded';
    case DocumentClassified = 'document_classified';
    case ExtractionStarted = 'extraction_started';
    case ExtractionDone = 'extraction_done';
    case ValidationRun = 'validation_run';
    case MessagePosted = 'message_posted';
    case Approved = 'approved';
    case IngestFromEmail = 'ingest_from_email';
    case DriveFolderPrepared = 'drive_folder_prepared';
    case ChecklistUpdated = 'checklist_updated';

    public function label(): string
    {
        return match ($this) {
            self::ProcessCreated => 'Processo criado',
            self::DocumentUploaded => 'Documento enviado',
            self::DocumentClassified => 'Documento classificado',
            self::ExtractionStarted => 'Extração iniciada',
            self::ExtractionDone => 'Extração concluída',
            self::ValidationRun => 'Validação cruzada',
            self::MessagePosted => 'Mensagem',
            self::Approved => 'Aprovado',
            self::IngestFromEmail => 'Ingestão por e-mail',
            self::DriveFolderPrepared => 'Pasta Drive',
            self::ChecklistUpdated => 'Checklist',
        };
    }
}
