<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Data;

/**
 * Result of a batch mail send operation.
 */
final class BatchResult
{
    /** @var list<MailMessage> */
    private array $successful = [];

    /** @var list<array{message: MailMessage, error: \Throwable}> */
    private array $failed = [];

    public function addSuccess(MailMessage $message): void
    {
        $this->successful[] = $message;
    }

    public function addFailure(MailMessage $message, \Throwable $error): void
    {
        $this->failed[] = ['message' => $message, 'error' => $error];
    }

    /**
     * @return list<MailMessage>
     */
    public function successful(): array
    {
        return $this->successful;
    }

    /**
     * @return list<array{message: MailMessage, error: \Throwable}>
     */
    public function failed(): array
    {
        return $this->failed;
    }

    public function successCount(): int
    {
        return count($this->successful);
    }

    public function failureCount(): int
    {
        return count($this->failed);
    }

    public function isAllSuccessful(): bool
    {
        return $this->failed === [];
    }
}
