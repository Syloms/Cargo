<?php
/**
 * Auto-expire insurance policies that have passed their end date
 * This should be called on every page load or via cron job
 */

require_once '../../include/db.php';

// Update all active policies that have expired
$stmt = $conn->prepare("
    UPDATE insurance_policies 
    SET status = 'expired' 
    WHERE status = 'active' 
    AND policy_end < NOW()
");

$stmt->execute();
$updated = $stmt->affected_rows;

// Return JSON response
echo json_encode([
    'success' => true,
    'updated' => $updated,
    'message' => "$updated policies were automatically expired"
]);
