<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Exception;

use JardisSupport\Contract\Mailer\MailerExceptionInterface;
use RuntimeException;

/**
 * SMTP authentication failure (LOGIN or PLAIN rejected).
 */
class SmtpAuthenticationException extends RuntimeException implements MailerExceptionInterface
{
}
