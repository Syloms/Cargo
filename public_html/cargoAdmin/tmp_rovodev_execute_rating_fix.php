<?php
// Execute rating fix
require_once 'include/db.php';

echo "<h2>Rating Fix Execution</h2>";
echo "<pre>";

// Step 1: Fix cars with default 5.0 rating but no reviews
echo "Step 1: Resetting cars with 5.0 rating but no reviews...\n";
$sql1 = "UPDATE cars 
SET rating = 0.0 
WHERE rating = 5.0 
AND id NOT IN (
    SELECT DISTINCT car_id 
    FROM reviews 
    WHERE car_id IS NOT NULL
)";
$result1 = $conn->query($sql1);
echo "Cars updated: " . $conn->affected_rows . "\n\n";

// Step 2: Fix motorcycles with default 5.0 rating but no reviews
echo "Step 2: Resetting motorcycles with 5.0 rating but no reviews...\n";
$sql2 = "UPDATE motorcycles 
SET rating = 0.0 
WHERE rating = 5.0 
AND id NOT IN (
    SELECT DISTINCT car_id 
    FROM reviews 
    WHERE car_id IS NOT NULL
)";
$result2 = $conn->query($sql2);
echo "Motorcycles updated: " . $conn->affected_rows . "\n\n";

// Step 3: Recalculate ratings for cars that have reviews
echo "Step 3: Recalculating ratings for cars with reviews...\n";
$sql3 = "UPDATE cars c
SET rating = (
    SELECT COALESCE(AVG(r.rating), 0.0)
    FROM reviews r
    WHERE r.car_id = c.id
)
WHERE c.id IN (
    SELECT DISTINCT car_id 
    FROM reviews 
    WHERE car_id IS NOT NULL
)";
$result3 = $conn->query($sql3);
echo "Cars recalculated: " . $conn->affected_rows . "\n\n";

// Step 4: Recalculate ratings for motorcycles that have reviews
echo "Step 4: Recalculating ratings for motorcycles with reviews...\n";
$sql4 = "UPDATE motorcycles m
SET rating = (
    SELECT COALESCE(AVG(r.rating), 0.0)
    FROM reviews r
    WHERE r.car_id = m.id
)
WHERE m.id IN (
    SELECT DISTINCT car_id 
    FROM reviews 
    WHERE car_id IS NOT NULL
)";
$result4 = $conn->query($sql4);
echo "Motorcycles recalculated: " . $conn->affected_rows . "\n\n";

// Show results
echo "=== RESULTS ===\n\n";

$stats = $conn->query("
    SELECT 'Cars with 0.0 rating' as info, COUNT(*) as count FROM cars WHERE rating = 0.0
    UNION ALL
    SELECT 'Cars with ratings' as info, COUNT(*) as count FROM cars WHERE rating > 0.0
    UNION ALL
    SELECT 'Motorcycles with 0.0 rating' as info, COUNT(*) as count FROM motorcycles WHERE rating = 0.0
    UNION ALL
    SELECT 'Motorcycles with ratings' as info, COUNT(*) as count FROM motorcycles WHERE rating > 0.0
");

while ($row = $stats->fetch_assoc()) {
    echo $row['info'] . ": " . $row['count'] . "\n";
}

// Show sample cars
echo "\n=== SAMPLE CARS (First 10) ===\n";
$sample = $conn->query("SELECT id, brand, model, rating FROM cars LIMIT 10");
while ($row = $sample->fetch_assoc()) {
    echo "Car #{$row['id']} - {$row['brand']} {$row['model']}: Rating = {$row['rating']}\n";
}

echo "\n✅ Rating fix completed!\n";
echo "</pre>";

$conn->close();
?>