<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Config;

/**
 * SMTP connection configuration value object.
 */
final readonly class SmtpConfig
{
    public function __construct(
        public string $host,
        public int $port = 587,
        public Encryption $encryption = Encryption::Tls,
        public ?string $username = null,
        public ?string $password = null,
        public int $timeout = 30,
        public ?string $fromAddress = null,
        public ?string $fromName = null,
        public int $maxRetries = 0,
        public int $retryDelayMs = 100,
        public bool $verifySsl = true,
    ) {
    }
}
