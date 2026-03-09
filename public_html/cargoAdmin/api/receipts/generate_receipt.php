<?php
require_once __DIR__ . '/../../include/config.php';
require_once __DIR__ . '/../../include/db.php';

/**
 * --------------------------------------------------
 * Create receipts table if not exists
 * --------------------------------------------------
 */
$createTable = "
CREATE TABLE IF NOT EXISTS receipts (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    booking_id INT(11) NOT NULL,
    receipt_no VARCHAR(50) NOT NULL UNIQUE,
    receipt_path VARCHAR(255) DEFAULT NULL,
    receipt_url VARCHAR(500) DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'generated',
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    emailed_at DATETIME DEFAULT NULL,
    email_count INT(11) DEFAULT 0,
    KEY idx_booking_id (booking_id),
    KEY idx_receipt_no (receipt_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";
$conn->query($createTable);

/**
 * --------------------------------------------------
 * Try to load TCPDF
 * --------------------------------------------------
 */
$tcpdfPath = __DIR__ . '/../vendor/tcpdf/tcpdf.php';
$hasTCPDF = file_exists($tcpdfPath);

if ($hasTCPDF) {
    require_once $tcpdfPath;
}

/**
 * --------------------------------------------------
 * Generate Receipt Function
 * --------------------------------------------------
 */
function generateReceipt($bookingId, $connection = null) {
    global $hasTCPDF, $conn;

    $shouldClose = false;
    if (!$connection) {
        $connection = $conn; // Use centralized connection
        $shouldClose = true;
    }
    try {
        // Fetch booking details
        $stmt = $connection->prepare("
            SELECT
                b.*,
                COALESCE(c.brand, m.brand) AS brand,
                COALESCE(c.model, m.model) AS model,
                COALESCE(c.car_year, m.motorcycle_year) AS vehicle_year,
                COALESCE(c.plate_number, m.plate_number) AS plate_number,
                u1.fullname AS renter_name,
                u1.email AS renter_email,
                u1.phone AS renter_contact,
                p.payment_method,
                p.payment_reference,
                p.payment_status
            FROM bookings b
            LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
            LEFT JOIN motorcycles m ON b.car_id = m.id AND b.vehicle_type = 'motorcycle'
            LEFT JOIN users u1 ON b.user_id = u1.id
            LEFT JOIN payments p ON p.id = (
                SELECT id FROM payments
                WHERE booking_id = b.id AND payment_status IN ('verified', 'paid', 'escrowed', 'released')
                ORDER BY id DESC LIMIT 1
            )
            WHERE b.id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();

        if (!$booking) {
            return ['error' => 'Booking not found'];
        }

        // Load template
        $template = file_get_contents(__DIR__ . '/templates/receipt_template.html');

        // Calculate duration
        $pickup = strtotime($booking['pickup_date']);
        $return = strtotime($booking['return_date']);
        $days = max(1, ceil(($return - $pickup) / 86400));

        // Receipt data
        $receiptNo = "RCP-" . str_pad($bookingId, 6, "0", STR_PAD_LEFT);
        $bookingIdFormatted = "#BK-" . str_pad($bookingId, 4, "0", STR_PAD_LEFT);
        $brand    = $booking['brand'] ?? '';
        $model    = $booking['model'] ?? '';
        $year     = $booking['vehicle_year'] ?? '';
        $plate    = $booking['plate_number'] ?? '';
        $carName  = trim("$brand $model $year");
        if ($plate) $carName .= " ($plate)";

        // Payment breakdown
        $pricePerDay     = floatval($booking['price_per_day'] ?? 0);
        $baseRental      = round($pricePerDay * $days, 2);
        $totalAmount     = floatval($booking['total_amount']);
        $serviceFee      = round($totalAmount - $baseRental, 2);
        $securityDeposit = floatval($booking['security_deposit_amount'] ?? 0);
        $grandTotal      = round($totalAmount + $securityDeposit, 2);

        // Build additional fee rows for template
        $additionalFees = '';
        if ($serviceFee > 0) {
            $additionalFees .= '<tr><td>Service Fee (5%)</td><td>&#8369;' . number_format($serviceFee, 2) . '</td></tr>';
        }
        if ($securityDeposit > 0) {
            $additionalFees .= '<tr><td>Security Deposit (Held)</td><td>&#8369;' . number_format($securityDeposit, 2) . '</td></tr>';
        }

        // Template replacements
        $replacements = [
            '{{RECEIPT_NO}}'        => $receiptNo,
            '{{BOOKING_ID}}'        => $bookingIdFormatted,
            '{{DATE_ISSUED}}'       => date('F d, Y h:i A'),
            '{{STATUS}}'            => strtoupper($booking['payment_status'] ?? 'PAID'),
            '{{RENTER_NAME}}'       => $booking['renter_name'] ?? 'N/A',
            '{{RENTER_EMAIL}}'      => $booking['renter_email'] ?? 'N/A',
            '{{RENTER_CONTACT}}'    => $booking['renter_contact'] ?? 'N/A',
            '{{CAR_NAME}}'          => $carName ?: 'N/A',
            '{{PICKUP_DATETIME}}'   => date('F d, Y', strtotime($booking['pickup_date'])) . ' at ' . $booking['pickup_time'],
            '{{RETURN_DATETIME}}'   => date('F d, Y', strtotime($booking['return_date'])) . ' at ' . $booking['return_time'],
            '{{DURATION}}'          => $days . ' day' . ($days > 1 ? 's' : ''),
            '{{DAILY_RATE}}'        => number_format($pricePerDay, 2),
            '{{BASE_AMOUNT}}'       => number_format($baseRental, 2),
            '{{ADDITIONAL_FEES}}'   => $additionalFees,
            '{{TOTAL_AMOUNT}}'      => number_format($grandTotal, 2),
            '{{PAYMENT_METHOD}}'    => strtoupper($booking['payment_method'] ?? 'N/A'),
            '{{PAYMENT_REFERENCE}}' => $booking['payment_reference'] ?? 'N/A',
        ];

        $html = str_replace(array_keys($replacements), array_values($replacements), $template);

        /**
         * --------------------------------------------------
         * Generate PDF
         * --------------------------------------------------
         */
        if ($hasTCPDF) {
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(15, 15, 15);
            $pdf->AddPage();
            $pdf->writeHTML($html, true, false, true, false, '');

            // Save file
            $receiptDir = __DIR__ . '/../../receipts';
            if (!is_dir($receiptDir)) {
                mkdir($receiptDir, 0755, true);
            }

            $filename = "receipt_{$bookingId}_" . time() . ".pdf";
            $receiptPath = "receipts/" . $filename;
            $filepath = $receiptDir . "/" . $filename;
            // Load config if not already loaded
            if (!defined('BASE_URL')) {
                require_once __DIR__ . '/../../include/config.php';
            }
            $receiptUrl = BASE_URL . "/" . $receiptPath;

            $pdf->Output($filepath, 'F');

            /**
             * --------------------------------------------------
             * Save receipt to database
             * --------------------------------------------------
             */
            $stmt = $connection->prepare("
                INSERT INTO receipts (
                    booking_id, receipt_no, receipt_path, receipt_url, status, generated_at
                )
                VALUES (?, ?, ?, ?, 'generated', NOW())
                ON DUPLICATE KEY UPDATE
                    receipt_path = VALUES(receipt_path),
                    receipt_url = VALUES(receipt_url),
                    status = 'generated'
            ");
            $stmt->bind_param("isss", $bookingId, $receiptNo, $receiptPath, $receiptUrl);
            $stmt->execute();

            return [
                'success' => true,
                'receipt_no' => $receiptNo,
                'receipt_path' => $receiptPath,
                'receipt_url' => $receiptUrl
            ];
        }

        // TCPDF missing
        return [
            'success' => true,
            'receipt_no' => $receiptNo,
            'html' => $html,
            'message' => 'TCPDF not installed. Returning HTML only.'
        ];

    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    } finally {
        if ($shouldClose && $connection) {
            $connection->close();
        }
    }
}

/**
 * --------------------------------------------------
 * API Endpoint
 * --------------------------------------------------
 */
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');

    if (!isset($_GET['booking_id'])) {
        echo json_encode(['error' => 'Missing booking_id']);
        exit;
    }

    echo json_encode(generateReceipt((int)$_GET['booking_id']));
}
