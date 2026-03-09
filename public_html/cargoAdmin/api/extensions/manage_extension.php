<?php
/**
 * Manage Extension Request (Approve/Reject)
 * For owners to approve or reject extension requests
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$extensionId = $_POST['extension_id'] ?? null;
$ownerId = $_POST['owner_id'] ?? null;
$action = $_POST['action'] ?? null; // 'approve' or 'reject'
$reason = $_POST['reason'] ?? '';

// Validation
if (!$extensionId || !$ownerId || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

if (!in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Get extension request details
$sql = "SELECT 
    e.*,
    b.owner_id,
    b.user_id as renter_id,
    b.total_amount
FROM rental_extensions e
JOIN bookings b ON e.booking_id = b.id
WHERE e.id = ? AND b.owner_id = ? AND e.status = 'pending'";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $extensionId, $ownerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Extension request not found or already processed']);
    exit;
}

$extension = mysqli_fetch_assoc($result);

// Start transaction
mysqli_begin_transaction($conn);

try {
    if ($action === 'approve') {
        // Update extension status
        $updateExtSql = "UPDATE rental_extensions 
                        SET status = 'approved', 
                            approved_by = ?, 
                            approval_reason = ?,
                            updated_at = NOW()
                        WHERE id = ?";
        $stmt = mysqli_prepare($conn, $updateExtSql);
        mysqli_stmt_bind_param($stmt, "isi", $ownerId, $reason, $extensionId);
        mysqli_stmt_execute($stmt);
        
        // Update booking
        $updateBookingSql = "UPDATE bookings 
                            SET extension_approved = 1,
                                extended_return_date = ?,
                                extension_fee = ?,
                                total_amount = total_amount + ?,
                                return_date = ?
                            WHERE id = ?";
        $stmt = mysqli_prepare($conn, $updateBookingSql);
        mysqli_stmt_bind_param($stmt, "sddsi", 
            $extension['requested_return_date'],
            $extension['extension_fee'],
            $extension['extension_fee'],
            $extension['requested_return_date'],
            $extension['booking_id']
        );
        mysqli_stmt_execute($stmt);
        
        // Notify renter
        $notifTitle = "✅ Extension Approved";
        $notifMessage = "Your extension request for booking #{$extension['booking_id']} has been approved! New return date: {$extension['requested_return_date']}. Extension fee: ₱" . number_format($extension['extension_fee'], 2);
        
    } else {
        // Reject extension
        $updateExtSql = "UPDATE rental_extensions 
                        SET status = 'rejected', 
                            approved_by = ?, 
                            approval_reason = ?,
                            updated_at = NOW()
                        WHERE id = ?";
        $stmt = mysqli_prepare($conn, $updateExtSql);
        mysqli_stmt_bind_param($stmt, "isi", $ownerId, $reason, $extensionId);
        mysqli_stmt_execute($stmt);
        
        // Update booking
        $updateBookingSql = "UPDATE bookings 
                            SET extension_requested = 0
                            WHERE id = ?";
        $stmt = mysqli_prepare($conn, $updateBookingSql);
        mysqli_stmt_bind_param($stmt, "i", $extension['booking_id']);
        mysqli_stmt_execute($stmt);
        
        // Notify renter
        $notifTitle = "❌ Extension Declined";
        $notifMessage = "Your extension request for booking #{$extension['booking_id']} was declined. Reason: {$reason}. Please return the vehicle on time.";
    }
    
    // Send notification
    $notifSql = "INSERT INTO notifications (user_id, title, message, type, created_at)
                 VALUES (?, ?, ?, 'info', NOW())";
    $stmt = mysqli_prepare($conn, $notifSql);
    mysqli_stmt_bind_param($stmt, "iss", $extension['renter_id'], $notifTitle, $notifMessage);
    mysqli_stmt_execute($stmt);
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => "Extension request {$action}d successfully",
        'action' => $action
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
