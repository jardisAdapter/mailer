<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Tests\Unit;

use JardisAdapter\Mailer\Data\Address;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Address value object.
 */
final class AddressTest extends TestCase
{
    public function testEmailOnly(): void
    {
        $address = new Address('test@example.com');

        $this->assertSame('test@example.com', $address->email);
        $this->assertNull($address->name);
    }

    public function testEmailWithName(): void
    {
        $address = new Address('test@example.com', 'Test User');

        $this->assertSame('test@example.com', $address->email);
        $this->assertSame('Test User', $address->name);
    }
}
