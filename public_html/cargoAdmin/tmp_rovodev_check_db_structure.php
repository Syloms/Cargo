<?php
/**
 * Check database structure and sample data
 */

require_once __DIR__ . '/include/db.php';

echo "<h2>Database Structure Check</h2>";

// Check insurance_claims table structure
echo "<h3>insurance_claims Table Structure:</h3>";
$structure = $conn->query("DESCRIBE insurance_claims");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($col = $structure->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";

// Get sample claims data
echo "<h3>Sample Claims Data:</h3>";
$claims = $conn->query("
    SELECT 
        id, 
        claim_number, 
        status,
        evidence_photos,
        LENGTH(evidence_photos) as photo_length,
        approved_amount,
        user_id
    FROM insurance_claims 
    ORDER BY created_at DESC 
    LIMIT 5
");

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Claim #</th><th>Status</th><th>Evidence Photos (first 100 chars)</th><th>Length</th><th>Approved Amt</th><th>User ID</th></tr>";
while ($claim = $claims->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$claim['id']}</td>";
    echo "<td>{$claim['claim_number']}</td>";
    echo "<td>{$claim['status']}</td>";
    echo "<td>" . htmlspecialchars(substr($claim['evidence_photos'] ?? 'NULL', 0, 100)) . "</td>";
    echo "<td>{$claim['photo_length']}</td>";
    echo "<td>₱" . number_format($claim['approved_amount'], 2) . "</td>";
    echo "<td>{$claim['user_id']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";

// Check users table for phone field
echo "<h3>Users Table - Phone Field Check:</h3>";
$users = $conn->query("
    SELECT id, fullname, email, phone 
    FROM users 
    WHERE id IN (SELECT DISTINCT user_id FROM insurance_claims)
    LIMIT 5
");

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th></tr>";
while ($user = $users->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>{$user['fullname']}</td>";
    echo "<td>{$user['email']}</td>";
    echo "<td>" . ($user['phone'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>

<style>
table { border-collapse: collapse; margin: 20px 0; }
th { background: #333; color: white; padding: 8px; }
td { padding: 5px; }
tr:nth-child(even) { background: #f2f2f2; }
</style>
