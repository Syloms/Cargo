<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../include/db.php';

if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

$userId = intval($_GET['user_id']);

try {
    // Get all payments for user with detailed booking info
    // FIXED: Only get the latest payment per booking to avoid duplicates
    $stmt = $conn->prepare("
        SELECT 
            p.id AS payment_id,
            p.booking_id,
            p.amount,
            p.payment_method,
            p.payment_reference,
            p.payment_status,
            p.created_at AS payment_date,
            p.verified_at,
            
            -- Booking details
            b.status AS booking_status,
            b.pickup_date,
            b.return_date,
            b.total_amount,
            b.platform_fee,
            b.owner_payout,
            b.escrow_status,
            b.payment_verified_at,
            b.vehicle_type,
            
            -- Car details (for cars)
            c.brand AS car_brand,
            c.model AS car_model,
            c.car_year AS car_year,
            c.image AS car_image,
            c.plate_number AS car_plate,
            
            -- Motorcycle details (for motorcycles)
            m.brand AS moto_brand,
            m.model AS moto_model,
            m.motorcycle_year AS moto_year,
            m.image AS moto_image,
            m.plate_number AS moto_plate,
            
            -- Owner details
            u.fullname AS owner_name,
            
            -- Receipt info
            r.receipt_no,
            r.receipt_url,
            r.generated_at AS receipt_date,
            
            -- Refund info
            ref.id AS refund_id,
            ref.status AS refund_status,
            ref.refund_amount,
            ref.created_at AS refund_requested_at
            
        FROM payments p
        INNER JOIN bookings b ON p.booking_id = b.id
        LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
        LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
        LEFT JOIN users u ON b.owner_id = u.id
        LEFT JOIN receipts r ON r.booking_id = b.id
        LEFT JOIN refunds ref ON ref.booking_id = b.id
        WHERE p.user_id = ?
        AND p.id = (
            SELECT MAX(id) 
            FROM payments 
            WHERE booking_id = p.booking_id
        )
        ORDER BY p.created_at DESC
    ");
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    $totalPaid = 0;
    $totalPending = 0;
    $verifiedCount = 0;
    $pendingCount = 0;
    
    while ($row = $result->fetch_assoc()) {
        // Determine vehicle type and get appropriate details
        $vehicleType = $row['vehicle_type'] ?? 'car';
        
        if ($vehicleType === 'motorcycle') {
            $brand = $row['moto_brand'];
            $model = $row['moto_model'];
            $year = $row['moto_year'];
            $vehicleImage = $row['moto_image'];
            $plateNumber = $row['moto_plate'];
        } else {
            $brand = $row['car_brand'];
            $model = $row['car_model'];
            $year = $row['car_year'];
            $vehicleImage = $row['car_image'];
            $plateNumber = $row['car_plate'];
        }
        
        // Format vehicle name
        $carFullName = trim($brand . ' ' . $model . ' ' . $year);
        
        // Format vehicle image with full URL
        $carImage = $vehicleImage;
        if (!empty($carImage) && strpos($carImage, 'http') !== 0) {
            // Load config if not already loaded
            if (!defined('BASE_URL')) {
                require_once __DIR__ . '/../include/config.php';
            }
            $carImage = BASE_URL . '/' . $carImage;
        }
        
        // Determine status badge
        $statusBadge = getPaymentStatusBadge($row['payment_status'], $row['escrow_status']);
        
        // Check if can request refund
        $canRefund = canRequestRefund($row);
        
        // Check if has receipt
        $hasReceipt = !empty($row['receipt_no']);
        
        // Update statistics
        // Don't count refunded transactions in pending
        $currentRefundStatus = determineRefundStatus($row);
        $isRefunded = ($currentRefundStatus === 'completed');
        
        if ($isRefunded) {
            // Refunded transactions don't count in pending or paid
            // They're in their own category
        } elseif (in_array($row['payment_status'], ['verified', 'paid', 'completed'])) {
            $totalPaid += floatval($row['amount']);
            $verifiedCount++;
        } elseif ($row['payment_status'] === 'pending') {
            $totalPending += floatval($row['amount']);
            $pendingCount++;
        }
        
        $payments[] = [
            'payment_id' => $row['payment_id'],
            'booking_id' => $row['booking_id'],
            'booking_reference' => '#BK-' . str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT),
            'amount' => floatval($row['amount']),
            'payment_method' => $row['payment_method'],
            'payment_reference' => $row['payment_reference'],
            'payment_status' => $row['payment_status'],
            'payment_date' => $row['payment_date'],
            'payment_date_formatted' => date('M d, Y h:i A', strtotime($row['payment_date'])),
            'verified_at' => $row['verified_at'],
            
            // Booking info
            'booking_status' => $row['booking_status'],
            'pickup_date' => $row['pickup_date'],
            'return_date' => $row['return_date'],
            
            // Car info
            'car_full_name' => $carFullName,
            'car_image' => $carImage,
            'plate_number' => $plateNumber,
            'vehicle_type' => $vehicleType,
            
            // Owner info
            'owner_name' => $row['owner_name'],
            
            // Escrow info
            'escrow_status' => $row['escrow_status'],
            'platform_fee' => floatval($row['platform_fee']),
            'owner_payout' => floatval($row['owner_payout']),
            
            // Receipt info
            'has_receipt' => $hasReceipt,
            'receipt_no' => $row['receipt_no'],
            'receipt_url' => $row['receipt_url'],
            'receipt_date' => $row['receipt_date'],
            
            // Refund info - Check escrow_status for accurate refund state
            'refund_id' => $row['refund_id'],
            'refund_status' => determineRefundStatus($row),
            'refund_amount' => $row['refund_amount'] ? floatval($row['refund_amount']) : null,
            'can_request_refund' => $canRefund,
            
            // Status badge
            'status_badge' => $statusBadge
        ];
    }
    
    echo json_encode([
        'success' => true,
        'payments' => $payments,
        'statistics' => [
            'total_paid' => $totalPaid,
            'total_pending' => $totalPending,
            'verified_count' => $verifiedCount,
            'pending_count' => $pendingCount,
            'total_transactions' => count($payments)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

function getPaymentStatusBadge($paymentStatus, $escrowStatus) {
    if ($paymentStatus === 'verified' || $paymentStatus === 'paid') {
        if ($escrowStatus === 'held') {
            return [
                'label' => 'PAYMENT VERIFIED',
                'sublabel' => 'Funds in Escrow',
                'color' => 'blue',
                'icon' => 'lock'
            ];
        } elseif ($escrowStatus === 'released_to_owner') {
            return [
                'label' => 'COMPLETED',
                'sublabel' => 'Payment Released',
                'color' => 'green',
                'icon' => 'check_circle'
            ];
        }
        return [
            'label' => 'VERIFIED',
            'sublabel' => 'Payment Confirmed',
            'color' => 'green',
            'icon' => 'check_circle'
        ];
    } elseif ($paymentStatus === 'pending') {
        return [
            'label' => 'PENDING',
            'sublabel' => 'Awaiting Verification',
            'color' => 'orange',
            'icon' => 'schedule'
        ];
    } elseif ($paymentStatus === 'refunded') {
        return [
            'label' => 'REFUNDED',
            'sublabel' => 'Money Returned',
            'color' => 'purple',
            'icon' => 'undo'
        ];
    } elseif ($paymentStatus === 'rejected' || $paymentStatus === 'failed') {
        return [
            'label' => 'FAILED',
            'sublabel' => 'Payment Rejected',
            'color' => 'red',
            'icon' => 'cancel'
        ];
    }
    
    return [
        'label' => strtoupper($paymentStatus),
        'sublabel' => '',
        'color' => 'grey',
        'icon' => 'info'
    ];
}

function determineRefundStatus($row) {
    // If there's no refund record, return null
    if (empty($row['refund_id'])) {
        return null;
    }
    
    // Get the refund status from refunds table (most reliable source)
    $refundStatus = $row['refund_status'];
    
    // If refund status is 'completed' OR 'approved', show as completed
    // This is the PRIMARY check - refunds table is the source of truth
    if (in_array($refundStatus, ['completed', 'approved'])) {
        return 'completed';
    }
    
    // FALLBACK: If escrow is refunded, refund is definitely completed
    // (in case escrow was updated but refund wasn't)
    if ($row['escrow_status'] === 'refunded') {
        return 'completed';
    }
    
    // Return the actual refund status from refunds table
    return $refundStatus;
}

function canRequestRefund($payment) {
    // Can only refund if:
    // 1. Payment is verified/paid
    // 2. Booking is cancelled/rejected
    // 3. No existing refund
    // 4. Escrow not already refunded
    
    $validStatuses = ['verified', 'paid'];
    $cancelledBookingStatuses = ['cancelled', 'rejected'];
    
    return in_array($payment['payment_status'], $validStatuses) &&
           in_array($payment['booking_status'], $cancelledBookingStatuses) &&
           empty($payment['refund_id']) &&
           $payment['escrow_status'] !== 'refunded';
}

$conn->close();