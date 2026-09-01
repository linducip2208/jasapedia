<?php

namespace App\Domain\Common\Exceptions;

use App\Domain\Auth\DomainException;

class StateTransitionException extends DomainException
{
    public function __construct(string $from, string $to, string $entity = 'entity')
    {
        parent::__construct(
            "Illegal {$entity} transition: {$from} → {$to}.",
            'ILLEGAL_STATE_TRANSITION',
            409,
            ['from' => $from, 'to' => $to],
        );
    }
}
