<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/../include/db.php";
require_once __DIR__ . "/payment/transaction_logger.php";

if (!isset($_GET['booking_id'])) {
    echo json_encode(["success" => false, "message" => "Missing booking_id"]);
    exit;
}

$bookingId = intval($_GET['booking_id']);

try {
    $logger = new TransactionLogger($conn);
    
    // Get transaction history
    $history = $logger->getHistory($bookingId);
    
    // Get summary
    $summary = $logger->getSummary($bookingId);
    
    echo json_encode([
        "success" => true,
        "booking_id" => $bookingId,
        "transactions" => $history,
        "summary" => $summary
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}