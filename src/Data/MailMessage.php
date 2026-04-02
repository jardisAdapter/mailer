<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Data;

use JardisAdapter\Mailer\Exception\MailMessageException;
use JardisSupport\Contract\Mailer\MailMessageInterface;

/**
 * Immutable mail message value object with fluent builder API.
 *
 * Properties are public for internal handler access (Address objects).
 * Interface getters return array representations per MailMessageInterface.
 */
final readonly class MailMessage implements MailMessageInterface
{
    /**
     * @param list<Address> $toAddresses
     * @param list<Address> $ccAddresses
     * @param list<Address> $bccAddresses
     * @param list<Attachment> $attachmentList
     * @param array<string, string> $customHeaders
     */
    public function __construct(
        public ?Address $fromAddress = null,
        public array $toAddresses = [],
        public array $ccAddresses = [],
        public array $bccAddresses = [],
        public ?Address $replyToAddress = null,
        public string $subjectLine = '',
        public ?string $textBody = null,
        public ?string $htmlBody = null,
        public array $attachmentList = [],
        public array $customHeaders = [],
    ) {
    }

    public static function create(): self
    {
        return new self();
    }

    // --- Interface Getters (MailMessageInterface) ---

    /**
     * @return array{address: string, name?: string}|null
     */
    public function from(): ?array
    {
        return $this->addressToArray($this->fromAddress);
    }

    /**
     * @return array<array{address: string, name?: string}>
     */
    public function to(): array
    {
        return array_map($this->addressToRequiredArray(...), $this->toAddresses);
    }

    /**
     * @return array<array{address: string, name?: string}>
     */
    public function cc(): array
    {
        return array_map($this->addressToRequiredArray(...), $this->ccAddresses);
    }

    /**
     * @return array<array{address: string, name?: string}>
     */
    public function bcc(): array
    {
        return array_map($this->addressToRequiredArray(...), $this->bccAddresses);
    }

    /**
     * @return array{address: string, name?: string}|null
     */
    public function replyTo(): ?array
    {
        return $this->addressToArray($this->replyToAddress);
    }

    public function subject(): ?string
    {
        return $this->subjectLine !== '' ? $this->subjectLine : null;
    }

    public function text(): ?string
    {
        return $this->textBody;
    }

    public function html(): ?string
    {
        return $this->htmlBody;
    }

    /**
     * @return array<array{content: string, filename: string, mimeType: string, inline: bool}>
     */
    public function attachments(): array
    {
        return array_map(
            static fn (Attachment $a): array => [
                'content' => $a->content,
                'filename' => $a->filename,
                'mimeType' => $a->contentType,
                'inline' => $a->inline,
            ],
            $this->attachmentList,
        );
    }

    // --- Fluent Builder (with* pattern) ---

    public function withFrom(string $email, ?string $name = null): self
    {
        return new self(
            new Address($email, $name),
            $this->toAddresses,
            $this->ccAddresses,
            $this->bccAddresses,
            $this->replyToAddress,
            $this->subjectLine,
            $this->textBody,
            $this->htmlBody,
            $this->attachmentList,
            $this->customHeaders,
        );
    }

    public function withTo(string $email, ?string $name = null): self
    {
        $to = $this->toAddresses;
        $to[] = new Address($email, $name);

        return new self(
            $this->fromAddress,
            $to,
            $this->ccAddresses,
            $this->bccAddresses,
            $this->replyToAddress,
            $this->subjectLine,
            $this->textBody,
            $this->htmlBody,
            $this->attachmentList,
            $this->customHeaders,
        );
    }

    public function withCc(string $email, ?string $name = null): self
    {
        $cc = $this->ccAddresses;
        $cc[] = new Address($email, $name);

        return new self(
            $this->fromAddress,
            $this->toAddresses,
            $cc,
            $this->bccAddresses,
            $this->replyToAddress,
            $this->subjectLine,
            $this->textBody,
            $this->htmlBody,
            $this->attachmentList,
            $this->customHeaders,
        );
    }

    public function withBcc(string $email, ?string $name = null): self
    {
        $bcc = $this->bccAddresses;
        $bcc[] = new Address($email, $name);

        return new self(
            $this->fromAddress,
            $this->toAddresses,
            $this->ccAddresses,
            $bcc,
            $this->replyToAddress,
            $this->subjectLine,
            $this->textBody,
            $this->htmlBody,
            $this->attachmentList,
            $this->customHeaders,
        );
    }

    public function withReplyTo(string $email, ?string $name = null): self
    {
        return new self(
            $this->fromAddress,
            $this->toAddresses,
            $this->ccAddresses,
            $this->bccAddresses,
            new Address($email, $name),
            $this->subjectLine,
            $this->textBody,
            $this->htmlBody,
            $this->attachmentList,
            $this->customHeaders,
        );
    }

    public function withSubject(string $subject): self
    {
        return new self(
            $this->fromAddress,
            $this->toAddresses,
            $this->ccAddresses,
            $this->bccAddresses,
            $this->replyToAddress,
            $subject,
            $this->textBody,
            $this->htmlBody,
            $this->attachmentList,
            $this->customHeaders,
        );
    }

    public function withText(string $text): self
    {
        return new self(
            $this->fromAddress,
            $this->toAddresses,
            $this->ccAddresses,
            $this->bccAddresses,
            $this->replyToAddress,
            $this->subjectLine,
            $text,
            $this->htmlBody,
            $this->attachmentList,
            $this->customHeaders,
        );
    }

    public function withHtml(string $html): self
    {
        return new self(
            $this->fromAddress,
            $this->toAddresses,
            $this->ccAddresses,
            $this->bccAddresses,
            $this->replyToAddress,
            $this->subjectLine,
            $this->textBody,
            $html,
            $this->attachmentList,
            $this->customHeaders,
        );
    }

    public function withAttachment(
        string $content,
        string $filename,
        string $contentType = 'application/octet-stream',
    ): self {
        $attachments = $this->attachmentList;
        $attachments[] = new Attachment($content, $filename, $contentType);

        return new self(
            $this->fromAddress,
            $this->toAddresses,
            $this->ccAddresses,
            $this->bccAddresses,
            $this->replyToAddress,
            $this->subjectLine,
            $this->textBody,
            $this->htmlBody,
            $attachments,
            $this->customHeaders,
        );
    }

    public function withEmbeddedImage(
        string $content,
        string $filename,
        string $contentType = 'image/png',
    ): self {
        $contentId = bin2hex(random_bytes(16)) . '@embed';
        $attachments = $this->attachmentList;
        $attachments[] = new Attachment($content, $filename, $contentType, true, $contentId);

        return new self(
            $this->fromAddress,
            $this->toAddresses,
            $this->ccAddresses,
            $this->bccAddresses,
            $this->replyToAddress,
            $this->subjectLine,
            $this->textBody,
            $this->htmlBody,
            $attachments,
            $this->customHeaders,
        );
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->customHeaders;
        $headers[$name] = $value;

        return new self(
            $this->fromAddress,
            $this->toAddresses,
            $this->ccAddresses,
            $this->bccAddresses,
            $this->replyToAddress,
            $this->subjectLine,
            $this->textBody,
            $this->htmlBody,
            $this->attachmentList,
            $headers,
        );
    }

    // --- Internal helpers ---

    /**
     * @return list<Address>
     */
    public function allRecipients(): array
    {
        return [...$this->toAddresses, ...$this->ccAddresses, ...$this->bccAddresses];
    }

    public function validate(): void
    {
        if ($this->fromAddress === null) {
            throw new MailMessageException('Mail message requires a From address');
        }

        if ($this->toAddresses === []) {
            throw new MailMessageException('Mail message requires at least one To address');
        }

        if ($this->textBody === null && $this->htmlBody === null) {
            throw new MailMessageException('Mail message requires at least a text or html body');
        }
    }

    /**
     * @return array{address: string, name?: string}|null
     */
    private function addressToArray(?Address $address): ?array
    {
        if ($address === null) {
            return null;
        }

        return $this->addressToRequiredArray($address);
    }

    /**
     * @return array{address: string, name?: string}
     */
    private function addressToRequiredArray(Address $address): array
    {
        $result = ['address' => $address->email];
        if ($address->name !== null) {
            $result['name'] = $address->name;
        }

        return $result;
    }
}
