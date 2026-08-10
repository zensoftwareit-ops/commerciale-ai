<?php

namespace App\Data;

use Carbon\CarbonImmutable;

readonly class InboundEmailMessage
{
    /** @param list<string> $references */
    public function __construct(
        public string $identifier,
        public ?string $messageId,
        public ?string $inReplyTo,
        public array $references,
        public string $fromAddress,
        public ?string $fromName,
        public string $subject,
        public string $body,
        public CarbonImmutable $receivedAt,
        public bool $automated = false,
    ) {}
}
