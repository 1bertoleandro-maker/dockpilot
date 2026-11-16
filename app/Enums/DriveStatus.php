<?php

declare(strict_types=1);

namespace App\Enums;

enum DriveStatus: string
{
    case DrivePending = 'drive_pending';
    case DriveSynced = 'drive_synced';
    case DriveFailed = 'drive_failed';
}
