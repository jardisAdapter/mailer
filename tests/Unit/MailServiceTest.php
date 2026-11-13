<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Tests\Unit;

use InvalidArgumentException;
use JardisAdapter\Mailer\Config\SmtpConfig;
use JardisAdapter\Mailer\Data\Envelope;
use JardisAdapter\Mailer\Exception\SmtpConnectionException;
use JardisAdapter\Mailer\Mailer;
use JardisSupport\Contract\Mailer\MailerExceptionInterface;
use JardisSupport\Contract\Mailer\MailServiceInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the kernel mail service contract (MailServiceInterface) implemented by Mailer.
 * Called through the contract type; transport is the injectable in-memory closure, no network.
 */
final class MailServiceTest extends TestCase
{
    /** @var list<Envelope> */
    private array $sent = [];

    private function service(?SmtpConfig $config = null, ?\Closure $transport = null): MailServiceInterface
    {
        $this->sent = [];

        return new Mailer(
            $config ?? new SmtpConfig(host: 'localhost', fromAddress: 'default@test.com', fromName: 'Default'),
            $transport ?? function (Envelope $envelope): void {
                $this->sent[] = $envelope;
            },
        );
    }

    private function raw(): string
    {
        $this->assertCount(1, $this->sent);

        return $this->sent[0]->rawMessage;
    }

    public function testMailerIsAMailService(): void
    {
        $this->assertInstanceOf(MailServiceInterface::class, $this->service());
    }

    public function testSingleRecipientAsString(): void
    {
        $this->service()->sendMail('anna@test.com', 'Hello', 'Body');

        $this->assertSame(['anna@test.com'], $this->sent[0]->recipients);
        $this->assertSame('default@test.com', $this->sent[0]->sender);
        $this->assertStringContainsString('To: anna@test.com', $this->raw());
        $this->assertStringContainsString('Subject: Hello', $this->raw());
    }

    public function testMultipleRecipientsAsList(): void
    {
        $this->service()->sendMail(['anna@test.com', 'ben@test.com'], 'Hello', 'Body');

        $this->assertSame(['anna@test.com', 'ben@test.com'], $this->sent[0]->recipients);
        $this->assertStringContainsString('To: anna@test.com, ben@test.com', $this->raw());
    }

    public function testTextOnly(): void
    {
        $this->service()->sendMail('anna@test.com', 'Hello', 'Plain body');

        $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $this->raw());
        $this->assertStringContainsString('Plain body', $this->raw());
    }

    public function testHtmlOnly(): void
    {
        $this->service()->sendMail('anna@test.com', 'Hello', null, '<p>Html body</p>');

        $this->assertStringContainsString('Content-Type: text/html; charset=UTF-8', $this->raw());
        $this->assertStringContainsString('<p>Html body</p>', $this->raw());
    }

    public function testTextAndHtml(): void
    {
        $this->service()->sendMail('anna@test.com', 'Hello', 'Plain body', '<p>Html body</p>');

        $this->assertStringContainsString('multipart/alternative', $this->raw());
        $this->assertStringContainsString('Plain body', $this->raw());
        $this->assertStringContainsString('<p>Html body</p>', $this->raw());
    }

    public function testOptionFrom(): void
    {
        $this->service()->sendMail('anna@test.com', 'Hello', 'Body', null, ['from' => 'own@test.com']);

        $this->assertSame('own@test.com', $this->sent[0]->sender);
        $this->assertStringContainsString('From: own@test.com', $this->raw());
    }

    public function testOptionFromName(): void
    {
        $this->service()->sendMail(
            'anna@test.com',
            'Hello',
            'Body',
            null,
            ['from' => 'own@test.com', 'fromName' => 'Own Name'],
        );

        $this->assertStringContainsString('From: Own Name <own@test.com>', $this->raw());
    }

    public function testOptionCc(): void
    {
        $this->service()->sendMail('anna@test.com', 'Hello', 'Body', null, ['cc' => ['c1@test.com', 'c2@test.com']]);

        $this->assertStringContainsString('Cc: c1@test.com, c2@test.com', $this->raw());
        $this->assertSame(['anna@test.com', 'c1@test.com', 'c2@test.com'], $this->sent[0]->recipients);
    }

    public function testOptionBcc(): void
    {
        $this->service()->sendMail('anna@test.com', 'Hello', 'Body', null, ['bcc' => ['hidden@test.com']]);

        $this->assertSame(['anna@test.com', 'hidden@test.com'], $this->sent[0]->recipients);
        $this->assertStringNotContainsString('hidden@test.com', $this->raw());
    }

    public function testOptionReplyTo(): void
    {
        $this->service()->sendMail('anna@test.com', 'Hello', 'Body', null, ['replyTo' => 'reply@test.com']);

        $this->assertStringContainsString('Reply-To: reply@test.com', $this->raw());
    }

    public function testOptionHeaders(): void
    {
        $this->service()->sendMail(
            'anna@test.com',
            'Hello',
            'Body',
            null,
            ['headers' => ['X-Trace' => 'abc', 'X-Other' => 'def']],
        );

        $this->assertStringContainsString('X-Trace: abc', $this->raw());
        $this->assertStringContainsString('X-Other: def', $this->raw());
    }

    public function testWithoutOptionsFallsBackToConfiguredDefaultFrom(): void
    {
        $this->service()->sendMail('anna@test.com', 'Hello', 'Body');

        $this->assertStringContainsString('From: Default <default@test.com>', $this->raw());
    }

    public function testNeitherTextNorHtmlThrowsInvalidArgument(): void
    {
        $service = $this->service();

        $this->expectException(InvalidArgumentException::class);

        try {
            $service->sendMail('anna@test.com', 'Hello');
        } finally {
            $this->assertSame([], $this->sent);
        }
    }

    public function testUnknownOptionKeyThrowsInvalidArgument(): void
    {
        $service = $this->service();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bogus');

        try {
            $service->sendMail('anna@test.com', 'Hello', 'Body', null, ['bogus' => 'x']);
        } finally {
            $this->assertSame([], $this->sent);
        }
    }

    public function testFromNameWithoutFromThrowsInvalidArgument(): void
    {
        $service = $this->service();

        $this->expectException(InvalidArgumentException::class);

        $service->sendMail('anna@test.com', 'Hello', 'Body', null, ['fromName' => 'Only Name']);
    }

    public function testTransferFailureIsMailerException(): void
    {
        $service = $this->service(
            new SmtpConfig(host: 'localhost', fromAddress: 'default@test.com'),
            static function (Envelope $envelope): void {
                throw new SmtpConnectionException('connection refused');
            },
        );

        $this->expectException(MailerExceptionInterface::class);

        $service->sendMail('anna@test.com', 'Hello', 'Body');
    }

    public function testSendOfPreparedMessageStillWorks(): void
    {
        $captured = null;
        $mailer = new Mailer(
            new SmtpConfig(host: 'localhost'),
            function (Envelope $envelope) use (&$captured): void {
                $captured = $envelope;
            },
        );

        $mailer->send(
            \JardisAdapter\Mailer\Data\MailMessage::create()
                ->withFrom('a@test.com')->withTo('b@test.com')->withSubject('S')->withText('T'),
        );

        $this->assertSame('a@test.com', $captured?->sender);
    }
}
