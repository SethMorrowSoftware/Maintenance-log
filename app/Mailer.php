<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * Outbound email.
 *
 * Two transports, no library:
 *
 *   mail()  the PHP built-in. Works on most cPanel accounts out of the box.
 *   smtp    a raw socket implementation of the bits of SMTP that matter:
 *           EHLO, STARTTLS, AUTH LOGIN / AUTH PLAIN, MAIL FROM, RCPT TO, DATA.
 *
 * Email is a convenience here, never a dependency. Every path returns a
 * boolean and logs its own failures; nothing in the application blocks on a
 * message going out.
 */
final class Mailer
{
    /** @var string last error, for the "send test email" button */
    private static string $lastError = '';

    /** @var list<string> transcript of the last SMTP conversation, for diagnostics */
    private static array $transcript = [];

    private function __construct()
    {
    }

    public static function lastError(): string
    {
        return self::$lastError;
    }

    /**
     * @return list<string>
     */
    public static function transcript(): array
    {
        return self::$transcript;
    }

    /**
     * Send an email.
     *
     * @param string|list<string> $to
     */
    public static function send(
        $to,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        ?string $replyTo = null
    ): bool {
        self::$lastError  = '';
        self::$transcript = [];

        if (!Settings::mailEnabled()) {
            self::$lastError = 'Email is switched off in Settings.';

            return false;
        }

        $recipients = self::normaliseRecipients($to);

        if ($recipients === []) {
            self::$lastError = 'No valid recipient address.';

            return false;
        }

        $fromEmail = trim((string) Settings::get('mail_from_email', ''));

        if ($fromEmail === '' || filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) {
            self::$lastError = 'No valid "from" address is configured in Settings > Email.';
            log_error('Mail not sent: ' . self::$lastError);

            return false;
        }

        $fromName = (string) Settings::get('mail_from_name', Settings::siteName());
        $subject  = self::sanitizeHeader($subject);
        $html     = self::wrapHtml($subject, $htmlBody);
        $text     = $textBody ?? self::htmlToText($htmlBody);

        $transport = (string) Settings::get('mail_transport', 'mail');

        try {
            if ($transport === 'smtp') {
                $ok = self::sendSmtp($recipients, $subject, $html, $text, $fromEmail, $fromName, $replyTo);
            } else {
                $ok = self::sendMailFunction($recipients, $subject, $html, $text, $fromEmail, $fromName, $replyTo);
            }
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            $ok = false;
        }

        if (!$ok) {
            log_error('Mail send failed: ' . self::$lastError, ['to' => implode(', ', $recipients), 'subject' => $subject]);
        }

        return $ok;
    }

    /**
     * @param  string|list<string> $to
     * @return list<string>
     */
    private static function normaliseRecipients($to): array
    {
        $list = is_array($to) ? $to : [$to];
        $out  = [];

        foreach ($list as $address) {
            $address = trim((string) $address);

            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL) !== false) {
                $out[] = $address;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Strip CR and LF so a header value cannot inject extra headers.
     */
    private static function sanitizeHeader(string $value): string
    {
        return trim((string) preg_replace('/[\r\n\0]+/', ' ', $value));
    }

    // -------------------------------------------------------------------------
    // Message construction
    // -------------------------------------------------------------------------

    /**
     * @param  list<string> $recipients
     * @return array{0: string, 1: string} [headers, body]
     */
    private static function buildMessage(
        array $recipients,
        string $subject,
        string $html,
        string $text,
        string $fromEmail,
        string $fromName,
        ?string $replyTo,
        bool $includeToHeader
    ): array {
        $boundary = 'rl_' . bin2hex(random_bytes(12));

        $headers = [];

        if ($includeToHeader) {
            $headers[] = 'To: ' . implode(', ', $recipients);
        }

        $headers[] = 'From: ' . self::formatAddress($fromEmail, $fromName);
        $headers[] = 'Reply-To: ' . ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false
            ? $replyTo
            : $fromEmail);
        $headers[] = 'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000';
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . self::hostname() . '>';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $headers[] = 'X-Mailer: RideLog ' . (defined('RIDELOG_VERSION') ? RIDELOG_VERSION : '1.0');
        $headers[] = 'Auto-Submitted: auto-generated';

        $body = "This is a message in MIME format.\r\n\r\n"
              . '--' . $boundary . "\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
              . self::quotedPrintable($text) . "\r\n\r\n"
              . '--' . $boundary . "\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
              . self::quotedPrintable($html) . "\r\n\r\n"
              . '--' . $boundary . "--\r\n";

        return [implode("\r\n", $headers), $body];
    }

    private static function formatAddress(string $email, string $name): string
    {
        $name = self::sanitizeHeader($name);

        if ($name === '') {
            return $email;
        }

        // Encode non-ASCII display names per RFC 2047.
        if (preg_match('/[^\x20-\x7E]/', $name)) {
            $name = '=?UTF-8?B?' . base64_encode($name) . '?=';

            return $name . ' <' . $email . '>';
        }

        return '"' . str_replace('"', '', $name) . '" <' . $email . '>';
    }

    private static function hostname(): string
    {
        $host = parse_url((string) Config::get('app.url', ''), PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return $host;
        }

        return (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
    }

    private static function quotedPrintable(string $text): string
    {
        return quoted_printable_encode(str_replace(["\r\n", "\r"], "\n", $text));
    }

    // -------------------------------------------------------------------------
    // Transport: PHP mail()
    // -------------------------------------------------------------------------

    /**
     * @param list<string> $recipients
     */
    private static function sendMailFunction(
        array $recipients,
        string $subject,
        string $html,
        string $text,
        string $fromEmail,
        string $fromName,
        ?string $replyTo
    ): bool {
        if (!function_exists('mail')) {
            self::$lastError = 'PHP mail() is disabled on this server. Switch the transport to SMTP in Settings.';

            return false;
        }

        [$headers, $body] = self::buildMessage(
            $recipients,
            $subject,
            $html,
            $text,
            $fromEmail,
            $fromName,
            $replyTo,
            false
        );

        $encodedSubject = preg_match('/[^\x20-\x7E]/', $subject)
            ? '=?UTF-8?B?' . base64_encode($subject) . '?='
            : $subject;

        // -f sets the envelope sender, which stops many hosts stamping the
        // message as coming from the web-server user and having it rejected.
        $result = @mail(
            implode(', ', $recipients),
            $encodedSubject,
            $body,
            $headers,
            '-f' . $fromEmail
        );

        if (!$result) {
            self::$lastError = 'PHP mail() returned false. Your host may block outgoing mail, '
                             . 'or the "from" address may not belong to this domain. '
                             . 'Try the SMTP transport instead.';
        }

        return (bool) $result;
    }

    // -------------------------------------------------------------------------
    // Transport: SMTP over a raw socket
    // -------------------------------------------------------------------------

    /**
     * @param list<string> $recipients
     */
    private static function sendSmtp(
        array $recipients,
        string $subject,
        string $html,
        string $text,
        string $fromEmail,
        string $fromName,
        ?string $replyTo
    ): bool {
        $host     = trim((string) Settings::get('smtp_host', ''));
        $port     = Settings::int('smtp_port', 587, 1, 65535);
        $secure   = strtolower((string) Settings::get('smtp_secure', 'tls'));
        $username = (string) Settings::get('smtp_user', '');
        $password = (string) Settings::get('smtp_pass', '');

        if ($host === '') {
            self::$lastError = 'No SMTP host is configured.';

            return false;
        }

        $timeout   = 15;
        $transport = $secure === 'ssl' ? 'ssl://' : '';

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
                'SNI_enabled'       => true,
            ],
        ]);

        $socket = @stream_socket_client(
            $transport . $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket === false) {
            self::$lastError = 'Could not connect to ' . $host . ':' . $port
                             . ($errstr !== '' ? ' — ' . $errstr : '') . '.';

            return false;
        }

        stream_set_timeout($socket, $timeout);

        try {
            if (!self::expect($socket, 220)) {
                return false;
            }

            $ehloHost = self::hostname();

            if (!self::command($socket, 'EHLO ' . $ehloHost, 250)) {
                // Some very old servers only speak HELO.
                if (!self::command($socket, 'HELO ' . $ehloHost, 250)) {
                    return false;
                }
            }

            if ($secure === 'tls') {
                if (!self::command($socket, 'STARTTLS', 220)) {
                    return false;
                }

                $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;

                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }

                if (@stream_socket_enable_crypto($socket, true, $crypto) !== true) {
                    self::$lastError = 'STARTTLS negotiation failed. Check the port and encryption setting.';

                    return false;
                }

                // The server must be greeted again over the encrypted channel.
                if (!self::command($socket, 'EHLO ' . $ehloHost, 250)) {
                    return false;
                }
            }

            if ($username !== '') {
                if (!self::authenticate($socket, $username, $password)) {
                    return false;
                }
            }

            if (!self::command($socket, 'MAIL FROM:<' . $fromEmail . '>', 250)) {
                return false;
            }

            foreach ($recipients as $recipient) {
                if (!self::command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251])) {
                    return false;
                }
            }

            if (!self::command($socket, 'DATA', 354)) {
                return false;
            }

            [$headers, $body] = self::buildMessage(
                $recipients,
                $subject,
                $html,
                $text,
                $fromEmail,
                $fromName,
                $replyTo,
                true
            );

            $encodedSubject = preg_match('/[^\x20-\x7E]/', $subject)
                ? '=?UTF-8?B?' . base64_encode($subject) . '?='
                : $subject;

            $message = 'Subject: ' . $encodedSubject . "\r\n" . $headers . "\r\n\r\n" . $body;

            // Dot-stuffing: a line that is just "." would end the message early.
            $message = preg_replace('/^\./m', '..', $message);

            self::write($socket, (string) $message . "\r\n.");

            if (!self::expect($socket, 250)) {
                return false;
            }

            self::command($socket, 'QUIT', [221, 250]);

            return true;
        } finally {
            if (is_resource($socket)) {
                @fclose($socket);
            }
        }
    }

    /**
     * @param resource $socket
     */
    private static function authenticate($socket, string $username, string $password): bool
    {
        // AUTH LOGIN is the most widely supported.
        if (self::command($socket, 'AUTH LOGIN', 334)) {
            if (!self::command($socket, base64_encode($username), 334)) {
                return false;
            }

            if (!self::command($socket, base64_encode($password), 235)) {
                self::$lastError = 'SMTP authentication was rejected. Check the username and password.';

                return false;
            }

            return true;
        }

        // Fall back to AUTH PLAIN.
        $credentials = base64_encode("\0" . $username . "\0" . $password);

        if (self::command($socket, 'AUTH PLAIN ' . $credentials, 235)) {
            return true;
        }

        self::$lastError = 'SMTP authentication failed. The server did not accept AUTH LOGIN or AUTH PLAIN.';

        return false;
    }

    /**
     * @param resource         $socket
     * @param int|list<int>    $expected
     */
    private static function command($socket, string $command, $expected): bool
    {
        // Keep credentials out of the transcript shown on screen.
        $display = preg_match('/^(AUTH|[A-Za-z0-9+\/=]{16,})/', $command) ? '[credentials]' : $command;
        self::$transcript[] = '> ' . $display;

        self::write($socket, $command);

        return self::expect($socket, $expected);
    }

    /**
     * @param resource $socket
     */
    private static function write($socket, string $data): void
    {
        @fwrite($socket, $data . "\r\n");
    }

    /**
     * Read a (possibly multi-line) reply and check its status code.
     *
     * @param resource      $socket
     * @param int|list<int> $expected
     */
    private static function expect($socket, $expected): bool
    {
        $codes = is_array($expected) ? $expected : [$expected];
        $reply = '';

        while (is_resource($socket) && !feof($socket)) {
            $line = fgets($socket, 515);

            if ($line === false) {
                break;
            }

            $reply .= $line;

            // A hyphen in the fourth column means more lines follow.
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        $reply = trim($reply);
        self::$transcript[] = '< ' . $reply;

        $code = (int) substr($reply, 0, 3);

        if (in_array($code, $codes, true)) {
            return true;
        }

        if (self::$lastError === '') {
            self::$lastError = 'The mail server replied: ' . ($reply !== '' ? $reply : 'no response')
                             . ' (expected ' . implode(' or ', array_map('strval', $codes)) . ').';
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Templating
    // -------------------------------------------------------------------------

    /**
     * Wrap body HTML in a simple, robust email template.
     *
     * Table layout and inline styles, because that is what email clients
     * actually render. No external CSS, no web fonts, no images.
     */
    public static function wrapHtml(string $title, string $body): string
    {
        $siteName = e(Settings::siteName());
        $appUrl   = e(rtrim((string) Config::get('app.url', ''), '/'));
        $safeTtl  = e($title);
        $year     = Dates::localNow()->format('Y');

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$safeTtl}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f8;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f8;padding:24px 12px;">
  <tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e3e6ec;border-radius:12px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;">
      <tr>
        <td style="background:#4f46e5;padding:18px 24px;color:#ffffff;font-size:16px;font-weight:600;">
          {$siteName}
        </td>
      </tr>
      <tr>
        <td style="padding:24px;color:#1f2430;font-size:15px;line-height:1.6;">
          <h1 style="margin:0 0 16px;font-size:18px;line-height:1.4;color:#1f2430;">{$safeTtl}</h1>
          {$body}
        </td>
      </tr>
      <tr>
        <td style="padding:16px 24px;border-top:1px solid #eef0f4;color:#78808f;font-size:12px;line-height:1.5;">
          Sent by {$siteName} &middot; <a href="{$appUrl}" style="color:#4f46e5;text-decoration:none;">{$appUrl}</a><br>
          &copy; {$year}. You are receiving this because you have an account on this system.
          You can turn these emails off in your profile.
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }

    /** A readable plain-text fallback built from the HTML body. */
    public static function htmlToText(string $html): string
    {
        $text = preg_replace('#<br\s*/?>#i', "\n", $html);
        $text = preg_replace('#</(p|div|h[1-6]|li|tr)>#i', "\n\n", (string) $text);
        $text = preg_replace('#<li[^>]*>#i', ' - ', (string) $text);

        // Keep the destination of links, which is the useful part in plain text.
        $text = preg_replace('#<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', '$2 ($1)', (string) $text);

        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", (string) $text);

        return trim((string) $text);
    }

    // -------------------------------------------------------------------------
    // Diagnostics
    // -------------------------------------------------------------------------

    /**
     * Send a test message, used by the button on the Settings screen.
     *
     * @return array{ok: bool, message: string, transcript: list<string>}
     */
    public static function sendTest(string $to): array
    {
        $siteName = Settings::siteName();
        $when     = Dates::datetime(Dates::nowUtc());

        $body = '<p>This is a test message from <strong>' . e($siteName) . '</strong>.</p>'
              . '<p>If you are reading it, outgoing email is working. Sent at ' . e($when) . '.</p>';

        $ok = self::send($to, 'Test message from ' . $siteName, $body);

        return [
            'ok'         => $ok,
            'message'    => $ok
                ? 'Test message sent to ' . $to . '. Check the inbox, and the spam folder.'
                : (self::$lastError !== '' ? self::$lastError : 'The message could not be sent.'),
            'transcript' => self::$transcript,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function transportChoices(): array
    {
        return [
            'mail' => 'PHP mail() — works on most cPanel hosting',
            'smtp' => 'SMTP — more reliable delivery, needs a mailbox',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function encryptionChoices(): array
    {
        return [
            'tls'  => 'STARTTLS (port 587)',
            'ssl'  => 'SSL/TLS (port 465)',
            'none' => 'None (port 25)',
        ];
    }
}
