<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

include "../include/db.php";

// Get distinct values for filter options from approved motorcycles only
$response = [
    "status" => "success",
    "options" => []
];

// Get brands
$brandsQuery = $conn->query("SELECT DISTINCT brand FROM motorcycles WHERE status = 'approved' AND brand IS NOT NULL AND brand != '' ORDER BY brand");
$brands = [];
while ($row = $brandsQuery->fetch_assoc()) {
    $brands[] = $row['brand'];
}
$response['options']['brands'] = $brands;

// Get body styles (motorcycle types)
$bodyStylesQuery = $conn->query("SELECT DISTINCT body_style FROM motorcycles WHERE status = 'approved' AND body_style IS NOT NULL AND body_style != '' ORDER BY body_style");
$bodyStyles = [];
while ($row = $bodyStylesQuery->fetch_assoc()) {
    $bodyStyles[] = $row['body_style'];
}
$response['options']['bodyStyles'] = $bodyStyles;

// Get transmissions
$transmissionsQuery = $conn->query("SELECT DISTINCT transmission_type FROM motorcycles WHERE status = 'approved' AND transmission_type IS NOT NULL AND transmission_type != '' ORDER BY transmission_type");
$transmissions = [];
while ($row = $transmissionsQuery->fetch_assoc()) {
    $transmissions[] = $row['transmission_type'];
}
$response['options']['transmissions'] = $transmissions;

// Get years
$yearsQuery = $conn->query("SELECT DISTINCT motorcycle_year FROM motorcycles WHERE status = 'approved' AND motorcycle_year IS NOT NULL AND motorcycle_year != '' ORDER BY motorcycle_year DESC");
$years = [];
while ($row = $yearsQuery->fetch_assoc()) {
    $years[] = $row['motorcycle_year'];
}
$response['options']['years'] = $years;

// Get engine sizes
$engineSizesQuery = $conn->query("SELECT DISTINCT engine_displacement FROM motorcycles WHERE status = 'approved' AND engine_displacement IS NOT NULL AND engine_displacement != '' ORDER BY engine_displacement");
$engineSizes = [];
while ($row = $engineSizesQuery->fetch_assoc()) {
    $engineSizes[] = $row['engine_displacement'];
}
$response['options']['engineSizes'] = $engineSizes;

// Delivery methods (static as they're stored in JSON)
$response['options']['deliveryMethods'] = [
    'Guest Pickup & Guest Return',
    'Guest Pickup & Host Collection',
    'Host Delivery & Guest Return',
    'Host Delivery & Host Collection'
];

// Price range
$priceQuery = $conn->query("SELECT MIN(price_per_day) as min_price, MAX(price_per_day) as max_price FROM motorcycles WHERE status = 'approved'");
$priceRange = $priceQuery->fetch_assoc();
$response['options']['priceRange'] = [
    'min' => floatval($priceRange['min_price'] ?? 0),
    'max' => floatval($priceRange['max_price'] ?? 2000)
];

echo json_encode($response);

$conn->close();
?>
