<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Tests\Unit;

use JardisAdapter\Mailer\Data\BatchResult;
use JardisAdapter\Mailer\Data\MailMessage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for BatchResult.
 */
final class BatchResultTest extends TestCase
{
    public function testEmptyResult(): void
    {
        $result = new BatchResult();

        $this->assertSame(0, $result->successCount());
        $this->assertSame(0, $result->failureCount());
        $this->assertTrue($result->isAllSuccessful());
        $this->assertSame([], $result->successful());
        $this->assertSame([], $result->failed());
    }

    public function testAddSuccess(): void
    {
        $message = MailMessage::create()->withFrom('a@b.com')->withTo('c@d.com')->withText('x');
        $result = new BatchResult();
        $result->addSuccess($message);

        $this->assertSame(1, $result->successCount());
        $this->assertSame(0, $result->failureCount());
        $this->assertTrue($result->isAllSuccessful());
        $this->assertSame([$message], $result->successful());
    }

    public function testAddFailure(): void
    {
        $message = MailMessage::create()->withFrom('a@b.com')->withTo('c@d.com')->withText('x');
        $error = new RuntimeException('send failed');
        $result = new BatchResult();
        $result->addFailure($message, $error);

        $this->assertSame(0, $result->successCount());
        $this->assertSame(1, $result->failureCount());
        $this->assertFalse($result->isAllSuccessful());
        $this->assertSame($message, $result->failed()[0]['message']);
        $this->assertSame($error, $result->failed()[0]['error']);
    }

    public function testMixedResults(): void
    {
        $ok = MailMessage::create()->withFrom('a@b.com')->withTo('c@d.com')->withText('ok');
        $fail = MailMessage::create()->withFrom('a@b.com')->withTo('e@f.com')->withText('fail');
        $error = new RuntimeException('err');

        $result = new BatchResult();
        $result->addSuccess($ok);
        $result->addFailure($fail, $error);

        $this->assertSame(1, $result->successCount());
        $this->assertSame(1, $result->failureCount());
        $this->assertFalse($result->isAllSuccessful());
    }
}
