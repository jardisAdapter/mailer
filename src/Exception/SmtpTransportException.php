<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Exception;

use JardisSupport\Contract\Mailer\MailerExceptionInterface;
use RuntimeException;

/**
 * SMTP protocol errors during message delivery (rejected recipient, DATA error).
 */
class SmtpTransportException extends RuntimeException implements MailerExceptionInterface
{
}
