<?php
/**
 * Migration Script: Add transfer_proof column to payouts table
 * This adds the missing column needed for payout proof uploads
 */

session_start();
include "include/db.php";

// Only allow admin access
if (!isset($_SESSION['admin_id'])) {
    die("Access denied. Admin login required.");
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Migration - Add Transfer Proof</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; border: 1px solid #c3e6cb; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; border: 1px solid #f5c6cb; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; border: 1px solid #bee5eb; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; border: 1px solid #ffeaa7; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; border-left: 4px solid #007bff; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px; border: none; cursor: pointer; }
        .btn:hover { background: #45a049; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .step { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #007bff; }
        .step-title { font-weight: bold; color: #007bff; margin-bottom: 10px; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔧 Database Migration: Add Transfer Proof Column</h1>";

// Step 1: Check current status
echo "<div class='step'>
    <div class='step-title'>Step 1: Checking Current Database Status</div>";

$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM payouts LIKE 'transfer_proof'");
if (mysqli_num_rows($checkColumn) > 0) {
    echo "<div class='warning'>⚠️ Column 'transfer_proof' already exists in payouts table. Migration may have been run already.</div>";
    echo "<p>Current column details:</p>";
    $colInfo = mysqli_fetch_assoc($checkColumn);
    echo "<pre>" . print_r($colInfo, true) . "</pre>";
    $migrationNeeded = false;
} else {
    echo "<div class='info'>ℹ️ Column 'transfer_proof' does not exist. Migration is needed.</div>";
    $migrationNeeded = true;
}
echo "</div>";

// Step 2: Run migration if needed
if ($migrationNeeded && isset($_POST['run_migration'])) {
    echo "<div class='step'>
        <div class='step-title'>Step 2: Running Migration</div>";
    
    $migrationSQL = "ALTER TABLE payouts ADD COLUMN transfer_proof VARCHAR(255) NULL COMMENT 'Screenshot/proof of transfer' AFTER completion_reference";
    
    echo "<p>Executing SQL:</p>";
    echo "<pre>$migrationSQL</pre>";
    
    if (mysqli_query($conn, $migrationSQL)) {
        echo "<div class='success'>✅ SUCCESS! Column 'transfer_proof' has been added to the payouts table.</div>";
        
        // Verify the change
        $verify = mysqli_query($conn, "SHOW COLUMNS FROM payouts LIKE 'transfer_proof'");
        if (mysqli_num_rows($verify) > 0) {
            echo "<div class='success'>✅ VERIFIED! Column exists in database.</div>";
            $colInfo = mysqli_fetch_assoc($verify);
            echo "<pre>" . print_r($colInfo, true) . "</pre>";
            
            echo "<div class='info'>
                <strong>Next Steps:</strong>
                <ol>
                    <li>✅ Database migration complete</li>
                    <li>✅ Admin can now upload transfer proof when completing payouts</li>
                    <li>✅ Owners can view transfer proof in their payout history</li>
                    <li>🔄 Refresh the payouts page: <a href='payouts.php?status=completed'>View Completed Payouts</a></li>
                </ol>
            </div>";
        }
    } else {
        echo "<div class='error'>❌ ERROR: Failed to add column.<br>Error: " . mysqli_error($conn) . "</div>";
    }
    echo "</div>";
    
} elseif ($migrationNeeded) {
    // Show migration form
    echo "<div class='step'>
        <div class='step-title'>Step 2: Ready to Run Migration</div>
        <div class='warning'>
            <strong>⚠️ Important:</strong> This will modify the database structure.
            <br>Make sure you have a database backup before proceeding.
        </div>
        
        <p><strong>Migration will execute:</strong></p>
        <pre>ALTER TABLE payouts 
ADD COLUMN transfer_proof VARCHAR(255) NULL 
COMMENT 'Screenshot/proof of transfer' 
AFTER completion_reference;</pre>
        
        <p><strong>What this does:</strong></p>
        <ul>
            <li>Adds a new column 'transfer_proof' to store the file path of uploaded proof images</li>
            <li>Allows NULL values (existing records won't be affected)</li>
            <li>Positioned after 'completion_reference' column for logical grouping</li>
        </ul>
        
        <form method='POST'>
            <button type='submit' name='run_migration' class='btn'>▶️ Run Migration Now</button>
            <a href='payouts.php' class='btn btn-secondary'>Cancel</a>
        </form>
    </div>";
} else {
    // Migration not needed
    echo "<div class='step'>
        <div class='step-title'>Migration Status</div>
        <div class='success'>✅ No migration needed. The database is already up to date!</div>
        <p><a href='payouts.php' class='btn'>← Back to Payouts</a></p>
    </div>";
}

echo "</div>
</body>
</html>";

mysqli_close($conn);
?>
