<?php

declare(strict_types=1);

namespace JardisAdapter\Mailer\Tests\Integration\Transport;

use JardisAdapter\Mailer\Data\Envelope;
use JardisAdapter\Mailer\Config\SmtpConfig;
use JardisAdapter\Mailer\Handler\SmtpTransport;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for SmtpTransport handler against a fake SMTP server.
 */
final class SmtpTransportTest extends TestCase
{
    private const PORT = 2525;

    /** @var resource|null */
    private $serverProcess = null;

    private string $outputFile;

    /** @var array<int, resource> */
    private array $serverPipes = [];

    protected function setUp(): void
    {
        $this->outputFile = sys_get_temp_dir() . '/smtp-test-' . uniqid() . '.json';
        $this->startServer();
    }

    protected function tearDown(): void
    {
        $this->stopServer();

        if (file_exists($this->outputFile)) {
            @unlink($this->outputFile);
        }
    }

    public function testSendEnvelopeToFakeServer(): void
    {
        $transport = new SmtpTransport($this->createConfig());

        $envelope = new Envelope(
            'sender@test.com',
            ['recipient@test.com'],
            "From: sender@test.com\r\nTo: recipient@test.com\r\nSubject: Test\r\n\r\nHello World",
        );

        $transport($envelope);
        $transport->disconnect();

        $messages = $this->getReceivedMessages();
        $this->assertCount(1, $messages);
        $this->assertSame('sender@test.com', $messages[0]['envelope']);
        $this->assertSame(['recipient@test.com'], $messages[0]['recipients']);
        $this->assertStringContainsString('Hello World', $messages[0]['data']);
    }

    public function testSendMultipleRecipientsToFakeServer(): void
    {
        $transport = new SmtpTransport($this->createConfig());

        $envelope = new Envelope(
            'sender@test.com',
            ['a@test.com', 'b@test.com', 'c@test.com'],
            "Subject: Multi\r\n\r\nBody",
        );

        $transport($envelope);
        $transport->disconnect();

        $messages = $this->getReceivedMessages();
        $this->assertCount(1, $messages);
        $this->assertSame(['a@test.com', 'b@test.com', 'c@test.com'], $messages[0]['recipients']);
    }

    public function testSendMultipleEnvelopesOverOneConnection(): void
    {
        $transport = new SmtpTransport($this->createConfig());

        $transport(new Envelope('a@test.com', ['b@test.com'], "Subject: First\r\n\r\nFirst body"));
        $transport(new Envelope('c@test.com', ['d@test.com'], "Subject: Second\r\n\r\nSecond body"));
        $transport->disconnect();

        $messages = $this->getReceivedMessages();
        $this->assertCount(2, $messages);
        $this->assertSame('a@test.com', $messages[0]['envelope']);
        $this->assertSame('c@test.com', $messages[1]['envelope']);
    }

    public function testAuthenticationWithFakeServer(): void
    {
        $config = new SmtpConfig(
            host: '127.0.0.1',
            port: self::PORT,
            encryption: 'none',
            username: 'testuser',
            password: 'testpass',
            timeout: 5,
        );

        $transport = new SmtpTransport($config);
        $transport(new Envelope('sender@test.com', ['recipient@test.com'], "Subject: Auth\r\n\r\nBody"));
        $transport->disconnect();

        $messages = $this->getReceivedMessages();
        $this->assertCount(1, $messages);
    }

    public function testDisconnectIsIdempotent(): void
    {
        $transport = new SmtpTransport($this->createConfig());
        $transport(new Envelope('a@test.com', ['b@test.com'], "Subject: Test\r\n\r\nBody"));
        $transport->disconnect();
        $transport->disconnect();

        $this->addToAssertionCount(1);
    }

    private function createConfig(): SmtpConfig
    {
        return new SmtpConfig(
            host: '127.0.0.1',
            port: self::PORT,
            encryption: 'none',
            timeout: 5,
        );
    }

    private function startServer(): void
    {
        $serverScript = dirname(__DIR__, 2) . '/Fixtures/smtp-server.php';
        $cmd = sprintf(
            'exec php %s %d %s',
            escapeshellarg($serverScript),
            self::PORT,
            escapeshellarg($this->outputFile),
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $this->serverProcess = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($this->serverProcess)) {
            $this->markTestSkipped('Could not start fake SMTP server');
        }

        $this->serverPipes = $pipes;

        $ready = fgets($pipes[1], 1024);
        if (trim((string) $ready) !== 'READY') {
            $this->stopServer();
            $this->markTestSkipped('SMTP server did not start properly');
        }
    }

    private function stopServer(): void
    {
        if ($this->serverProcess === null) {
            return;
        }

        foreach ($this->serverPipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_terminate($this->serverProcess, 15);
        proc_close($this->serverProcess);
        $this->serverProcess = null;

        usleep(100_000);
    }

    /**
     * @return list<array{envelope: string, recipients: list<string>, data: string}>
     */
    private function getReceivedMessages(): array
    {
        $this->stopServer();

        if (!file_exists($this->outputFile)) {
            return [];
        }

        $content = file_get_contents($this->outputFile);
        if ($content === false || $content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }
}
