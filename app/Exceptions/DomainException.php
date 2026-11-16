<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class DomainException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        int $httpStatus = 422,
    ) {
        parent::__construct($message, $httpStatus);
    }
}
