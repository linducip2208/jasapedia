<?php

namespace App\Domain\Auth;

use RuntimeException;

class DomainException extends RuntimeException
{
    public function __construct(
        string $message,
        protected string $errorCode = 'DOMAIN_ERROR',
        protected int $status = 400,
        protected array $exceptionDetails = [],
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function details(): array
    {
        return $this->exceptionDetails;
    }
}
