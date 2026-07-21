<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Tests\Unit;

use JardisAdapter\Mailer\Data\Envelope;
use JardisAdapter\Mailer\Data\MailMessage;
use JardisAdapter\Mailer\Config\SmtpConfig;
use JardisAdapter\Mailer\Exception\MailMessageException;
use JardisAdapter\Mailer\Exception\SmtpConnectionException;
use JardisAdapter\Mailer\Exception\SmtpTransportException;
use JardisAdapter\Mailer\Mailer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for Mailer orchestrator.
 */
final class MailerTest extends TestCase
{
    private SmtpConfig $config;

    protected function setUp(): void
    {
        $this->config = new SmtpConfig(host: 'localhost');
    }

    public function testSendDelegatesToTransport(): void
    {
        $captured = null;

        $transport = function (Envelope $envelope) use (&$captured): void {
            $captured = $envelope;
        };

        $mailer = new Mailer($this->config, $transport(...));
        $message = MailMessage::create()
            ->withFrom('sender@test.com')
            ->withTo('recipient@test.com')
            ->withText('Hello');

        $mailer->send($message);

        $this->assertSame('sender@test.com', $captured->sender);
        $this->assertSame(['recipient@test.com'], $captured->recipients);
        $this->assertStringContainsString('Hello', $captured->rawMessage);
    }

    public function testSendIncludesAllRecipientTypes(): void
    {
        $captured = null;

        $transport = function (Envelope $envelope) use (&$captured): void {
            $captured = $envelope;
        };

        $mailer = new Mailer($this->config, $transport(...));
        $message = MailMessage::create()
            ->withFrom('sender@test.com')
            ->withTo('to@test.com')
            ->withCc('cc@test.com')
            ->withBcc('bcc@test.com')
            ->withText('Body');

        $mailer->send($message);

        $this->assertSame(['to@test.com', 'cc@test.com', 'bcc@test.com'], $captured->recipients);
    }

    public function testSendAppliesDefaultFrom(): void
    {
        $captured = null;

        $transport = function (Envelope $envelope) use (&$captured): void {
            $captured = $envelope;
        };

        $config = new SmtpConfig(
            host: 'localhost',
            fromAddress: 'default@test.com',
            fromName: 'Default Sender',
        );
        $mailer = new Mailer($config, $transport(...));

        $message = MailMessage::create()
            ->withTo('recipient@test.com')
            ->withText('Hello');

        $mailer->send($message);

        $this->assertSame('default@test.com', $captured->sender);
    }

    public function testSendDoesNotOverrideExplicitFrom(): void
    {
        $captured = null;

        $transport = function (Envelope $envelope) use (&$captured): void {
            $captured = $envelope;
        };

        $config = new SmtpConfig(
            host: 'localhost',
            fromAddress: 'default@test.com',
        );
        $mailer = new Mailer($config, $transport(...));

        $message = MailMessage::create()
            ->withFrom('explicit@test.com')
            ->withTo('recipient@test.com')
            ->withText('Hello');

        $mailer->send($message);

        $this->assertSame('explicit@test.com', $captured->sender);
    }

    public function testSendValidatesMessage(): void
    {
        $transport = function (Envelope $envelope): void {
        };

        $mailer = new Mailer($this->config, $transport(...));
        $message = MailMessage::create()->withFrom('sender@test.com');

        $this->expectException(MailMessageException::class);
        $mailer->send($message);
    }

    public function testSendBatchCollectsResults(): void
    {
        $callCount = 0;

        $transport = function (Envelope $envelope) use (&$callCount): void {
            $callCount++;
            if ($callCount === 2) {
                throw new RuntimeException('Failed');
            }
        };

        $mailer = new Mailer($this->config, $transport(...));

        $msg1 = MailMessage::create()->withFrom('a@b.com')->withTo('c@d.com')->withText('ok');
        $msg2 = MailMessage::create()->withFrom('a@b.com')->withTo('e@f.com')->withText('fail');
        $msg3 = MailMessage::create()->withFrom('a@b.com')->withTo('g@h.com')->withText('ok2');

        $result = $mailer->sendBatch([$msg1, $msg2, $msg3]);

        $this->assertSame(2, $result->successCount());
        $this->assertSame(1, $result->failureCount());
        $this->assertFalse($result->isAllSuccessful());
        $this->assertSame('Failed', $result->failed()[0]['error']->getMessage());
    }

    public function testSendBatchAllSuccessful(): void
    {
        $count = 0;

        $transport = function (Envelope $envelope) use (&$count): void {
            $count++;
        };

        $mailer = new Mailer($this->config, $transport(...));

        $msg1 = MailMessage::create()->withFrom('a@b.com')->withTo('c@d.com')->withText('ok1');
        $msg2 = MailMessage::create()->withFrom('a@b.com')->withTo('e@f.com')->withText('ok2');

        $result = $mailer->sendBatch([$msg1, $msg2]);

        $this->assertTrue($result->isAllSuccessful());
        $this->assertSame(2, $result->successCount());
        $this->assertSame(2, $count);
    }

    public function testRawMessageContainsMimeHeaders(): void
    {
        $captured = null;

        $transport = function (Envelope $envelope) use (&$captured): void {
            $captured = $envelope->rawMessage;
        };

        $mailer = new Mailer($this->config, $transport(...));
        $message = MailMessage::create()
            ->withFrom('sender@test.com', 'Sender')
            ->withTo('recipient@test.com')
            ->withSubject('Test Subject')
            ->withText('Body text')
            ->withHtml('<p>Body HTML</p>');

        $mailer->send($message);

        $this->assertStringContainsString('MIME-Version: 1.0', $captured);
        $this->assertStringContainsString('From: Sender <sender@test.com>', $captured);
        $this->assertStringContainsString('Subject: Test Subject', $captured);
        $this->assertStringContainsString('multipart/alternative', $captured);
    }

    public function testPipelineOrder(): void
    {
        $captured = null;

        $transport = function (Envelope $envelope) use (&$captured): void {
            $captured = $envelope;
        };

        $config = new SmtpConfig(
            host: 'localhost',
            fromAddress: 'default@test.com',
            fromName: 'App',
        );
        $mailer = new Mailer($config, $transport(...));

        $message = MailMessage::create()
            ->withTo('to@test.com')
            ->withSubject('Pipeline Test')
            ->withText('Body');

        $mailer->send($message);

        // DefaultFrom applied, then validated, then encoded
        $this->assertSame('default@test.com', $captured->sender);
        $this->assertStringContainsString('App <default@test.com>', $captured->rawMessage);
    }

    public function testSkipsDefaultFromWhenNotConfigured(): void
    {
        $captured = null;

        $transport = function (Envelope $envelope) use (&$captured): void {
            $captured = $envelope;
        };

        $mailer = new Mailer($this->config, $transport(...));
        $message = MailMessage::create()
            ->withFrom('sender@test.com')
            ->withTo('to@test.com')
            ->withText('Body');

        $mailer->send($message);

        $this->assertSame('sender@test.com', $captured->sender);
    }

    public function testRetryOnConnectionError(): void
    {
        $callCount = 0;

        $transport = function (Envelope $envelope) use (&$callCount): void {
            $callCount++;
            if ($callCount < 3) {
                throw new SmtpConnectionException('Connection lost');
            }
        };

        $config = new SmtpConfig(host: 'localhost', maxRetries: 3, retryDelayMs: 0);
        $mailer = new Mailer($config, $transport(...));

        $message = MailMessage::create()->withFrom('a@b.com')->withTo('c@d.com')->withText('body');
        $mailer->send($message);

        $this->assertSame(3, $callCount);
    }

    public function testRetryOnTemporary4xxError(): void
    {
        $callCount = 0;

        $transport = function (Envelope $envelope) use (&$callCount): void {
            $callCount++;
            if ($callCount === 1) {
                throw new SmtpTransportException('Try again later', 421);
            }
        };

        $config = new SmtpConfig(host: 'localhost', maxRetries: 2, retryDelayMs: 0);
        $mailer = new Mailer($config, $transport(...));

        $message = MailMessage::create()->withFrom('a@b.com')->withTo('c@d.com')->withText('body');
        $mailer->send($message);

        $this->assertSame(2, $callCount);
    }

    public function testNoRetryOnPermanent5xxError(): void
    {
        $callCount = 0;

        $transport = function (Envelope $envelope) use (&$callCount): void {
            $callCount++;
            throw new SmtpTransportException('Mailbox not found', 550);
        };

        $config = new SmtpConfig(host: 'localhost', maxRetries: 3, retryDelayMs: 0);
        $mailer = new Mailer($config, $transport(...));

        $message = MailMessage::create()->withFrom('a@b.com')->withTo('c@d.com')->withText('body');

        $this->expectException(SmtpTransportException::class);
        $this->expectExceptionCode(550);

        try {
            $mailer->send($message);
        } finally {
            $this->assertSame(1, $callCount);
        }
    }

    public function testRetryExhaustedThrowsLastException(): void
    {
        $transport = function (Envelope $envelope): void {
            throw new SmtpConnectionException('Connection refused');
        };

        $config = new SmtpConfig(host: 'localhost', maxRetries: 2, retryDelayMs: 0);
        $mailer = new Mailer($config, $transport(...));

        $message = MailMessage::create()->withFrom('a@b.com')->withTo('c@d.com')->withText('body');

        $this->expectException(SmtpConnectionException::class);
        $this->expectExceptionMessage('Connection refused');
        $mailer->send($message);
    }

    public function testNoRetryWhenNotConfigured(): void
    {
        $callCount = 0;

        $transport = function (Envelope $envelope) use (&$callCount): void {
            $callCount++;
            throw new SmtpConnectionException('Connection lost');
        };

        $mailer = new Mailer($this->config, $transport(...));
        $message = MailMessage::create()->withFrom('a@b.com')->withTo('c@d.com')->withText('body');

        $this->expectException(SmtpConnectionException::class);

        try {
            $mailer->send($message);
        } finally {
            $this->assertSame(1, $callCount);
        }
    }
}
