<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Tests\Unit\Handler;

use JardisAdapter\Mailer\Data\Address;
use JardisAdapter\Mailer\Data\Envelope;
use JardisAdapter\Mailer\Data\MailMessage;
use JardisAdapter\Mailer\Handler\MimeEncoder;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MimeEncoder handler.
 */
final class MimeEncoderTest extends TestCase
{
    private MimeEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new MimeEncoder();
    }

    public function testInvokeReturnsEnvelope(): void
    {
        $message = MailMessage::create()
            ->withFrom('sender@example.com')
            ->withTo('to@example.com')
            ->withCc('cc@example.com')
            ->withText('Hello');

        $envelope = ($this->encoder)($message);

        $this->assertInstanceOf(Envelope::class, $envelope);
        $this->assertSame('sender@example.com', $envelope->sender);
        $this->assertSame(['to@example.com', 'cc@example.com'], $envelope->recipients);
        $this->assertStringContainsString('Hello', $envelope->rawMessage);
    }

    public function testEncodeAddressEmailOnly(): void
    {
        $address = new Address('test@example.com');

        $this->assertSame('test@example.com', $this->encoder->encodeAddress($address));
    }

    public function testEncodeAddressWithAsciiName(): void
    {
        $address = new Address('test@example.com', 'John Doe');

        $this->assertSame('John Doe <test@example.com>', $this->encoder->encodeAddress($address));
    }

    public function testEncodeAddressWithUtf8Name(): void
    {
        $address = new Address('test@example.com', 'Ünïcödé');
        $encoded = $this->encoder->encodeAddress($address);

        $this->assertStringContainsString('=?UTF-8?B?', $encoded);
        $this->assertStringContainsString('<test@example.com>', $encoded);
    }

    public function testEncodeHeaderAscii(): void
    {
        $this->assertSame('Hello World', $this->encoder->encodeHeader('Hello World'));
    }

    public function testEncodeHeaderUtf8(): void
    {
        $encoded = $this->encoder->encodeHeader('Ärger mit Ü');

        $this->assertStringStartsWith('=?UTF-8?B?', $encoded);
        $this->assertStringEndsWith('?=', $encoded);
    }

    public function testGenerateMessageId(): void
    {
        $id = $this->encoder->generateMessageId('example.com');

        $this->assertStringStartsWith('<', $id);
        $this->assertStringEndsWith('@example.com>', $id);
    }

    public function testEncodeTextOnlyMessage(): void
    {
        $message = MailMessage::create()
            ->withFrom('sender@example.com')
            ->withTo('recipient@example.com')
            ->withSubject('Test')
            ->withText('Hello World');

        $raw = $this->encoder->encode($message);

        $this->assertStringContainsString('From: sender@example.com', $raw);
        $this->assertStringContainsString('To: recipient@example.com', $raw);
        $this->assertStringContainsString('Subject: Test', $raw);
        $this->assertStringContainsString('text/plain', $raw);
        $this->assertStringContainsString('Hello World', $raw);
        $this->assertStringContainsString('MIME-Version: 1.0', $raw);
    }

    public function testEncodeHtmlOnlyMessage(): void
    {
        $message = MailMessage::create()
            ->withFrom('sender@example.com')
            ->withTo('recipient@example.com')
            ->withSubject('Test')
            ->withHtml('<p>Hello</p>');

        $raw = $this->encoder->encode($message);

        $this->assertStringContainsString('text/html', $raw);
        $this->assertStringContainsString('<p>Hello</p>', $raw);
    }

    public function testEncodeDualBodyMessage(): void
    {
        $message = MailMessage::create()
            ->withFrom('sender@example.com')
            ->withTo('recipient@example.com')
            ->withSubject('Test')
            ->withText('Plain')
            ->withHtml('<p>HTML</p>');

        $raw = $this->encoder->encode($message);

        $this->assertStringContainsString('multipart/alternative', $raw);
        $this->assertStringContainsString('text/plain', $raw);
        $this->assertStringContainsString('text/html', $raw);
        $this->assertStringContainsString('Plain', $raw);
        $this->assertStringContainsString('<p>HTML</p>', $raw);
    }

    public function testEncodeMessageWithAttachment(): void
    {
        $message = MailMessage::create()
            ->withFrom('sender@example.com')
            ->withTo('recipient@example.com')
            ->withSubject('Test')
            ->withText('Body')
            ->withAttachment('file content', 'doc.pdf', 'application/pdf');

        $raw = $this->encoder->encode($message);

        $this->assertStringContainsString('multipart/mixed', $raw);
        $this->assertStringContainsString('application/pdf', $raw);
        $this->assertStringContainsString('doc.pdf', $raw);
        $this->assertStringContainsString(base64_encode('file content'), $raw);
    }

    public function testEncodeMessageWithInlineImage(): void
    {
        $message = MailMessage::create()
            ->withFrom('sender@example.com')
            ->withTo('recipient@example.com')
            ->withSubject('Test')
            ->withHtml('<p>Image</p>')
            ->withEmbeddedImage('png data', 'logo.png', 'image/png');

        $raw = $this->encoder->encode($message);

        $this->assertStringContainsString('multipart/related', $raw);
        $this->assertStringContainsString('Content-ID:', $raw);
        $this->assertStringContainsString('image/png', $raw);
    }

    public function testEncodeMessageWithCcAndReplyTo(): void
    {
        $message = MailMessage::create()
            ->withFrom('sender@example.com', 'Sender')
            ->withTo('to@example.com')
            ->withCc('cc@example.com', 'CC User')
            ->withReplyTo('reply@example.com')
            ->withSubject('Test')
            ->withText('Body');

        $raw = $this->encoder->encode($message);

        $this->assertStringContainsString('Cc: CC User <cc@example.com>', $raw);
        $this->assertStringContainsString('Reply-To: reply@example.com', $raw);
        $this->assertStringContainsString('Sender <sender@example.com>', $raw);
    }

    public function testEncodeMessageWithCustomHeaders(): void
    {
        $message = MailMessage::create()
            ->withFrom('sender@example.com')
            ->withTo('to@example.com')
            ->withSubject('Test')
            ->withText('Body')
            ->withHeader('X-Priority', '1')
            ->withHeader('X-Mailer', 'Jardis');

        $raw = $this->encoder->encode($message);

        $this->assertStringContainsString('X-Priority: 1', $raw);
        $this->assertStringContainsString('X-Mailer: Jardis', $raw);
    }

    public function testMessageIdContainsDomain(): void
    {
        $message = MailMessage::create()
            ->withFrom('sender@mydomain.com')
            ->withTo('to@example.com')
            ->withSubject('Test')
            ->withText('Body');

        $raw = $this->encoder->encode($message);

        $this->assertStringContainsString('@mydomain.com>', $raw);
    }

    public function testQuotedPrintableEncode(): void
    {
        $input = "Ärger mit Ümlauten";
        $encoded = $this->encoder->quotedPrintableEncode($input);

        $this->assertStringContainsString('=', $encoded);
        $this->assertSame($input, quoted_printable_decode($encoded));
    }
}
