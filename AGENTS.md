# jardisadapter/mailer

SMTP mailer on raw sockets with STARTTLS, AUTH LOGIN/PLAIN, MIME encoding, attachments, and connection keepalive for batch send. Only three user-facing classes: `Mailer`, `SmtpConfig`, `MailMessage`.

## Usage essentials

- **Entry point:** `new Mailer(new SmtpConfig(host: …, username: …, password: …, maxRetries: 3))`. `SmtpConfig` is a readonly VO; `fromAddress`/`fromName` are only applied when the message has no `From` set.
- **`MailMessage` is immutable with PSR-7-style `with*` pattern:** `MailMessage::create()->withFrom(…)->withTo(…)->withSubject(…)->withText(…)->withHtml(…)->withAttachment($bytes, $name)->withEmbeddedImage(…)`. `withTo()`/`withCc()`/`withBcc()` are additive. Interface getters (`from()`/`to()`/`attachments()`) return arrays; internal properties (`fromAddress`, `toAddresses`, …) are for handlers.
- **Pipeline:** Transformers (`DefaultFrom`, `MessageValidator`) → Encoder (`MimeEncoder` → `Envelope`) → Transport (`SmtpTransport`). Only configured handlers are instantiated; `Mailer` has zero business logic.
- **Retry is internal to Mailer** (not a wrapping handler): exponential backoff (`retryDelayMs`) on `SmtpConnectionException` and temporary 4xx errors; permanent 5xx errors are thrown immediately. `maxRetries: 0` = no retry.
- **Batch send shares one SMTP connection:** `sendBatch([$msg1, $msg2])` returns `BatchResult` with `successCount()`/`failureCount()`/`successful()`/`failed()`. `SmtpTransport` uses a NOOP health-check before connection reuse and silent reconnect on a dead connection.
- **Custom transport via `Closure(Envelope): void`** is injectable (testing / API send / log-to-file). All exceptions implement `JardisSupport\Contract\Mailer\MailerExceptionInterface` — generic catch is possible.

## Full reference

https://docs.jardis.io/en/adapter/mailer
