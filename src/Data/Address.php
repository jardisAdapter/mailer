<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Data;

/**
 * Email address value object with optional display name.
 */
final readonly class Address
{
    public function __construct(
        public string $email,
        public ?string $name = null,
    ) {
    }
}
