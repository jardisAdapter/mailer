<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Tests\Unit\Config;

use JardisAdapter\Mailer\Config\Encryption;
use JardisAdapter\Mailer\Config\SmtpConfig;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SmtpConfig value object.
 */
final class SmtpConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = new SmtpConfig(host: 'smtp.example.com');

        $this->assertSame('smtp.example.com', $config->host);
        $this->assertSame(587, $config->port);
        $this->assertSame(Encryption::Tls, $config->encryption);
        $this->assertNull($config->username);
        $this->assertNull($config->password);
        $this->assertSame(30, $config->timeout);
        $this->assertNull($config->fromAddress);
        $this->assertNull($config->fromName);
        $this->assertSame(0, $config->maxRetries);
        $this->assertSame(100, $config->retryDelayMs);
    }

    public function testCustomValues(): void
    {
        $config = new SmtpConfig(
            host: 'mail.test.com',
            port: 465,
            encryption: Encryption::Ssl,
            username: 'user',
            password: 'pass',
            timeout: 60,
            fromAddress: 'no-reply@test.com',
            fromName: 'Test App',
        );

        $this->assertSame('mail.test.com', $config->host);
        $this->assertSame(465, $config->port);
        $this->assertSame(Encryption::Ssl, $config->encryption);
        $this->assertSame('user', $config->username);
        $this->assertSame('pass', $config->password);
        $this->assertSame(60, $config->timeout);
        $this->assertSame('no-reply@test.com', $config->fromAddress);
        $this->assertSame('Test App', $config->fromName);
    }

    public function testRetryConfig(): void
    {
        $config = new SmtpConfig(
            host: 'smtp.example.com',
            maxRetries: 3,
            retryDelayMs: 200,
        );

        $this->assertSame(3, $config->maxRetries);
        $this->assertSame(200, $config->retryDelayMs);
    }
}
