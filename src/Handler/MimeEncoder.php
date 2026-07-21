<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Handler;

use JardisAdapter\Mailer\Data\Address;
use JardisAdapter\Mailer\Data\Attachment;
use JardisAdapter\Mailer\Data\Envelope;
use JardisAdapter\Mailer\Data\MailMessage;

/**
 * Invokable MIME encoder — transforms MailMessage into wire-level Envelope.
 */
final class MimeEncoder
{
    public function __invoke(MailMessage $message): Envelope
    {
        /** @var Address $from validated before this handler runs */
        $from = $message->fromAddress;

        $sender = $from->email;
        $recipients = array_map(
            static fn (Address $addr): string => $addr->email,
            $message->allRecipients(),
        );
        $rawMessage = $this->encode($message);

        return new Envelope($sender, $recipients, $rawMessage);
    }

    public function encode(MailMessage $message): string
    {
        $boundary = $this->generateBoundary();
        $headers = $this->buildHeaders($message, $boundary);
        $body = $this->buildBody($message, $boundary);

        return $headers . "\r\n\r\n" . $body;
    }

    public function encodeAddress(Address $address): string
    {
        if ($address->name === null || $address->name === '') {
            return $address->email;
        }

        $encoded = $this->encodeHeader($address->name);

        return $encoded . ' <' . $address->email . '>';
    }

    public function encodeHeader(string $value): string
    {
        if ($this->isAscii($value)) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    public function generateMessageId(string $domain = 'localhost'): string
    {
        return '<' . bin2hex(random_bytes(16)) . '@' . $domain . '>';
    }

    public function quotedPrintableEncode(string $text): string
    {
        return quoted_printable_encode($text);
    }

    private function buildHeaders(MailMessage $message, string $boundary): string
    {
        $lines = [];

        $lines[] = 'MIME-Version: 1.0';

        if ($message->fromAddress !== null) {
            $lines[] = 'From: ' . $this->encodeAddress($message->fromAddress);
        }

        if ($message->toAddresses !== []) {
            $lines[] = 'To: ' . implode(', ', array_map($this->encodeAddress(...), $message->toAddresses));
        }

        if ($message->ccAddresses !== []) {
            $lines[] = 'Cc: ' . implode(', ', array_map($this->encodeAddress(...), $message->ccAddresses));
        }

        if ($message->replyToAddress !== null) {
            $lines[] = 'Reply-To: ' . $this->encodeAddress($message->replyToAddress);
        }

        $lines[] = 'Subject: ' . $this->encodeHeader($message->subjectLine);
        $lines[] = 'Date: ' . date('r');

        $domain = 'localhost';
        if ($message->fromAddress !== null) {
            $parts = explode('@', $message->fromAddress->email);
            if (count($parts) === 2) {
                $domain = $parts[1];
            }
        }
        $lines[] = 'Message-ID: ' . $this->generateMessageId($domain);

        foreach ($message->customHeaders as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $lines[] = 'Content-Type: ' . $this->resolveContentType($message, $boundary);

        return implode("\r\n", $lines);
    }

    private function resolveContentType(MailMessage $message, string $boundary): string
    {
        $hasAttachments = $this->hasRegularAttachments($message);
        $hasInline = $this->hasInlineAttachments($message);

        if ($hasAttachments) {
            return 'multipart/mixed; boundary="' . $boundary . '"';
        }

        if ($hasInline) {
            return 'multipart/related; boundary="' . $boundary . '"';
        }

        if ($message->textBody !== null && $message->htmlBody !== null) {
            return 'multipart/alternative; boundary="' . $boundary . '"';
        }

        if ($message->htmlBody !== null) {
            return 'text/html; charset=UTF-8';
        }

        return 'text/plain; charset=UTF-8';
    }

    private function buildBody(MailMessage $message, string $boundary): string
    {
        $hasAttachments = $this->hasRegularAttachments($message);
        $hasInline = $this->hasInlineAttachments($message);
        $hasBothBodies = $message->textBody !== null && $message->htmlBody !== null;

        if (!$hasAttachments && !$hasInline && !$hasBothBodies) {
            return $this->encodeSingleBody($message);
        }

        if (!$hasAttachments && !$hasInline && $hasBothBodies) {
            return $this->buildAlternativeBody($message, $boundary);
        }

        return $this->buildMultipartBody($message, $boundary);
    }

    private function encodeSingleBody(MailMessage $message): string
    {
        if ($message->htmlBody !== null) {
            return $this->quotedPrintableEncode($message->htmlBody);
        }

        return $this->quotedPrintableEncode($message->textBody ?? '');
    }

    private function buildAlternativeBody(MailMessage $message, string $boundary): string
    {
        $parts = [];

        if ($message->textBody !== null) {
            $parts[] = "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: quoted-printable\r\n"
                . "\r\n"
                . $this->quotedPrintableEncode($message->textBody);
        }

        if ($message->htmlBody !== null) {
            $parts[] = "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: quoted-printable\r\n"
                . "\r\n"
                . $this->quotedPrintableEncode($message->htmlBody);
        }

        return $this->assembleParts($parts, $boundary);
    }

    private function buildMultipartBody(MailMessage $message, string $boundary): string
    {
        $parts = [];
        $hasInline = $this->hasInlineAttachments($message);
        $hasBothBodies = $message->textBody !== null && $message->htmlBody !== null;

        if ($hasBothBodies || $hasInline) {
            $innerBoundary = $this->generateBoundary();
            $innerContent = $this->buildInnerContent($message, $innerBoundary, $hasInline);

            $type = $hasInline ? 'multipart/related' : 'multipart/alternative';
            $parts[] = "Content-Type: " . $type . "; boundary=\"" . $innerBoundary . "\"\r\n"
                . "\r\n"
                . $innerContent;
        } elseif ($message->htmlBody !== null) {
            $parts[] = "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: quoted-printable\r\n"
                . "\r\n"
                . $this->quotedPrintableEncode($message->htmlBody);
        } elseif ($message->textBody !== null) {
            $parts[] = "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: quoted-printable\r\n"
                . "\r\n"
                . $this->quotedPrintableEncode($message->textBody);
        }

        foreach ($message->attachmentList as $attachment) {
            if (!$attachment->inline) {
                $parts[] = $this->encodeAttachment($attachment);
            }
        }

        return $this->assembleParts($parts, $boundary);
    }

    private function buildInnerContent(
        MailMessage $message,
        string $boundary,
        bool $hasInline,
    ): string {
        $parts = [];

        if ($hasInline) {
            $altBoundary = $this->generateBoundary();
            $altContent = $this->buildAlternativeBody($message, $altBoundary);

            $parts[] = "Content-Type: multipart/alternative; boundary=\"" . $altBoundary . "\"\r\n"
                . "\r\n"
                . $altContent;

            foreach ($message->attachmentList as $attachment) {
                if ($attachment->inline) {
                    $parts[] = $this->encodeAttachment($attachment);
                }
            }
        } else {
            if ($message->textBody !== null) {
                $parts[] = "Content-Type: text/plain; charset=UTF-8\r\n"
                    . "Content-Transfer-Encoding: quoted-printable\r\n"
                    . "\r\n"
                    . $this->quotedPrintableEncode($message->textBody);
            }

            if ($message->htmlBody !== null) {
                $parts[] = "Content-Type: text/html; charset=UTF-8\r\n"
                    . "Content-Transfer-Encoding: quoted-printable\r\n"
                    . "\r\n"
                    . $this->quotedPrintableEncode($message->htmlBody);
            }
        }

        return $this->assembleParts($parts, $boundary);
    }

    private function encodeAttachment(Attachment $attachment): string
    {
        $headers = "Content-Type: " . $attachment->contentType . "; name=\"" . $attachment->filename . "\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Content-Disposition: " . ($attachment->inline ? 'inline' : 'attachment')
            . "; filename=\"" . $attachment->filename . "\"";

        if ($attachment->inline && $attachment->contentId !== null) {
            $headers .= "\r\nContent-ID: <" . $attachment->contentId . ">";
        }

        return $headers . "\r\n\r\n" . chunk_split(base64_encode($attachment->content), 76, "\r\n");
    }

    /**
     * @param list<string> $parts
     */
    private function assembleParts(array $parts, string $boundary): string
    {
        $result = '';

        foreach ($parts as $part) {
            $result .= '--' . $boundary . "\r\n" . $part . "\r\n";
        }

        $result .= '--' . $boundary . '--';

        return $result;
    }

    private function hasRegularAttachments(MailMessage $message): bool
    {
        foreach ($message->attachmentList as $attachment) {
            if (!$attachment->inline) {
                return true;
            }
        }

        return false;
    }

    private function hasInlineAttachments(MailMessage $message): bool
    {
        foreach ($message->attachmentList as $attachment) {
            if ($attachment->inline) {
                return true;
            }
        }

        return false;
    }

    private function isAscii(string $value): bool
    {
        return mb_check_encoding($value, 'ASCII');
    }

    private function generateBoundary(): string
    {
        return '----=_Part_' . bin2hex(random_bytes(16));
    }
}
