<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Handler;

use JardisAdapter\Mailer\Config\SmtpConfig;
use JardisAdapter\Mailer\Data\Envelope;
use JardisAdapter\Mailer\Exception\SmtpAuthenticationException;
use JardisAdapter\Mailer\Exception\SmtpConnectionException;
use JardisAdapter\Mailer\Exception\SmtpTransportException;
use JardisSupport\Contract\Mailer\MailTransportInterface;

/**
 * Invokable SMTP transport — delivers Envelope via socket-based SMTP.
 */
final class SmtpTransport implements MailTransportInterface
{
    /** @var resource|null */
    private mixed $socket = null;

    /** @var list<string> */
    private array $extensions = [];

    public function __construct(
        private readonly SmtpConfig $config,
    ) {
    }

    public function __invoke(Envelope $envelope): void
    {
        $this->send($envelope->sender, $envelope->recipients, $envelope->rawMessage);
    }

    /**
     * @param array<string> $recipients
     */
    public function send(string $sender, array $recipients, string $rawMessage): void
    {
        $this->ensureConnected();

        $this->sendCommand('MAIL FROM:<' . $sender . '>', 250);

        foreach ($recipients as $recipient) {
            $this->sendCommand('RCPT TO:<' . $recipient . '>', [250, 251]);
        }

        $this->sendCommand('DATA', 354);
        $this->sendRawData($rawMessage);
        $this->readResponse(250);
    }

    public function disconnect(): void
    {
        if ($this->socket === null) {
            return;
        }

        try {
            $this->sendCommand('QUIT', 221);
        } catch (\Throwable) {
            // Best-effort disconnect
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
            $this->socket = null;
            $this->extensions = [];
        }
    }

    public function isConnected(): bool
    {
        return $this->socket !== null && is_resource($this->socket);
    }

    private function ensureConnected(): void
    {
        if ($this->isConnected()) {
            if ($this->healthCheck()) {
                return;
            }
            $this->forceDisconnect();
        }

        $this->connect();
    }

    private function healthCheck(): bool
    {
        try {
            $this->sendCommand('NOOP', 250);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function forceDisconnect(): void
    {
        if ($this->socket !== null && is_resource($this->socket)) {
            @fclose($this->socket);
        }
        $this->socket = null;
        $this->extensions = [];
    }

    private function connect(): void
    {
        $host = $this->config->host;
        $port = $this->config->port;

        if ($this->config->encryption === 'ssl') {
            $host = 'ssl://' . $host;
        }

        $errstr = '';
        $errno = 0;

        $socket = @stream_socket_client(
            $host . ':' . $port,
            $errno,
            $errstr,
            $this->config->timeout,
            STREAM_CLIENT_CONNECT,
            $this->createStreamContext(),
        );

        if ($socket === false) {
            throw new SmtpConnectionException(
                'Failed to connect to SMTP server ' . $this->config->host . ':' . $port . ': ' . $errstr,
                (int) $errno,
            );
        }

        $this->socket = $socket;
        stream_set_timeout($this->socket, $this->config->timeout);

        $this->readResponse(220);
        $this->ehlo();

        if ($this->config->encryption === 'tls') {
            $this->startTls();
        }

        if ($this->config->username !== null && $this->config->password !== null) {
            $this->authenticate();
        }
    }

    private function ehlo(): void
    {
        $hostname = gethostname() ?: 'localhost';
        $response = $this->sendCommand('EHLO ' . $hostname, [250]);

        $this->extensions = [];
        $lines = explode("\r\n", $response);
        foreach ($lines as $line) {
            if (strlen($line) > 4) {
                $this->extensions[] = strtoupper(trim(substr($line, 4)));
            }
        }
    }

    private function startTls(): void
    {
        if (!in_array('STARTTLS', $this->extensions, true)) {
            throw new SmtpConnectionException(
                'SMTP server does not support STARTTLS',
            );
        }

        $this->sendCommand('STARTTLS', 220);

        if ($this->socket === null) {
            throw new SmtpConnectionException('Not connected to SMTP server');
        }

        $result = @stream_socket_enable_crypto(
            $this->socket,
            true,
            STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
        );

        if ($result !== true) {
            throw new SmtpConnectionException('TLS handshake failed');
        }

        $this->ehlo();
    }

    private function authenticate(): void
    {
        /** @var string $username checked before calling authenticate() */
        $username = $this->config->username;
        /** @var string $password checked before calling authenticate() */
        $password = $this->config->password;

        if (in_array('AUTH LOGIN', $this->extensions, true) || $this->supportsAuth('LOGIN')) {
            $this->authLogin($username, $password);
            return;
        }

        if (in_array('AUTH PLAIN', $this->extensions, true) || $this->supportsAuth('PLAIN')) {
            $this->authPlain($username, $password);
            return;
        }

        throw new SmtpAuthenticationException(
            'SMTP server does not support LOGIN or PLAIN authentication',
        );
    }

    private function supportsAuth(string $mechanism): bool
    {
        foreach ($this->extensions as $ext) {
            if (str_starts_with($ext, 'AUTH ') && str_contains($ext, $mechanism)) {
                return true;
            }
        }

        return false;
    }

    private function authLogin(string $username, string $password): void
    {
        $this->sendCommand('AUTH LOGIN', 334);
        $this->sendCommand(base64_encode($username), 334);

        try {
            $this->sendCommand(base64_encode($password), 235);
        } catch (SmtpTransportException $e) {
            throw new SmtpAuthenticationException(
                'SMTP authentication failed: ' . $e->getMessage(),
                $e->getCode(),
                $e,
            );
        }
    }

    private function authPlain(string $username, string $password): void
    {
        $credentials = base64_encode("\0" . $username . "\0" . $password);

        try {
            $this->sendCommand('AUTH PLAIN ' . $credentials, 235);
        } catch (SmtpTransportException $e) {
            throw new SmtpAuthenticationException(
                'SMTP authentication failed: ' . $e->getMessage(),
                $e->getCode(),
                $e,
            );
        }
    }

    /**
     * @param int|list<int> $expectedCode
     */
    private function sendCommand(string $command, int|array $expectedCode): string
    {
        $this->write($command . "\r\n");

        return $this->readResponse($expectedCode);
    }

    private function sendRawData(string $data): void
    {
        $data = str_replace("\r\n.", "\r\n..", $data);

        if (!str_ends_with($data, "\r\n")) {
            $data .= "\r\n";
        }

        $this->write($data . ".\r\n");
    }

    private function write(string $data): void
    {
        if ($this->socket === null) {
            throw new SmtpConnectionException('Not connected to SMTP server');
        }

        $result = @fwrite($this->socket, $data);

        if ($result === false) {
            throw new SmtpConnectionException('Failed to write to SMTP socket');
        }
    }

    /**
     * @param int|list<int> $expectedCode
     */
    private function readResponse(int|array $expectedCode): string
    {
        if ($this->socket === null) {
            throw new SmtpConnectionException('Not connected to SMTP server');
        }

        $expectedCodes = is_array($expectedCode) ? $expectedCode : [$expectedCode];
        $response = '';

        do {
            $line = @fgets($this->socket, 512);

            if ($line === false) {
                $info = stream_get_meta_data($this->socket);
                if ($info['timed_out']) {
                    throw new SmtpConnectionException('SMTP read timeout');
                }
                throw new SmtpConnectionException('Failed to read from SMTP socket');
            }

            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');

        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $expectedCodes, true)) {
            throw new SmtpTransportException(
                'Unexpected SMTP response: ' . trim($response),
                $code,
            );
        }

        return $response;
    }

    /** @return resource */
    private function createStreamContext()
    {
        return stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);
    }
}
