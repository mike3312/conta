<?php

namespace App\Exceptions;

use InvalidArgumentException;
use Throwable;

class FelImportException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly string $stage,
        string $publicMessage,
        ?Throwable $previous = null,
    ) {
        parent::__construct($publicMessage, 0, $previous);
    }
}
