<?php
/**
 * Reset Password API
 * POST JSON: {"email":"user@example.com","code":"123456","new_password":"..."}
 */

require_once __DIR__ . '/../../include/config.php';
require_once __DIR__ . '/../../include/db.php';

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
    $code = isset($data['code']) ? trim(strval($data['code'])) : '';
    $newPassword = isset($data['new_password']) ? strval($data['new_password']) : '';

    if (empty($email) || empty($code) || empty($newPassword)) {
        jsonError('Email, code, and new_password are required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Invalid email format');
    }

    if (strlen($newPassword) < 6) {
        jsonError('Password must be at least 6 characters');
    }

    // Load latest unused reset request
    $stmt = $conn->prepare(
        'SELECT id, user_id, code_hash, expires_at, used FROM password_resets WHERE email = ? AND used = 0 ORDER BY id DESC LIMIT 1'
    );
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        $stmt->close();
        jsonError('No active reset request found. Please request a new code.', 400);
    }

    $reset = $res->fetch_assoc();
    $stmt->close();

    // Expiry check
    $now = new DateTime('now', new DateTimeZone(TIMEZONE));
    $expiresAt = new DateTime($reset['expires_at'], new DateTimeZone(TIMEZONE));
    if ($now > $expiresAt) {
        // Mark as used
        $mark = $conn->prepare('UPDATE password_resets SET used = 1 WHERE id = ?');
        if ($mark) {
            $rid = intval($reset['id']);
            $mark->bind_param('i', $rid);
            $mark->execute();
            $mark->close();
        }
        jsonError('Reset code expired. Please request a new code.', 400);
    }

    if (!password_verify($code, $reset['code_hash'])) {
        jsonError('Invalid reset code', 400);
    }

    // Update password (store as hash for security)
    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

    $upd = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    if (!$upd) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    $uid = intval($reset['user_id']);
    $upd->bind_param('si', $hashed, $uid);
    $upd->execute();
    $upd->close();

    // Mark reset as used
    $mark = $conn->prepare('UPDATE password_resets SET used = 1 WHERE id = ?');
    if ($mark) {
        $rid = intval($reset['id']);
        $mark->bind_param('i', $rid);
        $mark->execute();
        $mark->close();
    }

    jsonSuccess('Password reset successful');

} catch (Exception $e) {
    debug_log('reset_password error', ['error' => $e->getMessage()]);
    if (DEBUG_MODE) {
        jsonError('Server error: ' . $e->getMessage(), 500);
    }
    jsonError('Failed to reset password. Please try again later.', 500);
}
