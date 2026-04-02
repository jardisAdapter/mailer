<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Exception;

use JardisSupport\Contract\Mailer\MailerExceptionInterface;
use RuntimeException;

/**
 * Connection-level errors: host unreachable, TLS handshake failure, timeout.
 */
class SmtpConnectionException extends RuntimeException implements MailerExceptionInterface
{
}
