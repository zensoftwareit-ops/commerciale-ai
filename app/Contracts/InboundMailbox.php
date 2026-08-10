<?php

namespace App\Contracts;

use App\Data\InboundEmailMessage;

interface InboundMailbox
{
    public function testConnection(): void;

    /** @return iterable<InboundEmailMessage> */
    public function recent(int $limit): iterable;

    public function markSeen(string $identifier): void;

    public function close(): void;
}
