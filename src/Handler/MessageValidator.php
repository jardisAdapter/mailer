<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Handler;

use JardisAdapter\Mailer\Data\MailMessage;

/**
 * Validates a mail message before it enters the pipeline.
 */
final class MessageValidator
{
    public function __invoke(MailMessage $message): MailMessage
    {
        $message->validate();

        return $message;
    }
}
