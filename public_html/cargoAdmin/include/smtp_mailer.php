<?php
/**
 * Minimal SMTP mail sender (AUTH LOGIN) with STARTTLS.
 * Works with Gmail SMTP when using an App Password.
 */

require_once __DIR__ . '/config.php';

function smtp_debug_log($msg) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log('[SMTP] ' . $msg);
    }
}

function smtp_read($fp) {
    $data = '';
    while (!feof($fp)) {
        $line = fgets($fp, 515);
        if ($line === false) break;
        $data .= $line;
        if (preg_match('/^\d{3}\s/', $line)) break;
    }
    smtp_debug_log('S: ' . trim(str_replace("\r\n", ' | ', $data)));
    return $data;
}

function smtp_write($fp, $cmd) {
    smtp_debug_log('C: ' . $cmd);
    fwrite($fp, $cmd . "\r\n");
}

function smtp_expect($response, $expectedCode) {
    $code = substr(trim($response), 0, 3);
    if ($code !== $expectedCode) {
        throw new Exception('SMTP unexpected response: ' . trim($response));
    }
}

function send_smtp_email($to, $subject, $htmlBody, $textBody = '') {
    foreach (['SMTP_HOST','SMTP_PORT','SMTP_USER','SMTP_PASS','SMTP_USE_TLS','SMTP_FROM_EMAIL','SMTP_FROM_NAME'] as $k) {
        if (!defined($k)) {
            throw new Exception('Missing SMTP config: ' . $k);
        }
    }

    if (SMTP_PASS === '' || SMTP_PASS === 'CHANGE_ME') {
        throw new Exception('SMTP_PASS not set. Use a Gmail App Password.');
    }

    $fp = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 20);
    if (!$fp) {
        throw new Exception("SMTP connect failed: $errstr ($errno)");
    }
    stream_set_timeout($fp, 20);

    smtp_expect(smtp_read($fp), '220');

    smtp_write($fp, 'EHLO ' . (defined('DOMAIN') ? DOMAIN : 'localhost'));
    smtp_expect(smtp_read($fp), '250');

    if (SMTP_USE_TLS) {
        smtp_write($fp, 'STARTTLS');
        smtp_expect(smtp_read($fp), '220');

        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception('Failed to enable TLS');
        }

        smtp_write($fp, 'EHLO ' . (defined('DOMAIN') ? DOMAIN : 'localhost'));
        smtp_expect(smtp_read($fp), '250');
    }

    smtp_write($fp, 'AUTH LOGIN');
    smtp_expect(smtp_read($fp), '334');

    smtp_write($fp, base64_encode(SMTP_USER));
    smtp_expect(smtp_read($fp), '334');

    smtp_write($fp, base64_encode(SMTP_PASS));
    smtp_expect(smtp_read($fp), '235');

    smtp_write($fp, 'MAIL FROM: <' . SMTP_FROM_EMAIL . '>');
    smtp_expect(smtp_read($fp), '250');

    smtp_write($fp, 'RCPT TO: <' . $to . '>');
    $rcpt = smtp_read($fp);
    $rcptCode = substr(trim($rcpt), 0, 3);
    if ($rcptCode !== '250' && $rcptCode !== '251') {
        throw new Exception('SMTP RCPT TO failed: ' . trim($rcpt));
    }

    smtp_write($fp, 'DATA');
    smtp_expect(smtp_read($fp), '354');

    if ($textBody === '') {
        $textBody = strip_tags($htmlBody);
    }

    $boundary = 'bnd_' . bin2hex(random_bytes(8));

    $headers = [];
    $headers[] = 'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>';
    $headers[] = 'To: <' . $to . '>';
    $headers[] = 'Subject: ' . $subject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    $message = implode("\r\n", $headers) . "\r\n\r\n";
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= $textBody . "\r\n\r\n";
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $message .= $htmlBody . "\r\n\r\n";
    $message .= "--$boundary--\r\n";

    fwrite($fp, $message . "\r\n.\r\n");
    smtp_expect(smtp_read($fp), '250');

    smtp_write($fp, 'QUIT');
    smtp_read($fp);
    fclose($fp);

    return true;
}
