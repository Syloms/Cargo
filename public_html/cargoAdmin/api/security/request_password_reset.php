<?php
/**
 * Request Password Reset API
 * POST JSON: {"email": "user@example.com"}
 * Returns: {success, message, data:{expires_in, code?}}
 */

require_once __DIR__ . '/../../include/config.php';
require_once __DIR__ . '/../../include/db.php';
require_once __DIR__ . '/../../include/smtp_mailer.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonError('Invalid JSON: ' . json_last_error_msg());
    }

    $email = isset($data['email']) ? trim(strtolower($data['email'])) : '';

    if (empty($email)) {
        jsonError('Email is required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Invalid email format');
    }

    // Look up user
    $stmt = $conn->prepare('SELECT id, email FROM users WHERE email = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Do not reveal whether an email exists in production.
        if (DEBUG_MODE) {
            jsonError('Email not found', 404);
        }
        jsonSuccess('If that email exists, a reset code has been sent.');
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Ensure reset table exists
    $conn->query(
        "CREATE TABLE IF NOT EXISTS password_resets (\n" .
        "  id INT AUTO_INCREMENT PRIMARY KEY,\n" .
        "  user_id INT NOT NULL,\n" .
        "  email VARCHAR(255) NOT NULL,\n" .
        "  code_hash VARCHAR(255) NOT NULL,\n" .
        "  expires_at DATETIME NOT NULL,\n" .
        "  used TINYINT(1) NOT NULL DEFAULT 0,\n" .
        "  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n" .
        "  INDEX(email),\n" .
        "  INDEX(user_id),\n" .
        "  INDEX(expires_at)\n" .
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $code = strval(random_int(100000, 999999));
    $codeHash = password_hash($code, PASSWORD_DEFAULT);

    $expiresMinutes = 15;
    $expiresAt = (new DateTime('now', new DateTimeZone(TIMEZONE)))
        ->add(new DateInterval('PT' . $expiresMinutes . 'M'))
        ->format('Y-m-d H:i:s');

    // Mark old unused codes as used to keep table tidy
    $cleanup = $conn->prepare('UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0');
    if ($cleanup) {
        $cleanup->bind_param('s', $email);
        $cleanup->execute();
        $cleanup->close();
    }

    $insert = $conn->prepare('INSERT INTO password_resets (user_id, email, code_hash, expires_at) VALUES (?, ?, ?, ?)');
    if (!$insert) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    $insert->bind_param('isss', $user['id'], $email, $codeHash, $expiresAt);
    $insert->execute();
    $insert->close();

    // Send code via SMTP email (Gmail)
    $subject = APP_NAME . ' - Password Reset Code';
    $htmlBody = "<p>Hello,</p>" .
        "<p>Your password reset code is:</p>" .
        "<h2 style=\"letter-spacing:2px;\">$code</h2>" .
        "<p>This code will expire in <strong>$expiresMinutes minutes</strong>.</p>" .
        "<p>If you did not request a password reset, you can ignore this email.</p>";

    try {
        send_smtp_email($email, $subject, $htmlBody);
    } catch (Exception $mailErr) {
        debug_log('SMTP send failed', ['error' => $mailErr->getMessage(), 'email' => $email]);

        // In DEBUG_MODE, return the code to allow testing even if SMTP isn't configured
        if (DEBUG_MODE) {
            jsonSuccess('Reset code generated (SMTP failed in DEBUG mode)', [
                'expires_in' => $expiresMinutes * 60,
                'code' => $code,
                'email_error' => $mailErr->getMessage(),
            ]);
        }

        jsonError('Failed to send reset code email. Please try again later.', 500);
    }

    // Success: do NOT return code when email is working
    jsonSuccess('Reset code sent to your email', [
        'expires_in' => $expiresMinutes * 60,
    ]);

} catch (Exception $e) {
    debug_log('request_password_reset error', ['error' => $e->getMessage()]);
    if (DEBUG_MODE) {
        jsonError('Server error: ' . $e->getMessage(), 500);
    }
    jsonError('Failed to request password reset. Please try again later.', 500);
}
