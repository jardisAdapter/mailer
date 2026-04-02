<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Tests\Unit;

use JardisAdapter\Mailer\Exception\MailMessageException;
use JardisAdapter\Mailer\Data\MailMessage;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MailMessage value object and fluent builder.
 */
final class MailMessageTest extends TestCase
{
    public function testFluentBuilderCreatesImmutableCopies(): void
    {
        $original = MailMessage::create();
        $withFrom = $original->withFrom('sender@test.com', 'Sender');

        $this->assertNull($original->fromAddress);
        $this->assertSame('sender@test.com', $withFrom->fromAddress->email);
        $this->assertSame('Sender', $withFrom->fromAddress->name);
    }

    public function testAddMultipleRecipients(): void
    {
        $message = MailMessage::create()
            ->withTo('a@test.com', 'Alice')
            ->withTo('b@test.com', 'Bob');

        $this->assertCount(2, $message->toAddresses);
        $this->assertSame('a@test.com', $message->toAddresses[0]->email);
        $this->assertSame('b@test.com', $message->toAddresses[1]->email);
    }

    public function testCcAndBcc(): void
    {
        $message = MailMessage::create()
            ->withCc('cc@test.com', 'CC User')
            ->withBcc('bcc@test.com');

        $this->assertCount(1, $message->ccAddresses);
        $this->assertSame('cc@test.com', $message->ccAddresses[0]->email);
        $this->assertSame('CC User', $message->ccAddresses[0]->name);
        $this->assertCount(1, $message->bccAddresses);
        $this->assertNull($message->bccAddresses[0]->name);
    }

    public function testReplyTo(): void
    {
        $message = MailMessage::create()->withReplyTo('reply@test.com', 'Reply');

        $this->assertSame('reply@test.com', $message->replyToAddress->email);
        $this->assertSame('Reply', $message->replyToAddress->name);
    }

    public function testSubjectAndBodies(): void
    {
        $message = MailMessage::create()
            ->withSubject('Test Subject')
            ->withText('Plain text')
            ->withHtml('<p>HTML</p>');

        $this->assertSame('Test Subject', $message->subjectLine);
        $this->assertSame('Plain text', $message->textBody);
        $this->assertSame('<p>HTML</p>', $message->htmlBody);
    }

    public function testAttach(): void
    {
        $message = MailMessage::create()
            ->withAttachment('pdf content', 'doc.pdf', 'application/pdf');

        $this->assertCount(1, $message->attachmentList);
        $this->assertSame('doc.pdf', $message->attachmentList[0]->filename);
        $this->assertFalse($message->attachmentList[0]->inline);
    }

    public function testEmbedImage(): void
    {
        $message = MailMessage::create()
            ->withEmbeddedImage('png data', 'logo.png', 'image/png');

        $this->assertCount(1, $message->attachmentList);
        $this->assertTrue($message->attachmentList[0]->inline);
        $this->assertNotNull($message->attachmentList[0]->contentId);
    }

    public function testCustomHeaders(): void
    {
        $message = MailMessage::create()
            ->withHeader('X-Priority', '1')
            ->withHeader('X-Mailer', 'Jardis');

        $this->assertSame('1', $message->customHeaders['X-Priority']);
        $this->assertSame('Jardis', $message->customHeaders['X-Mailer']);
    }

    public function testAllRecipientsMergesAllTypes(): void
    {
        $message = MailMessage::create()
            ->withTo('to@test.com')
            ->withCc('cc@test.com')
            ->withBcc('bcc@test.com');

        $all = $message->allRecipients();
        $this->assertCount(3, $all);
        $emails = array_map(static fn ($a) => $a->email, $all);
        $this->assertSame(['to@test.com', 'cc@test.com', 'bcc@test.com'], $emails);
    }

    public function testValidateRequiresFrom(): void
    {
        $message = MailMessage::create()
            ->withTo('to@test.com')
            ->withText('body');

        $this->expectException(MailMessageException::class);
        $this->expectExceptionMessage('From');
        $message->validate();
    }

    public function testValidateRequiresTo(): void
    {
        $message = MailMessage::create()
            ->withFrom('from@test.com')
            ->withText('body');

        $this->expectException(MailMessageException::class);
        $this->expectExceptionMessage('To');
        $message->validate();
    }

    public function testValidateRequiresBody(): void
    {
        $message = MailMessage::create()
            ->withFrom('from@test.com')
            ->withTo('to@test.com');

        $this->expectException(MailMessageException::class);
        $this->expectExceptionMessage('body');
        $message->validate();
    }

    public function testValidateAcceptsTextOnly(): void
    {
        $message = MailMessage::create()
            ->withFrom('from@test.com')
            ->withTo('to@test.com')
            ->withText('body');

        $message->validate();
        $this->addToAssertionCount(1);
    }

    public function testValidateAcceptsHtmlOnly(): void
    {
        $message = MailMessage::create()
            ->withFrom('from@test.com')
            ->withTo('to@test.com')
            ->withHtml('<p>body</p>');

        $message->validate();
        $this->addToAssertionCount(1);
    }

    public function testFullFluentChain(): void
    {
        $message = MailMessage::create()
            ->withFrom('sender@example.com', 'Sender Name')
            ->withTo('recipient@example.com', 'Recipient Name')
            ->withCc('cc@example.com')
            ->withBcc('bcc@example.com')
            ->withReplyTo('reply@example.com')
            ->withSubject('Your Order Confirmation')
            ->withText('Plain text body')
            ->withHtml('<h1>HTML body</h1>')
            ->withAttachment('pdf', 'invoice.pdf', 'application/pdf')
            ->withHeader('X-Priority', '1');

        $this->assertSame('sender@example.com', $message->fromAddress->email);
        $this->assertCount(1, $message->toAddresses);
        $this->assertCount(1, $message->ccAddresses);
        $this->assertCount(1, $message->bccAddresses);
        $this->assertSame('reply@example.com', $message->replyToAddress->email);
        $this->assertSame('Your Order Confirmation', $message->subjectLine);
        $this->assertSame('Plain text body', $message->textBody);
        $this->assertSame('<h1>HTML body</h1>', $message->htmlBody);
        $this->assertCount(1, $message->attachmentList);
        $this->assertSame('1', $message->customHeaders['X-Priority']);
    }
}
