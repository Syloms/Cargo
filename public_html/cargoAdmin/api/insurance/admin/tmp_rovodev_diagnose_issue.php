<?php
/**
 * Quick Diagnostic Script - Find why test stops after Test 1
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Quick Diagnostic</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} pre{background:#f5f5f5;padding:10px;}</style>";

require_once __DIR__ . '/../../../include/db.php';

if (!$conn) {
    die("<p class='error'>❌ Database connection failed!</p>");
}

echo "<p class='success'>✅ Database connected</p>";

// Check 1: Does insurance_policies have owner_id column?
echo "<h2>Check 1: Table Structure</h2>";
$result = $conn->query("DESCRIBE insurance_policies");
echo "<table border='1' cellpadding='5'><tr><th>Field</th><th>Type</th><th>Null</th></tr>";
$hasOwnerId = false;
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td></tr>";
    if ($row['Field'] === 'owner_id') {
        $hasOwnerId = true;
    }
}
echo "</table>";

if ($hasOwnerId) {
    echo "<p class='success'>✅ owner_id column EXISTS</p>";
} else {
    echo "<p class='error'>❌ owner_id column MISSING - This is the problem!</p>";
    echo "<h3>Solution: Add owner_id column</h3>";
    echo "<pre>ALTER TABLE insurance_policies ADD COLUMN owner_id INT NULL AFTER booking_id;</pre>";
    echo "<pre>UPDATE insurance_policies ip
JOIN bookings b ON ip.booking_id = b.id
SET ip.owner_id = b.owner_id;</pre>";
}

// Check 2: What data is in insurance_policies?
echo "<h2>Check 2: Insurance Policies Data</h2>";
$result = $conn->query("SELECT * FROM insurance_policies LIMIT 1");
if ($result && $result->num_rows > 0) {
    $policy = $result->fetch_assoc();
    echo "<table border='1' cellpadding='5'>";
    foreach ($policy as $key => $value) {
        echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
    }
    echo "</table>";
    
    // Check if owner_id is set
    if (isset($policy['owner_id']) && $policy['owner_id']) {
        echo "<p class='success'>✅ owner_id is set: {$policy['owner_id']}</p>";
    } else {
        echo "<p class='error'>❌ owner_id is NULL or not set</p>";
    }
} else {
    echo "<p class='error'>❌ No policies found</p>";
}

// Check 3: Can we find the owner from booking?
echo "<h2>Check 3: Link to Booking Owner</h2>";
try {
    $result = $conn->query("
        SELECT 
            ip.id as policy_id,
            ip.booking_id,
            ip.owner_id as policy_owner_id,
            b.owner_id as booking_owner_id,
            b.renter_id,
            u.fullname as owner_name
        FROM insurance_policies ip
        JOIN bookings b ON ip.booking_id = b.id
        LEFT JOIN users u ON b.owner_id = u.id
        LIMIT 1
    ");

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "<table border='1' cellpadding='5'>";
        foreach ($row as $key => $value) {
            echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";
        
        if ($row['policy_owner_id'] && $row['booking_owner_id'] && $row['policy_owner_id'] == $row['booking_owner_id']) {
            echo "<p class='success'>✅ owner_id matches booking! Owner: {$row['owner_name']}</p>";
        } elseif ($row['booking_owner_id']) {
            echo "<p class='error'>❌ Mismatch! Policy owner_id: {$row['policy_owner_id']}, Booking owner_id: {$row['booking_owner_id']}</p>";
            echo "<p>Need to run: <code>UPDATE insurance_policies ip JOIN bookings b ON ip.booking_id = b.id SET ip.owner_id = b.owner_id</code></p>";
        }
    } else {
        echo "<p class='error'>❌ Could not link policy to booking</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Check 3 Query Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check 4: Test the query from Test 2
echo "<h2>Check 4: Test Query from Test 2</h2>";
$testQuery = "
    SELECT 
        u.id as owner_id,
        u.fullname as owner_name,
        u.email as owner_email,
        COUNT(DISTINCT ip.id) as policy_count
    FROM users u
    LEFT JOIN bookings b ON u.id = b.owner_id
    LEFT JOIN insurance_policies ip ON b.id = ip.booking_id
    WHERE ip.id IS NOT NULL
    GROUP BY u.id
    LIMIT 5
";

echo "<p>Running query...</p>";
try {
    $result = $conn->query($testQuery);
    if ($result && $result->num_rows > 0) {
        echo "<p class='success'>✅ Query executed successfully! Found {$result->num_rows} owners</p>";
        echo "<table border='1' cellpadding='5'><tr><th>Owner ID</th><th>Name</th><th>Email</th><th>Policies</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['owner_id']}</td>";
            echo "<td>{$row['owner_name']}</td>";
            echo "<td>{$row['owner_email']}</td>";
            echo "<td>{$row['policy_count']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ Query returned no results</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Query failed: " . $e->getMessage() . "</p>";
}

echo "<h2>Summary</h2>";
echo "<p>If you see errors above, that's why the main test stops after Test 1.</p>";
echo "<p>Fix the issues and refresh the main test page.</p>";

// Check 5: Simple check - does booking exist?
echo "<h2>Check 5: Booking Verification</h2>";
try {
    $result = $conn->query("SELECT id, owner_id, renter_id, status FROM bookings WHERE id = 1");
    if ($result && $result->num_rows > 0) {
        $booking = $result->fetch_assoc();
        echo "<p class='success'>✅ Booking #1 exists</p>";
        echo "<table border='1' cellpadding='5'>";
        foreach ($booking as $key => $value) {
            echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ Booking #1 not found - this is why the join fails!</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error checking booking: " . htmlspecialchars($e->getMessage()) . "</p>";
}

if (isset($conn)) {
    $conn->close();
}
?>
