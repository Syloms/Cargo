<?php
require_once __DIR__ . '/include/db.php';

echo "<h2>Fix Missing Motorcycle Image</h2>";
echo "<style>
  body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
  .success { color: green; font-weight: bold; }
  .error { color: red; font-weight: bold; }
  .btn { padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
  .btn:hover { background: #764ba2; }
  .btn-danger { background: #f5576c; }
  .btn-danger:hover { background: #e74c3c; }
</style>";

// Check if action is requested
if (isset($_GET['action']) && isset($_GET['id'])) {
    $motorcycleId = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action === 'clear') {
        // Clear the image path
        $stmt = $conn->prepare("UPDATE motorcycles SET image = NULL WHERE id = ?");
        $stmt->bind_param("i", $motorcycleId);
        
        if ($stmt->execute()) {
            echo "<p class='success'>✅ Successfully cleared image path for motorcycle #{$motorcycleId}</p>";
        } else {
            echo "<p class='error'>❌ Failed to update: " . $conn->error . "</p>";
        }
        $stmt->close();
    }
}

// Get the problematic motorcycle
$query = "SELECT id, brand, model, image FROM motorcycles WHERE id = 5";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $motorcycleName = htmlspecialchars($row['brand'] . ' ' . $row['model']);
    $currentPath = htmlspecialchars($row['image']);
    
    echo "<div style='background: white; padding: 20px; border-radius: 8px;'>";
    echo "<h3>Motorcycle #5: {$motorcycleName}</h3>";
    echo "<p><strong>Current Image Path:</strong> <code>{$currentPath}</code></p>";
    echo "<p class='error'>⚠️ This file does not exist on the server</p>";
    
    echo "<h4>Fix Options:</h4>";
    
    echo "<form method='GET' style='margin: 10px 0;'>";
    echo "<input type='hidden' name='id' value='5'>";
    echo "<input type='hidden' name='action' value='clear'>";
    echo "<button type='submit' class='btn btn-danger'>Clear Image Path (Set to NULL)</button>";
    echo "<p style='font-size: 12px; color: #666;'>This will remove the broken path. Owner can upload a new image later.</p>";
    echo "</form>";
    
    echo "<hr>";
    
    echo "<h4>Manual Fix:</h4>";
    echo "<p>If the owner has the image file, they can:</p>";
    echo "<ol>";
    echo "<li>Delete this motorcycle listing</li>";
    echo "<li>Create a new listing with the correct image</li>";
    echo "</ol>";
    
    echo "<p><strong>Or run this SQL manually:</strong></p>";
    echo "<code style='display: block; background: #f5f5f5; padding: 10px; border-radius: 4px;'>";
    echo "UPDATE motorcycles SET image = NULL WHERE id = 5;";
    echo "</code>";
    
    echo "</div>";
} else {
    echo "<p>Motorcycle #5 not found in database.</p>";
}

echo "<br><a href='tmp_rovodev_fix_motorcycle_images.php' class='btn'>← Back to Full Report</a>";
echo "<a href='get_motorcycle_admin.php' class='btn'>View Motorcycles Admin</a>";

$conn->close();
?>
