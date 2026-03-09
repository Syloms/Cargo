<?php
/**
 * Suspension Guard
 * 
 * Usage:
 *   require_once __DIR__ . '/../security/suspension_guard.php';
 *   require_not_suspended($conn, $userId);
 */

function require_not_suspended(mysqli $conn, int $userId): void {
    if ($userId <= 0) {
        echo json_encode([
            'success' => false,
            'error_code' => 'INVALID_USER',
            'message' => 'Invalid user id'
        ]);
        exit;
    }

    $stmt = $conn->prepare('SELECT is_suspended, suspension_reason FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'error_code' => 'SERVER_ERROR',
            'message' => 'Database error'
        ]);
        exit;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        echo json_encode([
            'success' => false,
            'error_code' => 'USER_NOT_FOUND',
            'message' => 'User not found'
        ]);
        exit;
    }

    if (!empty($row['is_suspended']) && intval($row['is_suspended']) === 1) {
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'error_code' => 'ACCOUNT_SUSPENDED',
            'message' => 'Account suspended. Please contact support.',
            'reason' => $row['suspension_reason']
        ]);
        exit;
    }
}
