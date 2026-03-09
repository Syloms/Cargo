<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

include "../include/db.php";

// Get distinct values for filter options from approved cars only
$response = [
    "status" => "success",
    "options" => []
];

// Get brands
$brandsQuery = $conn->query("SELECT DISTINCT brand FROM cars WHERE status = 'approved' AND brand IS NOT NULL AND brand != '' ORDER BY brand");
$brands = [];
while ($row = $brandsQuery->fetch_assoc()) {
    $brands[] = $row['brand'];
}
$response['options']['brands'] = $brands;

// Get body styles
$bodyStylesQuery = $conn->query("SELECT DISTINCT body_style FROM cars WHERE status = 'approved' AND body_style IS NOT NULL AND body_style != '' ORDER BY body_style");
$bodyStyles = [];
while ($row = $bodyStylesQuery->fetch_assoc()) {
    $bodyStyles[] = $row['body_style'];
}
$response['options']['bodyStyles'] = $bodyStyles;

// Get transmissions
$transmissionsQuery = $conn->query("SELECT DISTINCT transmission FROM cars WHERE status = 'approved' AND transmission IS NOT NULL AND transmission != '' ORDER BY transmission");
$transmissions = [];
while ($row = $transmissionsQuery->fetch_assoc()) {
    $transmissions[] = $row['transmission'];
}
$response['options']['transmissions'] = $transmissions;

// Get fuel types
$fuelTypesQuery = $conn->query("SELECT DISTINCT fuel_type FROM cars WHERE status = 'approved' AND fuel_type IS NOT NULL AND fuel_type != '' ORDER BY fuel_type");
$fuelTypes = [];
while ($row = $fuelTypesQuery->fetch_assoc()) {
    $fuelTypes[] = $row['fuel_type'];
}
$response['options']['fuelTypes'] = $fuelTypes;

// Get years
$yearsQuery = $conn->query("SELECT DISTINCT car_year FROM cars WHERE status = 'approved' AND car_year IS NOT NULL AND car_year != '' ORDER BY car_year DESC");
$years = [];
while ($row = $yearsQuery->fetch_assoc()) {
    $years[] = $row['car_year'];
}
$response['options']['years'] = $years;

// Get seat counts
$seatsQuery = $conn->query("SELECT DISTINCT seat FROM cars WHERE status = 'approved' AND seat IS NOT NULL ORDER BY seat");
$seats = [];
while ($row = $seatsQuery->fetch_assoc()) {
    $seats[] = intval($row['seat']);
}
$response['options']['seats'] = $seats;

// Delivery methods (static as they're stored in JSON)
$response['options']['deliveryMethods'] = [
    'Guest Pickup & Guest Return',
    'Guest Pickup & Host Collection',
    'Host Delivery & Guest Return',
    'Host Delivery & Host Collection'
];

// Price range
$priceQuery = $conn->query("SELECT MIN(price_per_day) as min_price, MAX(price_per_day) as max_price FROM cars WHERE status = 'approved'");
$priceRange = $priceQuery->fetch_assoc();
$response['options']['priceRange'] = [
    'min' => floatval($priceRange['min_price'] ?? 0),
    'max' => floatval($priceRange['max_price'] ?? 2000)
];

echo json_encode($response);

$conn->close();
?>
