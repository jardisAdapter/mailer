# Jardis Mailer

![Build Status](https://github.com/jardisAdapter/mailer/actions/workflows/ci.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](phpstan.neon)
[![PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](phpcs.xml)
[![Zero Dependencies](https://img.shields.io/badge/Dependencies-Zero*-brightgreen.svg)](#)

> Part of the **[Jardis Business Platform](https://jardis.io)** — Enterprise-grade PHP components for Domain-Driven Design

**Transactional emails without the bloat.** A lean SMTP mailer built on raw sockets — designed for DDD applications that send order confirmations, password resets, or notifications. No Swiftmailer, no Symfony Mailer, no dependency tree. Just SMTP over a socket.

<small>* Zero external PHP packages. Only `ext-openssl` + `ext-mbstring` + `jardissupport/contract` (interfaces only).</small>

---

## Why This Mailer?

- **Two classes are enough** — `Mailer` + `SmtpConfig`, nothing else to learn
- **Fluent message builder** — immutable, `with*` pattern like PSR-7
- **STARTTLS + implicit SSL** — secure by default, PORT 587 or 465
- **AUTH LOGIN & PLAIN** — standard SMTP authentication
- **HTML + plain text** — multipart/alternative, just works
- **File attachments & inline images** — Base64 encoded, Content-ID for HTML embedding
- **Retry with backoff** — automatic retry on connection errors and temporary SMTP failures
- **Connection keepalive** — send 100 emails over one SMTP connection
- **NOOP health-check** — stale connections are silently reconnected
- **96% test coverage** — integration tests against a real SMTP server, not mocks

---

## Installation

```bash
composer require jardisadapter/mailer
```

---

## Quick Start

### Send a Simple Email

```php
use JardisAdapter\Mailer\Mailer;
use JardisAdapter\Mailer\Config\SmtpConfig;
use JardisAdapter\Mailer\Data\MailMessage;

$mailer = new Mailer(new SmtpConfig(
    host: 'smtp.example.com',
    username: 'user@example.com',
    password: 'secret',
));

$message = MailMessage::create()
    ->withFrom('noreply@example.com', 'My App')
    ->withTo('customer@example.com', 'Jane Doe')
    ->withSubject('Your Order Confirmation')
    ->withText('Thank you for your order #1234.')
    ->withHtml('<h1>Thank you!</h1><p>Your order #1234 has been confirmed.</p>');

$mailer->send($message);
```

### HTML + Plain Text

```php
$message = MailMessage::create()
    ->withFrom('noreply@example.com')
    ->withTo('user@example.com')
    ->withSubject('Weekly Report')
    ->withText('Your weekly report is attached.')
    ->withHtml('<h1>Weekly Report</h1><p>See attachment.</p>');
```

Both bodies are sent as `multipart/alternative` — the recipient's mail client picks the best one.

### Attachments

```php
$message = MailMessage::create()
    ->withFrom('billing@example.com')
    ->withTo('customer@example.com')
    ->withSubject('Your Invoice')
    ->withText('Please find your invoice attached.')
    ->withAttachment(file_get_contents('invoice.pdf'), 'invoice.pdf', 'application/pdf')
    ->withAttachment($csvData, 'report.csv', 'text/csv');
```

### Inline Images in HTML

```php
$message = MailMessage::create()
    ->withFrom('news@example.com')
    ->withTo('subscriber@example.com')
    ->withSubject('Our Newsletter')
    ->withHtml('<h1>News</h1><img src="cid:logo">')
    ->withEmbeddedImage(file_get_contents('logo.png'), 'logo.png', 'image/png');
```

### Multiple Recipients, CC, BCC

```php
$message = MailMessage::create()
    ->withFrom('team@example.com')
    ->withTo('alice@example.com', 'Alice')
    ->withTo('bob@example.com', 'Bob')
    ->withCc('manager@example.com')
    ->withBcc('archive@example.com')
    ->withReplyTo('support@example.com')
    ->withSubject('Meeting Notes')
    ->withText('Notes from today.');
```

### Custom Headers

```php
$message = MailMessage::create()
    ->withFrom('alerts@example.com')
    ->withTo('admin@example.com')
    ->withSubject('Server Alert')
    ->withText('CPU at 95%')
    ->withHeader('X-Priority', '1')
    ->withHeader('X-Mailer', 'Jardis Mailer');
```

---

## Fully Configured

```php
$mailer = new Mailer(new SmtpConfig(
    host: 'smtp.example.com',
    port: 587,                    // Default: 587
    encryption: 'tls',            // 'tls' (STARTTLS), 'ssl' (implicit), 'none'
    username: 'user@example.com',
    password: 'secret',
    timeout: 30,                  // Connect + read/write timeout in seconds
    fromAddress: 'noreply@example.com',  // Default From (applied when not set on message)
    fromName: 'My Application',
    maxRetries: 3,                // Retry on connection errors and 4xx
    retryDelayMs: 200,            // Exponential backoff: 200ms, 400ms, 800ms
));
```

---

## Batch Sending

Send multiple emails over a single SMTP connection — the connection stays alive between messages:

```php
$messages = [];
foreach ($recipients as $recipient) {
    $messages[] = MailMessage::create()
        ->withFrom('noreply@example.com')
        ->withTo($recipient->email, $recipient->name)
        ->withSubject('Your monthly statement')
        ->withHtml($renderer->render($recipient));
}

$result = $mailer->sendBatch($messages);

echo $result->successCount() . ' sent, ' . $result->failureCount() . ' failed';

foreach ($result->failed() as $failure) {
    log($failure['message']->to(), $failure['error']->getMessage());
}
```

---

## Retry

```php
$mailer = new Mailer(new SmtpConfig(
    host: 'smtp.example.com',
    maxRetries: 3,          // Up to 3 retries
    retryDelayMs: 200,      // Exponential backoff: 200ms, 400ms, 800ms
));
```

Automatically retries on `SmtpConnectionException` and temporary SMTP errors (4xx). No retry on permanent errors (5xx) — those are recipient or policy errors.

---

## Error Handling

| Exception | When |
|-----------|------|
| `SmtpConnectionException` | Host unreachable, TLS handshake failure, timeout |
| `SmtpAuthenticationException` | LOGIN or PLAIN rejected |
| `SmtpTransportException` | SMTP protocol error (rejected recipient, DATA error) |
| `MailMessageException` | Invalid message (missing From, To, or body) |

All implement `MailerExceptionInterface` for generic catch:

```php
use JardisSupport\Contract\Mailer\MailerExceptionInterface;

try {
    $mailer->send($message);
} catch (MailerExceptionInterface $e) {
    // Any mailer error
}
```

---

## Encryption

| Port | Encryption | How it works |
|------|-----------|--------------|
| 587  | `tls` (default) | Connects plain, upgrades via STARTTLS |
| 465  | `ssl` | Connects over implicit TLS |
| 25   | `none` | No encryption (not recommended) |

---

## Architecture

The user only sees `Mailer` + `SmtpConfig` + `MailMessage`. Internally, the mailer orchestrates a pipeline of invokable handlers — built from the config:

```
Mailer (Orchestrator)
  │
  │  Transformers (MailMessage → MailMessage, built from config):
  │  ├── DefaultFrom          apply default sender if not set
  │  └── MessageValidator     validate before sending
  │
  │  Encoder (MailMessage → Envelope):
  │  └── MimeEncoder          MIME assembly, Base64, Quoted-Printable, RFC 2047
  │
  │  Transport (Envelope → void):
  │  └── SmtpTransport        socket-based SMTP with NOOP health-check
  │
  │  Retry (internal to Mailer):
  │  └── Exponential backoff on connection errors and 4xx
  │
  ▼
  send():
    foreach transformer → $message = $transform($message)
    $envelope = $encoder($message)
    $transport($envelope)        // with retry
```

Each handler is an **invokable object** (`__invoke`) — independently testable, replaceable, composable. Only what is configured gets instantiated.

### Custom Transport

The transport is a closure — replaceable for testing or alternative delivery:

```php
$mailer = new Mailer(
    config: new SmtpConfig(host: 'localhost'),
    transport: function (Envelope $envelope): void {
        // Log, mock, or send via API
        file_put_contents('/tmp/mail.log', $envelope->rawMessage);
    },
);
```

---

## Jardis Foundation Integration

In a Jardis DDD project, the mailer is automatically configured via ENV:

```env
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=user@example.com
MAIL_PASSWORD=secret
MAIL_TIMEOUT=30
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME=My Application
```

The `MailerHandler` in `JardisApp` builds the mailer and registers it in the ServiceRegistry. Your application code receives `MailerInterface` via injection — without ever importing `Mailer` directly.

---

## Development

```bash
cp .env.example .env    # One-time setup
make install             # Install dependencies
make phpunit             # Run tests
make phpstan             # Static analysis (Level 8)
make phpcs               # Coding standards (PSR-12)
```

---

## Documentation

Full documentation, guides, and API reference:

**[docs.jardis.io/en/adapter/mailer](https://docs.jardis.io/en/adapter/mailer)**

---

## License

[MIT License](LICENSE.md) — free for any use, including commercial.

<!-- BEGIN jardis/dev-skills README block — do not edit by hand -->
## KI-gestützte Entwicklung

Dieses Package liefert einen Skill für Claude Code, Cursor, Continue und Aider mit. Installation im Konsumentenprojekt:

```bash
composer require --dev jardis/dev-skills
```

Mehr Details: <https://docs.jardis.io/skills>
<!-- END jardis/dev-skills README block -->
