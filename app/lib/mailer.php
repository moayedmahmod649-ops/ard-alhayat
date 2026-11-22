<?php
declare(strict_types=1);
/**
 * Minimal SMTP mailer (AUTH LOGIN, STARTTLS/SSL). If SMTP is not configured in Settings, messages are
 * appended to app/storage/mail/outbox.log so nothing is lost and admins can see what would have been sent.
 */
function mail_configured(): bool { return setting('smtp_host') !== '' && setting('mail_from') !== ''; }
function send_mail(string $to, string $subject, string $text): bool {
    global $CONFIG;
    if (!valid_email($to)) return false;
    $from = setting('mail_from', 'no-reply@localhost');
    $fromName = setting('mail_from_name', 'Ard Al-Hayat');
    $headers = [
        'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
        'To: <' . $to . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . (explode('@', $from)[1] ?? 'localhost') . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    $data = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($text));
    if (!mail_configured()) {
        $dir = dirname($CONFIG['mail_log']); if (!is_dir($dir)) mkdir($dir, 0750, true);
        file_put_contents($CONFIG['mail_log'], "=== " . now_sql() . " to $to | $subject\n$text\n\n", FILE_APPEND | LOCK_EX);
        return true;
    }
    try { return smtp_send(setting('smtp_host'), (int)setting('smtp_port', '587'), setting('smtp_secure', 'tls'), setting('smtp_user'), setting('smtp_pass'), $from, $to, $data); }
    catch (Throwable $e) { error_log('SMTP: ' . $e->getMessage()); return false; }
}
function smtp_send(string $host, int $port, string $secure, string $user, string $pass, string $from, string $to, string $data): bool {
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $s = @stream_socket_client($remote, $errno, $err, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$s) throw new RuntimeException("connect: $err");
    stream_set_timeout($s, 15);
    $read = function () use ($s): string { $r = ''; while (($l = fgets($s, 1024)) !== false) { $r .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $r; };
    $cmd = function (string $c, array $ok) use ($s, $read): string { if ($c !== '') fwrite($s, $c . "\r\n"); $r = $read(); if (!in_array((int)substr($r, 0, 3), $ok, true)) throw new RuntimeException(trim($r)); return $r; };
    $cmd('', [220]);
    $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
    $cmd($ehlo, [250]);
    if ($secure === 'tls') { $cmd('STARTTLS', [220]); if (!stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('tls'); $cmd($ehlo, [250]); }
    if ($user !== '') { $cmd('AUTH LOGIN', [334]); $cmd(base64_encode($user), [334]); $cmd(base64_encode($pass), [235]); }
    $cmd('MAIL FROM:<' . $from . '>', [250]);
    $cmd('RCPT TO:<' . $to . '>', [250, 251]);
    $cmd('DATA', [354]);
    $cmd(preg_replace('/^\./m', '..', $data) . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($s);
    return true;
}
