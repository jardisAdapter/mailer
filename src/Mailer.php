<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer;

use Closure;
use JardisAdapter\Mailer\Config\SmtpConfig;
use JardisAdapter\Mailer\Data\BatchResult;
use JardisAdapter\Mailer\Data\Envelope;
use JardisAdapter\Mailer\Data\MailMessage;
use JardisAdapter\Mailer\Exception\SmtpConnectionException;
use JardisAdapter\Mailer\Exception\SmtpTransportException;
use JardisAdapter\Mailer\Handler\DefaultFrom;
use JardisAdapter\Mailer\Handler\MessageValidator;
use JardisAdapter\Mailer\Handler\MimeEncoder;
use JardisAdapter\Mailer\Handler\SmtpTransport;
use JardisSupport\Contract\Mailer\MailerInterface;
use JardisSupport\Contract\Mailer\MailMessageInterface;

/**
 * Mail service — pure orchestrator that builds handler pipeline from config.
 */
final class Mailer implements MailerInterface
{
    /** @var list<Closure(MailMessage): MailMessage> */
    private readonly array $transformers;

    /** @var Closure(MailMessage): Envelope */
    private readonly Closure $encoder;

    /** @var Closure(Envelope): void */
    private readonly Closure $transport;

    /** @var Closure(): void */
    private readonly Closure $disconnectFn;

    /**
     * @param ?Closure(Envelope): void $transport
     */
    public function __construct(
        private readonly SmtpConfig $config,
        ?Closure $transport = null,
    ) {
        $this->transformers = $this->buildTransformers();
        $this->encoder = (new MimeEncoder())->__invoke(...);
        [$this->transport, $this->disconnectFn] = $this->buildTransport($transport);
    }

    public function send(MailMessageInterface $message): void
    {
        if (!$message instanceof MailMessage) {
            throw new \InvalidArgumentException(
                'Expected ' . MailMessage::class . ', got ' . $message::class,
            );
        }

        foreach ($this->transformers as $transform) {
            $message = $transform($message);
        }

        $envelope = ($this->encoder)($message);
        $this->deliver($envelope);
    }

    private function deliver(Envelope $envelope): void
    {
        $maxAttempts = $this->config->maxRetries + 1;
        $lastException = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                ($this->transport)($envelope);
                return;
            } catch (SmtpConnectionException $e) {
                $lastException = $e;
            } catch (SmtpTransportException $e) {
                if (!$this->isTemporaryError($e)) {
                    throw $e;
                }
                $lastException = $e;
            }

            if ($attempt + 1 < $maxAttempts) {
                $this->delay($attempt);
            }
        }

        /** @var SmtpConnectionException|SmtpTransportException $lastException */
        throw $lastException;
    }

    private function isTemporaryError(SmtpTransportException $e): bool
    {
        $code = $e->getCode();

        return $code >= 400 && $code < 500;
    }

    private function delay(int $attempt): void
    {
        $delayMs = $this->config->retryDelayMs * (2 ** $attempt);
        usleep($delayMs * 1000);
    }

    /**
     * @param list<MailMessage> $messages
     */
    public function sendBatch(array $messages): BatchResult
    {
        $result = new BatchResult();

        foreach ($messages as $message) {
            try {
                $this->send($message);
                $result->addSuccess($message);
            } catch (\Throwable $e) {
                $result->addFailure($message, $e);
            }
        }

        ($this->disconnectFn)();

        return $result;
    }

    public function disconnect(): void
    {
        ($this->disconnectFn)();
    }

    /**
     * @return list<Closure(MailMessage): MailMessage>
     */
    private function buildTransformers(): array
    {
        $transformers = [];

        if ($this->config->fromAddress !== null) {
            $transformers[] = (new DefaultFrom($this->config->fromAddress, $this->config->fromName))->__invoke(...);
        }

        $transformers[] = (new MessageValidator())->__invoke(...);

        return $transformers;
    }

    /**
     * @param ?Closure(Envelope): void $transport
     * @return array{Closure(Envelope): void, Closure(): void}
     */
    private function buildTransport(?Closure $transport): array
    {
        if ($transport !== null) {
            return [$transport, static function (): void {
            }];
        }

        $smtp = new SmtpTransport($this->config);

        return [$smtp->__invoke(...), $smtp->disconnect(...)];
    }
}
