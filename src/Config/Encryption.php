<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Config;

/**
 * SMTP encryption mode.
 */
enum Encryption: string
{
    case Tls  = 'tls';
    case Ssl  = 'ssl';
    case None = 'none';
}
