<?php
/**
 * Submit Late Fee Payment API - With Detailed Logging
 */

$logFile = __DIR__ . '/../../late_fee_debug.log';

function logDebug($message) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

logDebug("=== API CALLED ===");
logDebug("POST data: " . json_encode($_POST));

try {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type');
    
    logDebug("Headers sent");
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logDebug("Invalid method: " . $_SERVER['REQUEST_METHOD']);
        echo json_encode(['success' => false, 'message' => 'Only POST allowed']);
        exit();
    }
    
    logDebug("Method validated");
    
    require_once '../../include/db.php';
    logDebug("DB included");
    
    // Get POST data
    $bookingId = $_POST['booking_id'] ?? null;
    $userId = $_POST['user_id'] ?? null;
    $totalAmount = $_POST['total_amount'] ?? null;
    $lateFeeAmount = $_POST['late_fee_amount'] ?? null;
    
    logDebug("Data extracted: booking=$bookingId, user=$userId, amount=$totalAmount");
    
    if (!$bookingId || !$userId || !$totalAmount) {
        logDebug("Missing fields");
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }
    
    logDebug("Validation passed");
    
    // Query booking
    $sql = "SELECT id, payment_status FROM bookings WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $bookingId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
    
    if (!$booking) {
        logDebug("Booking not found");
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit();
    }
    
    logDebug("Booking found: payment_status={$booking['payment_status']}");
    
    // Simple success response for testing
    logDebug("Returning success");
    echo json_encode([
        'success' => true,
        'message' => 'Test successful - actual insert disabled',
        'booking_id' => $bookingId,
        'payment_status' => $booking['payment_status']
    ]);
    
    logDebug("Response sent");
    
} catch (Exception $e) {
    logDebug("Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Error $e) {
    logDebug("Fatal Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $e->getMessage()]);
}

logDebug("=== END ===\n");
?>
