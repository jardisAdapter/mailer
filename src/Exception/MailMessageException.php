<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Exception;

use JardisSupport\Contract\Mailer\MailerExceptionInterface;
use RuntimeException;

/**
 * Invalid mail message (missing From, To, or body).
 */
class MailMessageException extends RuntimeException implements MailerExceptionInterface
{
}
