<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Handler;

use JardisAdapter\Mailer\Data\MailMessage;

/**
 * Applies default From address when not set on the message.
 */
final class DefaultFrom
{
    public function __construct(
        private readonly string $address,
        private readonly ?string $name = null,
    ) {
    }

    public function __invoke(MailMessage $message): MailMessage
    {
        if ($message->fromAddress !== null) {
            return $message;
        }

        return $message->withFrom($this->address, $this->name);
    }
}
