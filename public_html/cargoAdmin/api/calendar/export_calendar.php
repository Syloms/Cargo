<?php
/**
 * ============================================================================
 * CALENDAR EXPORT - PDF, Excel, CSV
 * Export calendar events in various formats
 * ============================================================================
 */

session_start();
require_once '../../include/db.php';

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    die('Unauthorized');
}

$format = $_GET['format'] ?? 'csv'; // pdf, excel, csv
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$event_type = $_GET['event_type'] ?? 'all';

// Calculate date range
$start_date = "$year-$month-01";
$end_date = date('Y-m-t', strtotime($start_date));

// Get events (reuse logic from get_calendar_events.php)
$events = [];

try {
    // Fetch all events based on filters
    $where_clause = "(DATE(b.pickup_date) BETWEEN ? AND ? OR DATE(b.return_date) BETWEEN ? AND ?)";
    
    if ($event_type === 'all' || $event_type === 'bookings') {
        $booking_query = "
            SELECT 
                'Booking Pickup' as event_type,
                b.pickup_date as event_date,
                b.pickup_time as event_time,
                u.fullname as customer,
                CONCAT(c.brand, ' ', c.model) as vehicle,
                b.status,
                b.total_amount as amount
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.id
            LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
            WHERE $where_clause
            
            UNION ALL
            
            SELECT 
                'Booking Return' as event_type,
                b.return_date as event_date,
                b.return_time as event_time,
                u.fullname as customer,
                CONCAT(c.brand, ' ', c.model) as vehicle,
                b.status,
                b.total_amount as amount
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.id
            LEFT JOIN cars c ON b.car_id = c.id AND b.vehicle_type = 'car'
            WHERE $where_clause
        ";
        
        $stmt = $conn->prepare($booking_query);
        $stmt->bind_param('ssssssss', $start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
    }
    
    if ($event_type === 'all' || $event_type === 'payments') {
        $payment_query = "
            SELECT 
                'Payment' as event_type,
                DATE(p.created_at) as event_date,
                TIME(p.created_at) as event_time,
                u.fullname as customer,
                p.payment_method as vehicle,
                p.payment_status as status,
                p.amount
            FROM payments p
            LEFT JOIN bookings b ON p.booking_id = b.id
            LEFT JOIN users u ON b.user_id = u.id
            WHERE DATE(p.created_at) BETWEEN ? AND ?
        ";
        
        $stmt = $conn->prepare($payment_query);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
    }
    
    if ($event_type === 'all' || $event_type === 'verifications') {
        $verification_query = "
            SELECT 
                'Verification' as event_type,
                DATE(uv.created_at) as event_date,
                TIME(uv.created_at) as event_time,
                u.fullname as customer,
                'User Verification' as vehicle,
                uv.status,
                NULL as amount
            FROM user_verifications uv
            LEFT JOIN users u ON uv.user_id = u.id
            WHERE DATE(uv.created_at) BETWEEN ? AND ?
        ";
        
        $stmt = $conn->prepare($verification_query);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
    }
    
    // Check if refunds table exists before querying
    $refunds_exist = mysqli_query($conn, "SHOW TABLES LIKE 'refunds'");
    if ($refunds_exist && mysqli_num_rows($refunds_exist) > 0) {
        // Refunds query here if needed
    }
    
    // Check if user_verifications table exists
    if (false) { // Disable for now
        $verification_query = "
            SELECT 
                'Verification' as event_type,
                DATE(uv.created_at) as event_date,
                TIME(uv.created_at) as event_time,
                u.fullname as customer,
                'User Verification' as vehicle,
                uv.status,
                NULL as amount
            FROM user_verifications uv
            LEFT JOIN users u ON uv.user_id = u.id
            WHERE DATE(uv.created_at) BETWEEN ? AND ?
        ";
        
        $stmt = $conn->prepare($verification_query);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
    }
    
    // Sort events by date
    usort($events, function($a, $b) {
        return strcmp($a['event_date'] . ' ' . $a['event_time'], $b['event_date'] . ' ' . $b['event_time']);
    });
    
    // Export based on format
    switch ($format) {
        case 'csv':
            exportCSV($events, $month, $year);
            break;
        case 'excel':
            exportExcel($events, $month, $year);
            break;
        case 'pdf':
            exportPDF($events, $month, $year);
            break;
        default:
            die('Invalid format');
    }
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

function exportCSV($events, $month, $year) {
    $monthName = date('F', mktime(0, 0, 0, $month, 1));
    $filename = "calendar_{$monthName}_{$year}_" . date('His') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header
    fputcsv($output, ['Date', 'Time', 'Event Type', 'Customer', 'Details', 'Status', 'Amount']);
    
    // Data
    foreach ($events as $event) {
        fputcsv($output, [
            date('Y-m-d', strtotime($event['event_date'])),
            $event['event_time'],
            $event['event_type'],
            $event['customer'],
            $event['vehicle'],
            $event['status'],
            $event['amount'] ? '₱' . number_format($event['amount'], 2) : ''
        ]);
    }
    
    fclose($output);
    exit;
}

function exportExcel($events, $month, $year) {
    $monthName = date('F', mktime(0, 0, 0, $month, 1));
    $filename = "calendar_{$monthName}_{$year}_" . date('His') . ".xls";
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    echo '<tr style="background: #667eea; color: white; font-weight: bold;">';
    echo '<th>Date</th><th>Time</th><th>Event Type</th><th>Customer</th><th>Details</th><th>Status</th><th>Amount</th>';
    echo '</tr>';
    
    foreach ($events as $event) {
        echo '<tr>';
        echo '<td>' . date('Y-m-d', strtotime($event['event_date'])) . '</td>';
        echo '<td>' . $event['event_time'] . '</td>';
        echo '<td>' . $event['event_type'] . '</td>';
        echo '<td>' . htmlspecialchars($event['customer']) . '</td>';
        echo '<td>' . htmlspecialchars($event['vehicle']) . '</td>';
        echo '<td>' . $event['status'] . '</td>';
        echo '<td>' . ($event['amount'] ? '₱' . number_format($event['amount'], 2) : '') . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

function exportPDF($events, $month, $year) {
    require_once '../../vendor/tcpdf/TCPDF-main/tcpdf.php';
    
    $monthName = date('F', mktime(0, 0, 0, $month, 1));
    
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('CarGo Admin');
    $pdf->SetAuthor('CarGo Admin');
    $pdf->SetTitle("Calendar - $monthName $year");
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Title
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->Cell(0, 10, "Event Calendar - $monthName $year", 0, 1, 'C');
    $pdf->Ln(5);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(102, 126, 234);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell(25, 7, 'Date', 1, 0, 'C', 1);
    $pdf->Cell(20, 7, 'Time', 1, 0, 'C', 1);
    $pdf->Cell(35, 7, 'Event Type', 1, 0, 'C', 1);
    $pdf->Cell(40, 7, 'Customer', 1, 0, 'C', 1);
    $pdf->Cell(30, 7, 'Status', 1, 0, 'C', 1);
    $pdf->Cell(30, 7, 'Amount', 1, 1, 'C', 1);
    
    // Table data
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    
    foreach ($events as $event) {
        $pdf->Cell(25, 6, date('Y-m-d', strtotime($event['event_date'])), 1, 0, 'C', $fill);
        $pdf->Cell(20, 6, $event['event_time'], 1, 0, 'C', $fill);
        $pdf->Cell(35, 6, $event['event_type'], 1, 0, 'L', $fill);
        $pdf->Cell(40, 6, substr($event['customer'], 0, 25), 1, 0, 'L', $fill);
        $pdf->Cell(30, 6, $event['status'], 1, 0, 'C', $fill);
        $pdf->Cell(30, 6, $event['amount'] ? '₱' . number_format($event['amount'], 2) : '', 1, 1, 'R', $fill);
        $fill = !$fill;
    }
    
    // Output PDF
    $pdf->Output("calendar_{$monthName}_{$year}.pdf", 'D');
    exit;
}
?>
