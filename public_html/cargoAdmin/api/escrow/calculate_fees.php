<?php
require_once 'include/db.php';

function calculateBookingFees($totalAmount) {
    $commissionRate = 10; // 10% platform fee
    $platformFee = $totalAmount * ($commissionRate / 100);
    $ownerPayout = $totalAmount - $platformFee;
    
    return [
        'total_amount' => $totalAmount,
        'platform_fee' => round($platformFee, 2),
        'owner_payout' => round($ownerPayout, 2),
        'commission_rate' => $commissionRate
    ];
}

// API endpoint
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    
    $amount = $_GET['amount'] ?? 0;
    
    if ($amount <= 0) {
        echo json_encode(['error' => 'Invalid amount']);
        exit;
    }
    
    $fees = calculateBookingFees($amount);
    echo json_encode($fees);
}