<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Tests\Unit;

use JardisAdapter\Mailer\Data\Envelope;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Envelope value object.
 */
final class EnvelopeTest extends TestCase
{
    public function testProperties(): void
    {
        $envelope = new Envelope(
            'sender@test.com',
            ['a@test.com', 'b@test.com'],
            'raw message content',
        );

        $this->assertSame('sender@test.com', $envelope->sender);
        $this->assertSame(['a@test.com', 'b@test.com'], $envelope->recipients);
        $this->assertSame('raw message content', $envelope->rawMessage);
    }
}
