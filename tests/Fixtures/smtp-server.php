<?php

declare(strict_types=1);

/**
 * Minimal fake SMTP server for integration testing.
 *
 * Accepts connections, performs EHLO/STARTTLS/AUTH handshake,
 * receives DATA, and writes received messages to a temp file.
 *
 * Usage: php smtp-server.php <port> <output-file>
 */

$port = (int) ($argv[1] ?? 2525);
$outputFile = $argv[2] ?? '/tmp/smtp-test-output.json';

$server = stream_socket_server('tcp://127.0.0.1:' . $port, $errno, $errstr);

if ($server === false) {
    fwrite(STDERR, "Failed to start SMTP server: $errstr ($errno)\n");
    exit(1);
}

$messages = [];

// Write output on termination
pcntl_async_signals(true);
$shutdown = function () use (&$messages, $outputFile, $server): void {
    file_put_contents($outputFile, json_encode($messages, JSON_PRETTY_PRINT));
    if (is_resource($server)) {
        fclose($server);
    }
    exit(0);
};
pcntl_signal(SIGTERM, $shutdown);
pcntl_signal(SIGINT, $shutdown);

// Signal readiness
fwrite(STDOUT, "READY\n");
fflush(STDOUT);

while (true) {
    $client = @stream_socket_accept($server, 5);

    if ($client === false) {
        // Timeout — write current state and continue listening
        file_put_contents($outputFile, json_encode($messages, JSON_PRETTY_PRINT));
        continue;
    }

    handleClient($client, $messages);
    fclose($client);

    // Write after every client so data is available when test reads it
    file_put_contents($outputFile, json_encode($messages, JSON_PRETTY_PRINT));
}

/**
 * @param resource $client
 * @param list<array{envelope: string, recipients: list<string>, data: string}> $messages
 */
function handleClient($client, array &$messages): void
{
    writeLine($client, '220 localhost ESMTP FakeServer');

    $envelope = '';
    $recipients = [];
    $inData = false;
    $data = '';

    while (!feof($client)) {
        if ($inData) {
            $line = fgets($client, 8192);
            if ($line === false) {
                break;
            }
            if (trim($line) === '.') {
                $inData = false;
                $messages[] = [
                    'envelope' => $envelope,
                    'recipients' => $recipients,
                    'data' => $data,
                ];
                writeLine($client, '250 OK message accepted');
                $envelope = '';
                $recipients = [];
                $data = '';
                continue;
            }
            $data .= $line;
            continue;
        }

        $line = fgets($client, 1024);
        if ($line === false) {
            break;
        }

        $line = trim($line);
        $upper = strtoupper($line);

        if (str_starts_with($upper, 'EHLO') || str_starts_with($upper, 'HELO')) {
            writeLine($client, "250-localhost\r\n250-AUTH LOGIN PLAIN\r\n250-STARTTLS\r\n250 OK");
        } elseif ($upper === 'STARTTLS') {
            writeLine($client, '220 Ready to start TLS');
        } elseif (str_starts_with($upper, 'AUTH LOGIN')) {
            writeLine($client, '334 ' . base64_encode('Username:'));
            fgets($client, 1024); // username
            writeLine($client, '334 ' . base64_encode('Password:'));
            fgets($client, 1024); // password
            writeLine($client, '235 Authentication successful');
        } elseif (str_starts_with($upper, 'AUTH PLAIN')) {
            writeLine($client, '235 Authentication successful');
        } elseif (str_starts_with($upper, 'MAIL FROM:')) {
            $envelope = extractAddress($line);
            writeLine($client, '250 OK');
        } elseif (str_starts_with($upper, 'RCPT TO:')) {
            $recipients[] = extractAddress($line);
            writeLine($client, '250 OK');
        } elseif ($upper === 'DATA') {
            writeLine($client, '354 Start mail input');
            $inData = true;
        } elseif ($upper === 'QUIT') {
            writeLine($client, '221 Bye');
            break;
        } elseif ($upper === 'RSET') {
            $envelope = '';
            $recipients = [];
            $data = '';
            writeLine($client, '250 OK');
        } else {
            writeLine($client, '500 Unknown command');
        }
    }
}

/**
 * @param resource $client
 */
function writeLine($client, string $line): void
{
    fwrite($client, $line . "\r\n");
}

function extractAddress(string $line): string
{
    if (preg_match('/<([^>]+)>/', $line, $matches)) {
        return $matches[1];
    }

    return '';
}
