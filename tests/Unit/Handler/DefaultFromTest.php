<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Tests\Unit\Handler;

use JardisAdapter\Mailer\Handler\DefaultFrom;
use JardisAdapter\Mailer\Data\MailMessage;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DefaultFrom handler.
 */
final class DefaultFromTest extends TestCase
{
    public function testAppliesDefaultWhenNoFromSet(): void
    {
        $handler = new DefaultFrom('default@test.com', 'Default');
        $message = MailMessage::create()->withTo('to@test.com')->withText('body');

        $result = $handler($message);

        $this->assertSame('default@test.com', $result->fromAddress->email);
        $this->assertSame('Default', $result->fromAddress->name);
    }

    public function testDoesNotOverrideExplicitFrom(): void
    {
        $handler = new DefaultFrom('default@test.com', 'Default');
        $message = MailMessage::create()->withFrom('explicit@test.com', 'Explicit')->withTo('to@test.com')->withText('body');

        $result = $handler($message);

        $this->assertSame('explicit@test.com', $result->fromAddress->email);
        $this->assertSame('Explicit', $result->fromAddress->name);
    }

    public function testAppliesDefaultWithoutName(): void
    {
        $handler = new DefaultFrom('noreply@test.com');
        $message = MailMessage::create()->withTo('to@test.com')->withText('body');

        $result = $handler($message);

        $this->assertSame('noreply@test.com', $result->fromAddress->email);
        $this->assertNull($result->fromAddress->name);
    }
}
