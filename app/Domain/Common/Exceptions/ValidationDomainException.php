<?php

namespace App\Domain\Common\Exceptions;

use App\Domain\Auth\DomainException;

class ValidationDomainException extends DomainException
{
    public function __construct(string $message, array $details = [])
    {
        parent::__construct($message, 'BUSINESS_RULE_VIOLATION', 422, $details);
    }
}
