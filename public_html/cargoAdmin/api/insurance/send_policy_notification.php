<?php
/**
 * Send Insurance Policy Notifications
 * Sends email notifications for insurance policy events
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../include/db.php';
require_once __DIR__ . '/../../include/smtp_mailer.php';

function sendEmail($to, $subject, $body) {
    try {
        send_smtp_email($to, $subject, $body);
        return true;
    } catch (Exception $e) {
        error_log("Failed to send insurance notification email: " . $e->getMessage());
        return false;
    }
}

function sendPolicyCreatedEmail($policyData) {
    $to = $policyData['owner_email'];
    $subject = "Insurance Policy Created - {$policyData['policy_number']}";
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #ff9800; color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; }
            .policy-info { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #ff9800; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .button { display: inline-block; padding: 12px 24px; background: #ff9800; color: white; text-decoration: none; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🛡️ Insurance Policy Created</h1>
            </div>
            <div class='content'>
                <p>Dear {$policyData['owner_name']},</p>
                
                <p>An insurance policy has been created for your vehicle booking.</p>
                
                <div class='policy-info'>
                    <h3>Policy Details</h3>
                    <p><strong>Policy Number:</strong> {$policyData['policy_number']}</p>
                    <p><strong>Vehicle:</strong> {$policyData['vehicle_name']}</p>
                    <p><strong>Coverage Type:</strong> " . strtoupper($policyData['coverage_type']) . "</p>
                    <p><strong>Premium Amount:</strong> ₱" . number_format($policyData['premium_amount'], 2) . "</p>
                    <p><strong>Coverage Limit:</strong> ₱" . number_format($policyData['coverage_limit'], 2) . "</p>
                    <p><strong>Valid From:</strong> " . date('M d, Y', strtotime($policyData['policy_start'])) . "</p>
                    <p><strong>Valid Until:</strong> " . date('M d, Y', strtotime($policyData['policy_end'])) . "</p>
                </div>
                
                <p>Your vehicle is now protected with comprehensive insurance coverage for the rental period.</p>
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='https://cargoph.online' class='button'>View Policy Details</a>
                </p>
                
                <p>If you have any questions, please contact our support team.</p>
            </div>
            <div class='footer'>
                <p>© 2026 Cargo Platform. All rights reserved.</p>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($to, $subject, $body);
}

function sendPolicyExpiringEmail($policyData) {
    $to = $policyData['owner_email'];
    $subject = "Insurance Policy Expiring Soon - {$policyData['policy_number']}";
    
    $daysRemaining = $policyData['days_remaining'];
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #ff9800; color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
            .policy-info { background: white; padding: 15px; margin: 15px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>⚠️ Insurance Policy Expiring Soon</h1>
            </div>
            <div class='content'>
                <p>Dear {$policyData['owner_name']},</p>
                
                <div class='warning'>
                    <strong>⏰ Action Required:</strong> Your insurance policy will expire in {$daysRemaining} day(s).
                </div>
                
                <div class='policy-info'>
                    <h3>Policy Information</h3>
                    <p><strong>Policy Number:</strong> {$policyData['policy_number']}</p>
                    <p><strong>Vehicle:</strong> {$policyData['vehicle_name']}</p>
                    <p><strong>Expiration Date:</strong> " . date('M d, Y', strtotime($policyData['policy_end'])) . "</p>
                    <p><strong>Days Remaining:</strong> {$daysRemaining} days</p>
                </div>
                
                <p>Please ensure the rental is completed before the policy expires, or contact us if you need to extend the coverage.</p>
            </div>
            <div class='footer'>
                <p>© 2026 Cargo Platform. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($to, $subject, $body);
}

function sendClaimFiledEmail($policyData, $claimData) {
    $to = $policyData['owner_email'];
    $subject = "Insurance Claim Filed - {$policyData['policy_number']}";
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #f44336; color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; }
            .claim-info { background: #ffebee; padding: 15px; margin: 15px 0; border-left: 4px solid #f44336; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🚨 Insurance Claim Filed</h1>
            </div>
            <div class='content'>
                <p>Dear {$policyData['owner_name']},</p>
                
                <p>An insurance claim has been filed for your policy.</p>
                
                <div class='claim-info'>
                    <h3>Claim Details</h3>
                    <p><strong>Claim Number:</strong> {$claimData['claim_number']}</p>
                    <p><strong>Policy Number:</strong> {$policyData['policy_number']}</p>
                    <p><strong>Vehicle:</strong> {$policyData['vehicle_name']}</p>
                    <p><strong>Claim Type:</strong> {$claimData['claim_type']}</p>
                    <p><strong>Amount Claimed:</strong> ₱" . number_format($claimData['claimed_amount'], 2) . "</p>
                    <p><strong>Filed Date:</strong> " . date('M d, Y') . "</p>
                </div>
                
                <p>Our claims team will review the submission and contact you within 24-48 hours.</p>
                
                <p>Reference Number: {$claimData['claim_number']}</p>
            </div>
            <div class='footer'>
                <p>© 2026 Cargo Platform. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($to, $subject, $body);
}

// API endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $type = $input['type'] ?? '';
    $policyData = $input['policy_data'] ?? [];
    $claimData = $input['claim_data'] ?? null;
    
    $result = false;
    
    switch ($type) {
        case 'policy_created':
            $result = sendPolicyCreatedEmail($policyData);
            break;
        case 'policy_expiring':
            $result = sendPolicyExpiringEmail($policyData);
            break;
        case 'claim_filed':
            $result = sendClaimFiledEmail($policyData, $claimData);
            break;
    }
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Email sent successfully' : 'Failed to send email'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
