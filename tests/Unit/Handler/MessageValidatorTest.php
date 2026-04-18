<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Tests\Unit\Handler;

use JardisAdapter\Mailer\Exception\MailMessageException;
use JardisAdapter\Mailer\Handler\MessageValidator;
use JardisAdapter\Mailer\Data\MailMessage;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MessageValidator handler.
 */
final class MessageValidatorTest extends TestCase
{
    public function testPassesValidMessage(): void
    {
        $validator = new MessageValidator();
        $message = MailMessage::create()->withFrom('a@b.com')->withTo('c@d.com')->withText('body');

        $result = $validator($message);

        $this->assertSame($message, $result);
    }

    public function testRejectsInvalidMessage(): void
    {
        $validator = new MessageValidator();
        $message = MailMessage::create();

        $this->expectException(MailMessageException::class);
        $validator($message);
    }
}
