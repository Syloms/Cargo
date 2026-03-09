<?php
/**
 * SMTP test endpoint
 * POST JSON: {"to":"someone@example.com"}
 */

require_once __DIR__ . '/../../include/config.php';
require_once __DIR__ . '/../../include/smtp_mailer.php';

setCorsHeaders();

try {
    // Support both:
    // 1) GET /test_smtp.php?to=email@example.com  (easy browser test)
    // 2) POST JSON {"to":"email@example.com"}

    $to = '';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $to = isset($_GET['to']) ? trim($_GET['to']) : '';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonError('Invalid JSON: ' . json_last_error_msg());
        }

        $to = isset($data['to']) ? trim($data['to']) : '';
    } else {
        jsonError('Method not allowed', 405);
    }

    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        jsonError('Valid "to" email is required');
    }

    $subject = APP_NAME . ' SMTP Test';
    $body = '<p>If you received this, SMTP is working.</p>';

    send_smtp_email($to, $subject, $body);
    jsonSuccess('SMTP test email sent');

} catch (Exception $e) {
    debug_log('test_smtp error', ['error' => $e->getMessage()]);
    if (DEBUG_MODE) {
        jsonError('SMTP test failed: ' . $e->getMessage(), 500);
    }
    jsonError('SMTP test failed', 500);
}
