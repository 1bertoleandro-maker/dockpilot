<?php

declare(strict_types=1);

namespace App\Enums;

enum ValidationSeverity: string
{
    case Fail = 'fail';
    case Warn = 'warn';
    case Info = 'info';
}
