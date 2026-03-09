<?php
/**
 * Admin profile helper
 *
 * Provides:
 *   - $currentAdmin (array)
 *   - $currentAdminName (string)
 *   - $currentAdminAvatarUrl (string)
 *
 * Requirements:
 *   - session_start() should already have been called by the page.
 *   - include/db.php should already be loaded (provides $conn).
 */

if (!isset($conn)) {
    // Keep this include side-effect-free; page should include db.php first.
    throw new RuntimeException('admin_profile.php requires $conn (include/db.php) to be loaded before including this file.');
}

$adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;

$currentAdmin = [
    'id' => $adminId,
    'fullname' => $_SESSION['admin_name'] ?? $_SESSION['fullname'] ?? 'Admin',
    'profile_image' => null,
];

if ($adminId > 0) {
    $stmt = $conn->prepare('SELECT id, fullname, profile_image FROM admin WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $adminId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res && $row = $res->fetch_assoc()) {
                $currentAdmin = array_merge($currentAdmin, $row);
            }
        }
        $stmt->close();
    }
}

$currentAdminName = trim((string)($currentAdmin['fullname'] ?? 'Admin'));
if ($currentAdminName === '') {
    $currentAdminName = 'Admin';
}

// If a stored profile image exists, use it; else fallback to initials avatar.
if (!empty($currentAdmin['profile_image'])) {
    // profile_image in this project is stored as a relative path like "uploads/admin/admin_1_xxx.jpg"
    $currentAdminAvatarUrl = htmlspecialchars($currentAdmin['profile_image'], ENT_QUOTES, 'UTF-8');
} else {
    $currentAdminAvatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($currentAdminName) . '&background=1a1a1a&color=fff';
}
