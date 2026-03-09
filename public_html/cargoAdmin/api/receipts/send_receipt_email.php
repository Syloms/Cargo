<?php
require_once __DIR__ . '/../../include/config.php';
require_once __DIR__ . '/../../include/db.php';
require_once __DIR__ . '/../../include/smtp_mailer.php';
require_once __DIR__ . '/generate_receipt.php';

function sendReceiptEmail($bookingId, $connection = null) {
    global $conn;
    $shouldClose = false;
    if (!$connection) {
        $connection = $conn; // Use centralized connection
        $shouldClose = false; // Don't close shared connection
    }
    
    try {
        // Get booking and renter email
        $stmt = $connection->prepare("
            SELECT 
                b.id,
                b.total_amount,
                u.fullname AS renter_name,
                u.email AS renter_email,
                c.brand, c.model
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN cars c ON b.car_id = c.id
            WHERE b.id = ?
        ");
        
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        
        if (!$booking) {
            return ['error' => 'Booking not found'];
        }
        
        // Generate receipt
        $receipt = generateReceipt($bookingId, $connection);
        
        if (isset($receipt['error'])) {
            return $receipt;
        }
        
        // Prepare email
        $to = $booking['renter_email'];
        $subject = "Payment Receipt - Booking #BK-" . str_pad($bookingId, 4, "0", STR_PAD_LEFT);
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1a1a1a; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .button { background: #1a1a1a; color: white; padding: 12px 30px; text-decoration: none; display: inline-block; margin: 20px 0; border-radius: 5px; }
                .footer { text-align: center; padding: 20px; color: #999; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>CarGo</h1>
                    <p>Your Trusted Car Rental Service</p>
                </div>
                <div class='content'>
                    <h2>Payment Receipt</h2>
                    <p>Dear {$booking['renter_name']},</p>
                    <p>Thank you for your payment! Your booking for the <strong>{$booking['brand']} {$booking['model']}</strong> has been confirmed.</p>
                    <p><strong>Receipt Number:</strong> {$receipt['receipt_no']}</p>
                    <p><strong>Total Amount:</strong> ₱" . number_format($booking['total_amount'], 2) . "</p>
                    <p>Your receipt is attached to this email. You can also download it from your booking details.</p>
                    <a href='{$receipt['receipt_url']}' class='button'>Download Receipt</a>
                    <p>If you have any questions, please don't hesitate to contact us.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated email. Please do not reply.</p>
                    <p>&copy; 2025 CarGo. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Send email using SMTP
        try {
            send_smtp_email($to, $subject, $message);
            
            return [
                'success' => true,
                'message' => 'Receipt email sent successfully via SMTP',
                'email' => $to,
                'receipt' => $receipt
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage(),
                'receipt' => $receipt
            ];
        }
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    } finally {
        if ($shouldClose && $connection) {
            $connection->close();
        }
    }
}

// API Endpoint
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    
    if (!isset($_POST['booking_id'])) {
        echo json_encode(['error' => 'Missing booking_id']);
        exit;
    }
    
    $bookingId = intval($_POST['booking_id']);
    $result = sendReceiptEmail($bookingId);
    
    echo json_encode($result);
}