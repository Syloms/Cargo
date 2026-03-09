<?php
/**
 * Check Overdue
 * Checks if a booking is overdue and calculates late fees
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../include/db.php';

try {
    $bookingId = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : (isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0);
    
    if ($bookingId <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid booking ID'
        ]);
        exit;
    }

    // Get booking details
    $query = "
        SELECT 
            b.*,
            COALESCE(c.price_per_day, m.price_per_day) as daily_rate,
            COALESCE(c.brand, m.brand) as vehicle_brand,
            COALESCE(c.model, m.model) as vehicle_model
        FROM bookings b
        LEFT JOIN cars c ON b.vehicle_type = 'car' AND b.car_id = c.id
        LEFT JOIN motorcycles m ON b.vehicle_type = 'motorcycle' AND b.car_id = m.id
        WHERE b.id = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        echo json_encode([
            'success' => false,
            'error' => 'Booking not found'
        ]);
        exit;
    }

    // Check if overdue (must include return_time, not just midnight of return_date)
    $returnDateTime = new DateTime($booking['return_date'] . ' ' . ($booking['return_time'] ?? '23:59:59'));
    $now = new DateTime();
    $isOverdue = $now > $returnDateTime
        && $booking['status'] !== 'completed'
        && $booking['trip_started'] == 1;

    $overdueData = [
        'is_overdue' => $isOverdue,
        'booking_id' => $bookingId,
        'return_date' => $booking['return_date'],
        'current_status' => $booking['status']
    ];

    if ($isOverdue) {
        // Calculate overdue duration
        $interval = $now->diff($returnDateTime);
        $overdueDays = $interval->days;
        $hoursOverdue = ($overdueDays * 24) + $interval->h;

        // Tier system: Grace 2h → Tier1 ₱300/hr (hrs 3-6) → Tier2 ₱500/hr (hrs 7-24) → Tier3 ₱2000/day (beyond 24h)
        $graceHours = 2;
        $tier1Rate = 300;
        $tier2Rate = 500;
        $tier3Rate = 2000;

        $totalLateFee = 0;
        if ($hoursOverdue > $graceHours) {
            $billableHours = $hoursOverdue - $graceHours;
            if ($billableHours <= 4) {
                // Tier 1 only (hours 3-6)
                $totalLateFee = $billableHours * $tier1Rate;
            } elseif ($billableHours <= 22) {
                // Tier 1 + Tier 2 (hours 3-24)
                $totalLateFee = (4 * $tier1Rate) + (($billableHours - 4) * $tier2Rate);
            } else {
                // Tier 3: first 24h at tier1+tier2, then per-day beyond
                $additionalHours = $hoursOverdue - 24;
                $additionalFullDays = floor($additionalHours / 24);
                $partialHours = $additionalHours % 24;

                $totalLateFee = (4 * $tier1Rate) + (18 * $tier2Rate) + ($additionalFullDays * $tier3Rate);

                if ($partialHours > 0) {
                    if ($partialHours <= 4) {
                        $totalLateFee += $partialHours * $tier1Rate;
                    } else {
                        $totalLateFee += (4 * $tier1Rate) + (($partialHours - 4) * $tier2Rate);
                    }
                }
            }
        }

        $overdueData['overdue_days'] = $overdueDays;
        $overdueData['hours_overdue'] = $hoursOverdue;
        $overdueData['total_late_fee'] = $totalLateFee;
        
        // Check if late fee already recorded
        $lateFeeQuery = "SELECT * FROM overdue_management WHERE booking_id = ?";
        $stmt = $conn->prepare($lateFeeQuery);
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $existingLateFee = $stmt->get_result()->fetch_assoc();

        if ($existingLateFee) {
            $overdueData['late_fee_id'] = $existingLateFee['id'];
            $overdueData['late_fee_status'] = $existingLateFee['status'];
            $overdueData['recorded_late_fee'] = floatval($existingLateFee['late_fee_amount']);
        } else {
            $overdueData['late_fee_id'] = null;
            $overdueData['late_fee_status'] = 'not_recorded';
            $overdueData['recorded_late_fee'] = 0;
        }
    } else {
        $overdueData['overdue_days'] = 0;
        $overdueData['total_late_fee'] = 0;
    }

    echo json_encode([
        'success' => true,
        'overdue_data' => $overdueData
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
