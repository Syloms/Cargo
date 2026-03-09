<?php
/**
 * Send Insurance Policy Email to Owner and Renter
 * Sends a professional email with policy details to both parties
 */

header('Content-Type: application/json');
require_once '../../include/db.php';
require_once '../../include/smtp_mailer.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$policyId = $input['policy_id'] ?? null;

if (!$policyId) {
    echo json_encode(['success' => false, 'message' => 'Policy ID is required']);
    exit;
}

// Auto-expire policies before sending email
$expireStmt = $conn->prepare("
    UPDATE insurance_policies 
    SET status = 'expired' 
    WHERE status = 'active' 
    AND policy_end < NOW()
");
$expireStmt->execute();
$expireStmt->close();

try {
    // Get basic policy details first
    $stmt = $conn->prepare("
        SELECT 
            ip.*,
            b.id as booking_id,
            b.pickup_date,
            b.return_date,
            
            -- Renter info
            u_renter.id as renter_id,
            u_renter.fullname as renter_name,
            u_renter.email as renter_email,
            u_renter.phone as renter_contact,
            
            -- Owner info
            u_owner.id as owner_id,
            u_owner.fullname as owner_name,
            u_owner.email as owner_email,
            u_owner.phone as owner_contact,
            
            -- Provider info
            ip_provider.provider_name,
            ip_provider.contact_email as provider_email,
            ip_provider.contact_phone as provider_phone
            
        FROM insurance_policies ip
        JOIN bookings b ON ip.booking_id = b.id
        JOIN users u_renter ON b.user_id = u_renter.id
        JOIN users u_owner ON ip.owner_id = u_owner.id
        LEFT JOIN insurance_providers ip_provider ON ip.provider_id = ip_provider.id
        WHERE ip.id = ?
    ");
    
    $stmt->bind_param('i', $policyId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Policy not found']);
        exit;
    }
    
    $policy = $result->fetch_assoc();
    
    // Get vehicle data separately based on vehicle_type
    // Handle empty vehicle_type by checking both tables
    $vehicle = null;
    
    if (!empty($policy['vehicle_type'])) {
        // If vehicle_type is set, query the appropriate table
        if ($policy['vehicle_type'] === 'car') {
            $stmt = $conn->prepare("SELECT brand, model, car_year, plate_number FROM cars WHERE id = ?");
            $stmt->bind_param('i', $policy['vehicle_id']);
            $stmt->execute();
            $vehicle = $stmt->get_result()->fetch_assoc();
        } else if ($policy['vehicle_type'] === 'motorcycle') {
            $stmt = $conn->prepare("SELECT brand, model, motorcycle_year as car_year, plate_number FROM motorcycles WHERE id = ?");
            $stmt->bind_param('i', $policy['vehicle_id']);
            $stmt->execute();
            $vehicle = $stmt->get_result()->fetch_assoc();
        }
    } else {
        // vehicle_type is empty - try both tables (fallback for bad data)
        // Try cars first
        $stmt = $conn->prepare("SELECT brand, model, car_year, plate_number, 'car' as detected_type FROM cars WHERE id = ?");
        $stmt->bind_param('i', $policy['vehicle_id']);
        $stmt->execute();
        $vehicle = $stmt->get_result()->fetch_assoc();
        
        // If not found in cars, try motorcycles
        if (!$vehicle) {
            $stmt = $conn->prepare("SELECT brand, model, motorcycle_year as car_year, plate_number, 'motorcycle' as detected_type FROM motorcycles WHERE id = ?");
            $stmt->bind_param('i', $policy['vehicle_id']);
            $stmt->execute();
            $vehicle = $stmt->get_result()->fetch_assoc();
        }
        
        // Update the policy vehicle_type for future use
        if ($vehicle && isset($vehicle['detected_type'])) {
            $policy['vehicle_type'] = $vehicle['detected_type'];
        }
    }
    
    // Vehicle not found - this is a data issue
    if (!$vehicle) {
        echo json_encode([
            'success' => false,
            'message' => 'Vehicle not found. Vehicle ID: ' . $policy['vehicle_id'] . ' does not exist in database.'
        ]);
        exit;
    }
    
    // Add vehicle data to policy array
    if ($vehicle) {
        $policy['vehicle_brand'] = $vehicle['brand'];
        $policy['vehicle_model'] = $vehicle['model'];
        $policy['vehicle_year'] = $vehicle['car_year'];
        $policy['vehicle_plate'] = $vehicle['plate_number'];
    }
    
    // All data retrieved successfully - continue with email preparation
    
    // Prepare email content with fallback for missing vehicle data
    $vehicleBrand = !empty($policy['vehicle_brand']) ? $policy['vehicle_brand'] : 'N/A';
    $vehicleModel = !empty($policy['vehicle_model']) ? $policy['vehicle_model'] : 'N/A';
    $vehicleYear = !empty($policy['vehicle_year']) ? $policy['vehicle_year'] : 'N/A';
    $vehiclePlate = !empty($policy['vehicle_plate']) ? $policy['vehicle_plate'] : 'N/A';
    
    $vehicleDetails = trim("{$vehicleBrand} {$vehicleModel} {$vehicleYear}");
    if ($vehicleDetails === 'N/A N/A N/A' || empty(trim($vehicleDetails))) {
        $vehicleDetails = ucfirst($policy['vehicle_type']) . " (ID: {$policy['vehicle_id']})";
    }
    $policyStart = date('F d, Y', strtotime($policy['policy_start']));
    $policyEnd = date('F d, Y', strtotime($policy['policy_end']));
    
    // Email HTML template
    $emailTemplate = function($recipientName, $recipientType) use ($policy, $vehicleDetails, $vehiclePlate, $policyStart, $policyEnd) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { background: #ffffff; padding: 30px; border: 1px solid #e0e0e0; }
                .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #667eea; }
                .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e0e0e0; }
                .info-row:last-child { border-bottom: none; }
                .label { font-weight: 600; color: #555; }
                .value { color: #222; }
                .highlight { background: #e8f5e9; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; color: #666; font-size: 14px; }
                .btn { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🛡️ Insurance Policy Certificate</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Policy Number: {$policy['policy_number']}</p>
                </div>
                
                <div class='content'>
                    <p>Dear <strong>{$recipientName}</strong>,</p>
                    
                    <p>This email confirms your insurance policy coverage for the vehicle rental. Below are the complete policy details:</p>
                    
                    <div class='info-box'>
                        <h3 style='margin-top: 0; color: #667eea;'>📋 Policy Information</h3>
                        <div class='info-row'>
                            <span class='label'>Policy Number:</span>
                            <span class='value'><strong>{$policy['policy_number']}</strong></span>
                        </div>
                        <div class='info-row'>
                            <span class='label'>Status:</span>
                            <span class='value'><span style='color: #28a745; font-weight: bold;'>" . strtoupper($policy['status']) . "</span></span>
                        </div>
                        <div class='info-row'>
                            <span class='label'>Coverage Period:</span>
                            <span class='value'>{$policyStart} to {$policyEnd}</span>
                        </div>
                        <div class='info-row'>
                            <span class='label'>Premium Amount:</span>
                            <span class='value' style='color: #28a745; font-weight: bold;'>₱" . number_format($policy['premium_amount'], 2) . "</span>
                        </div>
                        <div class='info-row'>
                            <span class='label'>Coverage Limit:</span>
                            <span class='value' style='color: #1976d2; font-weight: bold;'>₱" . number_format($policy['coverage_limit'], 2) . "</span>
                        </div>
                        <div class='info-row'>
                            <span class='label'>Deductible:</span>
                            <span class='value'>₱" . number_format($policy['deductible'], 2) . "</span>
                        </div>
                    </div>
                    
                    <div class='info-box'>
                        <h3 style='margin-top: 0; color: #667eea;'>🚗 Vehicle Information</h3>
                        <div class='info-row'>
                            <span class='label'>Vehicle:</span>
                            <span class='value'><strong>{$vehicleDetails}</strong></span>
                        </div>
                        <div class='info-row'>
                            <span class='label'>Plate Number:</span>
                            <span class='value' style='font-family: monospace; font-weight: bold;'>{$vehiclePlate}</span>
                        </div>
                    </div>
                    
                    " . ($recipientType === 'owner' ? "
                    <div class='info-box'>
                        <h3 style='margin-top: 0; color: #667eea;'>🧑 Insured Driver (Renter)</h3>
                        <div class='info-row'>
                            <span class='label'>Name:</span>
                            <span class='value'>{$policy['renter_name']}</span>
                        </div>
                        <div class='info-row'>
                            <span class='label'>Contact:</span>
                            <span class='value'>{$policy['renter_contact']}</span>
                        </div>
                    </div>
                    " : "
                    <div class='info-box'>
                        <h3 style='margin-top: 0; color: #667eea;'>👤 Vehicle Owner</h3>
                        <div class='info-row'>
                            <span class='label'>Name:</span>
                            <span class='value'>{$policy['owner_name']}</span>
                        </div>
                        <div class='info-row'>
                            <span class='label'>Contact:</span>
                            <span class='value'>{$policy['owner_contact']}</span>
                        </div>
                    </div>
                    ") . "
                    
                    <div class='info-box'>
                        <h3 style='margin-top: 0; color: #667eea;'>🏢 Insurance Provider</h3>
                        <div class='info-row'>
                            <span class='label'>Provider:</span>
                            <span class='value'>{$policy['provider_name']}</span>
                        </div>
                        <div class='info-row'>
                            <span class='label'>Email:</span>
                            <span class='value'>{$policy['provider_email']}</span>
                        </div>
                        <div class='info-row'>
                            <span class='label'>Phone:</span>
                            <span class='value'>{$policy['provider_phone']}</span>
                        </div>
                    </div>
                    
                    <div class='highlight'>
                        <strong>⚠️ Important Notes:</strong>
                        <ul style='margin: 10px 0 0 0; padding-left: 20px;'>
                            <li>Keep this policy information for your records</li>
                            <li>In case of an incident, contact the insurance provider immediately</li>
                            <li>Review the policy terms and conditions carefully</li>
                            <li>Coverage is valid only during the rental period specified</li>
                        </ul>
                    </div>
                    
                    <p style='margin-top: 30px;'>If you have any questions about this policy, please contact us or reach out to the insurance provider directly.</p>
                    
                    <p>Thank you for choosing our service!</p>
                </div>
                
                <div class='footer'>
                    <p><strong>CarGo Rental Management System</strong></p>
                    <p style='font-size: 12px; color: #999; margin-top: 10px;'>
                        This is an automated email. Please do not reply to this message.<br>
                        For support, contact your insurance provider or our customer service.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
    };
    
    $emailsSent = [];
    $errors = [];
    
    // Send to Renter
    if (!empty($policy['renter_email'])) {
        try {
            $subject = "Insurance Policy Certificate - {$policy['policy_number']}";
            $htmlBody = $emailTemplate($policy['renter_name'], 'renter');
            
            send_smtp_email($policy['renter_email'], $subject, $htmlBody);
            $emailsSent[] = "Renter ({$policy['renter_email']})";
        } catch (Exception $e) {
            $errors[] = "Failed to send to renter: " . $e->getMessage();
        }
    }
    
    // Send to Owner
    if (!empty($policy['owner_email']) && $policy['owner_email'] !== $policy['renter_email']) {
        try {
            $subject = "Insurance Policy Certificate - {$policy['policy_number']}";
            $htmlBody = $emailTemplate($policy['owner_name'], 'owner');
            
            send_smtp_email($policy['owner_email'], $subject, $htmlBody);
            $emailsSent[] = "Owner ({$policy['owner_email']})";
        } catch (Exception $e) {
            $errors[] = "Failed to send to owner: " . $e->getMessage();
        }
    }
    
    if (count($emailsSent) > 0) {
        $message = "Email sent successfully to: " . implode(', ', $emailsSent);
        if (count($errors) > 0) {
            $message .= ". Errors: " . implode(', ', $errors);
        }
        echo json_encode([
            'success' => true,
            'message' => $message,
            'sent_to' => $emailsSent,
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send emails. ' . implode(', ', $errors)
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
