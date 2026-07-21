<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Data;

/**
 * Envelope value object — the wire-level representation of a mail message.
 */
final readonly class Envelope
{
    /**
     * @param list<string> $recipients
     */
    public function __construct(
        public string $sender,
        public array $recipients,
        public string $rawMessage,
    ) {
    }
}
