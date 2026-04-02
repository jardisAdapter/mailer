<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Tests\Unit\Exception;

use JardisSupport\Contract\Mailer\MailerExceptionInterface;
use JardisAdapter\Mailer\Exception\MailMessageException;
use JardisAdapter\Mailer\Exception\SmtpAuthenticationException;
use JardisAdapter\Mailer\Exception\SmtpConnectionException;
use JardisAdapter\Mailer\Exception\SmtpTransportException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for exception hierarchy.
 */
final class ExceptionTest extends TestCase
{
    public function testSmtpConnectionExceptionImplementsInterface(): void
    {
        $e = new SmtpConnectionException('connection failed', 111);

        $this->assertInstanceOf(MailerExceptionInterface::class, $e);
        $this->assertInstanceOf(RuntimeException::class, $e);
        $this->assertSame('connection failed', $e->getMessage());
        $this->assertSame(111, $e->getCode());
    }

    public function testSmtpAuthenticationExceptionImplementsInterface(): void
    {
        $e = new SmtpAuthenticationException('auth failed');

        $this->assertInstanceOf(MailerExceptionInterface::class, $e);
        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    public function testSmtpTransportExceptionImplementsInterface(): void
    {
        $e = new SmtpTransportException('transport error', 550);

        $this->assertInstanceOf(MailerExceptionInterface::class, $e);
        $this->assertSame(550, $e->getCode());
    }

    public function testMailMessageExceptionImplementsInterface(): void
    {
        $e = new MailMessageException('invalid message');

        $this->assertInstanceOf(MailerExceptionInterface::class, $e);
        $this->assertInstanceOf(RuntimeException::class, $e);
    }
}
