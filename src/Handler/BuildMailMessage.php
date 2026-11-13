<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Handler;

use InvalidArgumentException;
use JardisAdapter\Mailer\Data\MailMessage;

/**
 * Builds a MailMessage from the plain arguments of the kernel mail service.
 *
 * Only maps arguments onto the MailMessage builder; no sending, no defaults
 * (a missing From falls back to the configured default in the Mailer pipeline).
 */
final class BuildMailMessage
{
    private const OPTION_KEYS = ['from', 'fromName', 'cc', 'bcc', 'replyTo', 'headers'];

    /**
     * @param string|list<string> $to
     * @param array{
     *     from?: string,
     *     fromName?: string,
     *     cc?: list<string>,
     *     bcc?: list<string>,
     *     replyTo?: string,
     *     headers?: array<string, string>
     * } $options
     * @throws InvalidArgumentException If neither text nor HTML is given, an option key is unknown,
     *                                  or fromName is given without from
     */
    public function __invoke(
        string|array $to,
        string $subject,
        ?string $text = null,
        ?string $html = null,
        array $options = [],
    ): MailMessage {
        if ($text === null && $html === null) {
            throw new InvalidArgumentException('Mail requires a text body, an HTML body, or both');
        }

        $unknown = array_diff(array_keys($options), self::OPTION_KEYS);
        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Unknown mail option(s): ' . implode(', ', array_map('strval', $unknown))
                . '; allowed: ' . implode(', ', self::OPTION_KEYS),
            );
        }

        $message = MailMessage::create()->withSubject($subject);

        foreach ((array) $to as $recipient) {
            $message = $message->withTo($recipient);
        }

        if ($text !== null) {
            $message = $message->withText($text);
        }

        if ($html !== null) {
            $message = $message->withHtml($html);
        }

        if (isset($options['from'])) {
            $message = $message->withFrom($options['from'], $options['fromName'] ?? null);
        } elseif (isset($options['fromName'])) {
            throw new InvalidArgumentException('Mail option "fromName" requires option "from"');
        }

        foreach ($options['cc'] ?? [] as $address) {
            $message = $message->withCc($address);
        }

        foreach ($options['bcc'] ?? [] as $address) {
            $message = $message->withBcc($address);
        }

        if (isset($options['replyTo'])) {
            $message = $message->withReplyTo($options['replyTo']);
        }

        foreach ($options['headers'] ?? [] as $name => $value) {
            $message = $message->withHeader($name, $value);
        }

        return $message;
    }
}
