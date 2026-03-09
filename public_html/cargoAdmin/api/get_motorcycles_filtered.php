<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

include "../include/db.php";

if (!isset($conn) || $conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . (isset($conn) ? $conn->connect_error : "Connection not established")
    ]);
    exit;
}

// Get filter parameters from request
$location = isset($_GET['location']) ? $_GET['location'] : '';
$minPrice = isset($_GET['minPrice']) ? floatval($_GET['minPrice']) : 0;
$maxPrice = isset($_GET['maxPrice']) ? floatval($_GET['maxPrice']) : 999999;
$deliveryMethod = isset($_GET['deliveryMethod']) ? $_GET['deliveryMethod'] : '';
$transmission = isset($_GET['transmission']) ? $_GET['transmission'] : '';
$bodyStyle = isset($_GET['bodyStyle']) ? $_GET['bodyStyle'] : '';
$brand = isset($_GET['brand']) ? $_GET['brand'] : '';
$year = isset($_GET['year']) ? $_GET['year'] : '';
$engineSize = isset($_GET['engineSize']) ? $_GET['engineSize'] : '';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'created_at';
$sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'DESC';

// Build WHERE clause dynamically
$whereConditions = ["motorcycles.status = 'approved'"];
$params = [];
$types = "";

// Price range filter
if ($minPrice > 0 || $maxPrice < 999999) {
    $whereConditions[] = "motorcycles.price_per_day BETWEEN ? AND ?";
    $params[] = $minPrice;
    $params[] = $maxPrice;
    $types .= "dd";
}

// Location filter
if (!empty($location)) {
    // Note: motorcycles table has 'location' column, users table has 'address' column
    $whereConditions[] = "(users.address LIKE ? OR motorcycles.location LIKE ? OR users.fullname LIKE ?)";
    $locationPattern = "%$location%";
    $params[] = $locationPattern;
    $params[] = $locationPattern;
    $params[] = $locationPattern;
    $types .= "sss";
}

// Transmission filter
if (!empty($transmission)) {
    $whereConditions[] = "motorcycles.transmission_type = ?";
    $params[] = $transmission;
    $types .= "s";
}

// Body style filter
if (!empty($bodyStyle)) {
    $whereConditions[] = "motorcycles.body_style = ?";
    $params[] = $bodyStyle;
    $types .= "s";
}

// Brand filter
if (!empty($brand)) {
    $whereConditions[] = "motorcycles.brand = ?";
    $params[] = $brand;
    $types .= "s";
}

// Year filter
if (!empty($year)) {
    $whereConditions[] = "motorcycles.motorcycle_year = ?";
    $params[] = $year;
    $types .= "s";
}

// Engine size filter
if (!empty($engineSize)) {
    $whereConditions[] = "motorcycles.engine_displacement = ?";
    $params[] = $engineSize;
    $types .= "s";
}

// Delivery method filter
if (!empty($deliveryMethod)) {
    $whereConditions[] = "(motorcycles.delivery_types LIKE ? OR motorcycles.delivery_types IS NULL)";
    $deliveryPattern = "%$deliveryMethod%";
    $params[] = $deliveryPattern;
    $types .= "s";
}

// Combine WHERE conditions
$whereClause = implode(" AND ", $whereConditions);

// Validate sort column to prevent SQL injection
$allowedSortColumns = [
    'price_per_day' => 'motorcycles.price_per_day',
    'rating' => 'motorcycles.rating',
    'motorcycle_year' => 'motorcycles.motorcycle_year',
    'created_at' => 'motorcycles.created_at',
    'brand' => 'motorcycles.brand'
];

$sortColumn = isset($allowedSortColumns[$sortBy]) ? $allowedSortColumns[$sortBy] : 'motorcycles.created_at';
$sortDirection = ($sortOrder === 'ASC') ? 'ASC' : 'DESC';

// Build the query
$sql = "
    SELECT 
        motorcycles.id,
        motorcycles.owner_id,
        motorcycles.brand,
        motorcycles.model,
        motorcycles.motorcycle_year,
        motorcycles.price_per_day AS price,
        motorcycles.image,
        motorcycles.transmission_type,
        motorcycles.body_style,
        motorcycles.color,
        motorcycles.location,
        motorcycles.latitude,
        motorcycles.longitude,
        motorcycles.delivery_types,
        motorcycles.engine_displacement,
        motorcycles.has_unlimited_mileage,
        users.fullname AS owner_name,
        users.address AS owner_address,
        COALESCE(motorcycles.rating, 0) AS rating
    FROM motorcycles
    JOIN users ON users.id = motorcycles.owner_id
    WHERE $whereClause
    ORDER BY $sortColumn $sortDirection
";

// Prepare and execute statement
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to prepare statement: " . $conn->error,
        "sql" => $sql
    ]);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to execute statement: " . $stmt->error
    ]);
    exit;
}

$result = $stmt->get_result();

$motorcycles = [];

while ($row = $result->fetch_assoc()) {
    // Resolve location: prefer motorcycle's own location, fall back to owner's address
    $motoLoc  = trim($row['location']       ?? '');
    $ownerLoc = trim($row['owner_address']  ?? '');
    $row['location'] = !empty($motoLoc) ? $motoLoc : (!empty($ownerLoc) ? $ownerLoc : '');

    // Handle empty images
    if (empty($row['image'])) {
        $row['image'] = "https://via.placeholder.com/400x250?text=No+Image";
    }

    // Round rating
    $row['rating'] = round(floatval($row['rating']), 1);
    
    // Parse delivery types JSON
    if (!empty($row['delivery_types'])) {
        $row['delivery_types'] = json_decode($row['delivery_types'], true);
    } else {
        $row['delivery_types'] = [];
    }
    
    // Add vehicle type identifier
    $row['vehicle_type'] = 'motorcycle';
    $row['type'] = $row['body_style'] ?? 'Standard';
    $row['year'] = $row['motorcycle_year'] ?? '';
    $row['engine_size'] = $row['engine_displacement'] ?? '';
    
    $motorcycles[] = $row;
}

echo json_encode([
    "status" => "success",
    "count" => count($motorcycles),
    "motorcycles" => $motorcycles,
    "filters_applied" => [
        "location" => $location,
        "minPrice" => $minPrice,
        "maxPrice" => $maxPrice,
        "transmission" => $transmission,
        "bodyStyle" => $bodyStyle,
        "brand" => $brand,
        "year" => $year,
        "engineSize" => $engineSize,
        "deliveryMethod" => $deliveryMethod,
        "sortBy" => $sortBy,
        "sortOrder" => $sortOrder
    ]
]);

$stmt->close();
$conn->close();
?>
