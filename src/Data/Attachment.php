<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Data;

/**
 * Mail attachment value object.
 */
final readonly class Attachment
{
    public function __construct(
        public string $content,
        public string $filename,
        public string $contentType = 'application/octet-stream',
        public bool $inline = false,
        public ?string $contentId = null,
    ) {
    }
}
