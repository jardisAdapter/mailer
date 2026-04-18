---
name: adapter-mailer
description: SMTP mailer with STARTTLS, AUTH, HTML/text, attachments, retry, keepalive. Use for Mailer, email sending.
user-invocable: false
---

# MAILER_COMPONENT_SKILL
> jardisadapter/mailer v1.0 | NS: `JardisAdapter\Mailer` | SMTP on raw sockets | PHP 8.2+

## ARCHITECTURE
```
Mailer (implements MailerInterface)
  Transformers (MailMessage → MailMessage, built from SmtpConfig):
    DefaultFrom        if SmtpConfig->fromAddress != null
    MessageValidator   always (validates From, To, Body)
  Encoder (MailMessage → Envelope):
    MimeEncoder        MIME assembly, Base64, QP, RFC 2047
  Transport (Envelope → void):
    SmtpTransport      Socket SMTP — EHLO, STARTTLS / implicit SSL,
                       AUTH LOGIN + AUTH PLAIN, NOOP health-check,
                       silent reconnect, keepalive for batch
  Retry (internal to Mailer):
    Exponential backoff on SmtpConnectionException + temporary 4xx
    Permanent 5xx throws immediately
```
All classes under `Handler/` are invokable (`__invoke()`). Only configured handlers are instantiated. Mailer has zero business logic.

## RULES
- User-facing surface = `Mailer` + `SmtpConfig` + `MailMessage`. Handlers are internal — do not instantiate.
- `MailMessage` is immutable; builder uses `with*()` pattern (PSR-7 style).
- `SmtpConfig->fromAddress` is applied only when the message has no From.
- Retry: `maxRetries: 0` = no retry. Exponential delay: 100ms, 200ms, 400ms, …
- 4xx + connection errors → retry. 5xx → throw immediately.
- Transport is injectable as `?Closure(Envelope): void` (testing, logging, API).
- `sendBatch()` reuses one SMTP connection across all messages (keepalive + NOOP health-check).
- Logging / caching = integration-layer concern — Decorator on `MailerInterface`, never inside the package.

## CONTRACTS (from jardissupport/contract)
| Interface | Implementation |
|-----------|----------------|
| `MailerInterface` | `Mailer` — `send(MailMessageInterface): void` |
| `MailMessageInterface` | `MailMessage` — getters return arrays |
| `MailTransportInterface` | `SmtpTransport` — `send(string, array, string): void` + `disconnect()` |
| `MailerExceptionInterface` | all 4 exceptions implement this |

## USAGE
```php
use JardisAdapter\Mailer\Mailer;
use JardisAdapter\Mailer\Config\SmtpConfig;
use JardisAdapter\Mailer\Data\MailMessage;

$mailer = new Mailer(new SmtpConfig(
    host: 'smtp.example.com',
    username: 'user@example.com',
    password: 'secret',
    maxRetries: 3,
));

$message = MailMessage::create()
    ->withFrom('sender@example.com', 'Sender Name')
    ->withTo('recipient@example.com')
    ->withSubject('Confirmation')
    ->withHtml('<h1>Hi</h1>');

$mailer->send($message);

// Batch — one SMTP connection for all messages
$result = $mailer->sendBatch([$msg1, $msg2, $msg3]);
$result->successCount();     // int
$result->failureCount();     // int
$result->isAllSuccessful();  // bool
$result->successful();       // list<MailMessage>
$result->failed();           // list<['message' => MailMessage, 'error' => Throwable]>
```

## MAILMESSAGE — TRIPLE SURFACE
Immutable VO. Builder (`with*`) → Getter (Contract, arrays) → Property (Handler access, typed).

| Builder | Getter | Property | Shape |
|---------|--------|----------|-------|
| `withFrom(email, ?name)` | `from()` | `fromAddress` | `?Address` / `['address' => ..., 'name' => ...]\|null` |
| `withTo(email, ?name)` *(additive)* | `to()` | `toAddresses` | `list<Address>` / `list<['address'=>...,'name'=>...]>` |
| `withCc(email, ?name)` *(additive)* | `cc()` | `ccAddresses` | same as To |
| `withBcc(email, ?name)` *(additive)* | `bcc()` | `bccAddresses` | same as To |
| `withReplyTo(email, ?name)` | `replyTo()` | `replyToAddress` | `?Address` |
| `withSubject(subject)` | `subject()` | `subjectLine` | `?string` / `string` |
| `withText(text)` | `text()` | `textBody` | `?string` |
| `withHtml(html)` | `html()` | `htmlBody` | `?string` |
| `withAttachment(content, filename, ?contentType)` | `attachments()` | `attachmentList` | `list<Attachment>` / `list<['content','filename','mimeType','inline']>` |
| `withEmbeddedImage(content, filename, ?contentType)` | (in `attachments()`) | (in `attachmentList`, `inline = true`, Content-ID set) | — |
| `withHeader(name, value)` | — | `customHeaders` | `array<string, string>` |

## SMTPCONFIG (readonly value object)
```php
new SmtpConfig(
    host: 'smtp.example.com',   // required
    port: 587,                  // default 587
    encryption: 'tls',          // 'tls' (STARTTLS) | 'ssl' (implicit) | 'none'
    username: null,             // AUTH username
    password: null,             // AUTH password
    timeout: 30,                // connect + read/write timeout (s)
    fromAddress: null,          // default From (applied only if message has none)
    fromName: null,             // default From name
    maxRetries: 0,              // 0 = no retry
    retryDelayMs: 100,          // base for exponential backoff
);
```

## CUSTOM TRANSPORT (testing / logging / API)
```php
use JardisAdapter\Mailer\Data\Envelope;

$mailer = new Mailer(
    config: new SmtpConfig(host: 'localhost'),
    transport: function (Envelope $envelope): void {
        file_put_contents('/tmp/mail.log', $envelope->rawMessage);
    },
);
```

## DATA OBJECTS (readonly VOs)
| Class | Fields |
|-------|--------|
| `Address` | `email: string`, `name: ?string = null` |
| `Attachment` | `content: string`, `filename: string`, `contentType: ?string = null`, `inline: bool = false`, `contentId: ?string = null` |
| `Envelope` | `sender: string`, `recipients: array<string>`, `rawMessage: string` |
| `BatchResult` | fluent result of `sendBatch()` — see USAGE |

## EXCEPTIONS
All implement `MailerExceptionInterface`. Retry: connection errors + temporary 4xx. Permanent 5xx throws immediately.

| Exception | Trigger |
|-----------|---------|
| `SmtpConnectionException` | Host unreachable, TLS error, timeout |
| `SmtpAuthenticationException` | LOGIN/PLAIN rejected |
| `SmtpTransportException` | SMTP protocol error (rejected recipient, DATA error) |
| `MailMessageException` | No From, no To, no body |

Generic catch:
```php
use JardisSupport\Contract\Mailer\MailerExceptionInterface;
try { $mailer->send($message); }
catch (MailerExceptionInterface $e) { /* ... */ }
```

## FOUNDATION INTEGRATION
ENV variables consumed by `MailerHandler`:
```
MAIL_HOST, MAIL_PORT, MAIL_ENCRYPTION, MAIL_USERNAME, MAIL_PASSWORD, MAIL_TIMEOUT,
MAIL_FROM_ADDRESS, MAIL_FROM_NAME
```

**Three-state return from `MailerHandler`:**

| Return | Meaning |
|--------|---------|
| `Mailer` | Mailer active |
| `null` | Package not installed or `MAIL_HOST` not set |
| `false` | Explicitly disabled |

## ANTI-PATTERNS
- ❌ `new MimeEncoder()` / `new SmtpTransport()` directly — handlers are internal.
- ❌ Logging / caching inside the package — ✅ Decorator on `MailerInterface` in the caller.

## LAYER
- **Application:** inject `MailerInterface`.
- **Infrastructure:** build `Mailer` with `SmtpConfig` from ENV.
- **Domain:** never imports mailer classes.

## DEPENDENCIES
- `jardissupport/contract ^1.0` — MailerInterface, MailMessageInterface, MailTransportInterface
- `ext-openssl` — TLS / SSL
- `ext-mbstring` — UTF-8 header encoding
