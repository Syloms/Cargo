<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/include/db.php';

if (!$conn) {
    die("Connection failed: Database connection not available");
}

echo "<h2>Motorcycle Image Path Fixer</h2>";
echo "<style>
  body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: #f5f5f5; }
  .success { color: green; font-weight: bold; }
  .error { color: red; font-weight: bold; }
  .warning { color: orange; font-weight: bold; }
  .info { color: blue; }
  table { width: 100%; border-collapse: collapse; background: white; margin: 20px 0; }
  th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
  th { background: #667eea; color: white; }
  tr:nth-child(even) { background: #f9f9f9; }
  img { max-width: 100px; max-height: 80px; }
  .btn { padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer; }
  .btn:hover { background: #764ba2; }
</style>";

// Get all motorcycles
$query = "SELECT id, brand, model, image FROM motorcycles ORDER BY id DESC";
$result = $conn->query($query);

if (!$result) {
    die("<p class='error'>Query failed: " . $conn->error . "</p>");
}

echo "<h3>Found " . $result->num_rows . " motorcycles</h3>";

echo "<table>";
echo "<tr>
        <th>ID</th>
        <th>Motorcycle</th>
        <th>Image Path in DB</th>
        <th>File Exists?</th>
        <th>Preview</th>
        <th>Action</th>
      </tr>";

$missingCount = 0;
$foundCount = 0;

while ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $name = htmlspecialchars($row['brand'] . ' ' . $row['model']);
    $imagePath = $row['image'];
    
    echo "<tr>";
    echo "<td><strong>#{$id}</strong></td>";
    echo "<td>{$name}</td>";
    
    if (empty($imagePath)) {
        echo "<td class='warning'>Empty/NULL</td>";
        echo "<td class='error'>❌ No path</td>";
        echo "<td>-</td>";
        echo "<td><span class='warning'>No image set</span></td>";
        $missingCount++;
    } else {
        echo "<td><code>" . htmlspecialchars($imagePath) . "</code></td>";
        
        // Check if file exists
        $fullPath = __DIR__ . '/' . $imagePath;
        
        if (file_exists($fullPath)) {
            echo "<td class='success'>✅ YES</td>";
            echo "<td><img src='" . htmlspecialchars($imagePath) . "' alt='Motorcycle' style='max-width: 80px;'></td>";
            echo "<td class='success'>OK</td>";
            $foundCount++;
        } else {
            echo "<td class='error'>❌ NO</td>";
            echo "<td>-</td>";
            echo "<td class='error'>File not found at: <br><code>" . htmlspecialchars($fullPath) . "</code></td>";
            $missingCount++;
        }
    }
    
    echo "</tr>";
}

echo "</table>";

echo "<div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>Summary</h3>";
echo "<p>✅ <span class='success'>Images Found: {$foundCount}</span></p>";
echo "<p>❌ <span class='error'>Images Missing: {$missingCount}</span></p>";
echo "</div>";

// Check uploads directory
echo "<div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>Uploads Directory Check</h3>";
$uploadsDir = __DIR__ . "/uploads/";

if (is_dir($uploadsDir)) {
    echo "<p class='success'>✅ Uploads directory exists</p>";
    
    // Count motorcycle images
    $motorcycleImages = glob($uploadsDir . "motorcycle_*.jpg");
    $motorcycleImagesPng = glob($uploadsDir . "motorcycle_*.png");
    $allMotorcycleImages = array_merge($motorcycleImages, $motorcycleImagesPng);
    
    echo "<p class='info'>Found " . count($allMotorcycleImages) . " motorcycle image files in uploads/</p>";
    
    if (count($allMotorcycleImages) > 0) {
        echo "<p>Sample files (first 10):</p><ul>";
        for ($i = 0; $i < min(10, count($allMotorcycleImages)); $i++) {
            echo "<li><code>" . basename($allMotorcycleImages[$i]) . "</code></li>";
        }
        echo "</ul>";
    }
} else {
    echo "<p class='error'>❌ Uploads directory does NOT exist at: {$uploadsDir}</p>";
}
echo "</div>";

$conn->close();
?>

<div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;">
  <h3>💡 How to Fix Missing Images</h3>
  <ol>
    <li>Check if the image files actually exist in the <code>uploads/</code> directory</li>
    <li>Verify the paths in the database match the actual file locations</li>
    <li>If files exist but paths are wrong, update the database</li>
    <li>If files don't exist, ask owners to re-upload their motorcycle images</li>
  </ol>
</div>
