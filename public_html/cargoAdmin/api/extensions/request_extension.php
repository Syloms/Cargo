<?php
/**
 * Request Rental Extension API
 * Allows renter to request extending the rental period
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../../include/db.php';
require_once __DIR__ . '/../security/suspension_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$bookingId = $_POST['booking_id'] ?? null;
$userId = $_POST['user_id'] ?? null;
$requestedReturnDate = $_POST['requested_return_date'] ?? null;
$reason = $_POST['reason'] ?? '';

// Validation
if (!$bookingId || !$userId || !$requestedReturnDate) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Block suspended users
require_not_suspended($conn, intval($userId));

// Get booking details
$bookingSql = "SELECT 
    b.*,
    DATEDIFF(?, b.return_date) as extension_days,
    TIMESTAMPDIFF(HOUR, NOW(), CONCAT(b.return_date, ' ', b.return_time)) as hours_until_return
FROM bookings b
WHERE b.id = ? AND b.user_id = ? AND b.status = 'approved'";

$stmt = mysqli_prepare($conn, $bookingSql);
mysqli_stmt_bind_param($stmt, "sii", $requestedReturnDate, $bookingId, $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or not eligible for extension']);
    exit;
}

$booking = mysqli_fetch_assoc($result);

// Validation checks
if ($booking['extension_days'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'Extension date must be after current return date']);
    exit;
}

if ($booking['extension_days'] > 7) {
    echo json_encode(['success' => false, 'message' => 'Maximum extension is 7 days per request']);
    exit;
}

// Check if already overdue
if ($booking['hours_until_return'] < 0) {
    echo json_encode(['success' => false, 'message' => 'Cannot request extension for overdue booking. Please contact owner directly.']);
    exit;
}

// Check if extension already requested
$checkSql = "SELECT id FROM rental_extensions WHERE booking_id = ? AND status = 'pending'";
$stmt = mysqli_prepare($conn, $checkSql);
mysqli_stmt_bind_param($stmt, "i", $bookingId);
mysqli_stmt_execute($stmt);
$checkResult = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($checkResult) > 0) {
    echo json_encode(['success' => false, 'message' => 'Extension request already pending']);
    exit;
}

// Calculate extension fee
$baseRate = $booking['price_per_day'];
$extensionFee = $baseRate * $booking['extension_days'];

// Add rush fee if requested less than 24 hours before return
if ($booking['hours_until_return'] < 24) {
    $rushFeePercent = 0.20; // 20% rush fee
    $extensionFee *= (1 + $rushFeePercent);
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Create extension request
    $insertSql = "INSERT INTO rental_extensions 
        (booking_id, requested_by, original_return_date, requested_return_date, 
         extension_days, extension_fee, reason, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $stmt = mysqli_prepare($conn, $insertSql);
    mysqli_stmt_bind_param($stmt, "iissids", 
        $bookingId, 
        $userId, 
        $booking['return_date'], 
        $requestedReturnDate, 
        $booking['extension_days'], 
        $extensionFee, 
        $reason
    );
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to create extension request');
    }
    
    // Update booking
    $updateSql = "UPDATE bookings SET extension_requested = 1 WHERE id = ?";
    $stmt = mysqli_prepare($conn, $updateSql);
    mysqli_stmt_bind_param($stmt, "i", $bookingId);
    mysqli_stmt_execute($stmt);
    
    // Notify owner
    $notifSql = "INSERT INTO notifications (user_id, title, message, type, created_at)
                 VALUES (?, ?, ?, 'info', NOW())";
    $stmt = mysqli_prepare($conn, $notifSql);
    $title = "📅 Extension Request";
    $message = "Renter has requested to extend booking #{$bookingId} by {$booking['extension_days']} day(s). Extension fee: ₱" . number_format($extensionFee, 2);
    mysqli_stmt_bind_param($stmt, "iss", $booking['owner_id'], $title, $message);
    mysqli_stmt_execute($stmt);
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Extension request submitted successfully',
        'extension_days' => $booking['extension_days'],
        'extension_fee' => $extensionFee,
        'requested_return_date' => $requestedReturnDate
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
