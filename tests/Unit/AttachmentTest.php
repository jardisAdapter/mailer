<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Tests\Unit;

use JardisAdapter\Mailer\Data\Attachment;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Attachment value object.
 */
final class AttachmentTest extends TestCase
{
    public function testRegularAttachment(): void
    {
        $attachment = new Attachment('file content', 'doc.pdf', 'application/pdf');

        $this->assertSame('file content', $attachment->content);
        $this->assertSame('doc.pdf', $attachment->filename);
        $this->assertSame('application/pdf', $attachment->contentType);
        $this->assertFalse($attachment->inline);
        $this->assertNull($attachment->contentId);
    }

    public function testInlineAttachment(): void
    {
        $attachment = new Attachment('img', 'logo.png', 'image/png', true, 'cid123');

        $this->assertTrue($attachment->inline);
        $this->assertSame('cid123', $attachment->contentId);
    }

    public function testDefaultContentType(): void
    {
        $attachment = new Attachment('data', 'file.bin');

        $this->assertSame('application/octet-stream', $attachment->contentType);
    }
}
