<?php

namespace App\Exceptions;

use RuntimeException;

class ConversationHandoffRequired extends RuntimeException
{
    public function __construct(public readonly string $reason = 'qualification_limit_reached')
    {
        parent::__construct('La conversazione richiede l’intervento di un commerciale.');
    }
}

