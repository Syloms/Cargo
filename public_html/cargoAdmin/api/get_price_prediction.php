<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/../include/db.php';

$car_id       = isset($_GET['car_id'])       ? intval($_GET['car_id'])     : 0;
$vehicle_type = isset($_GET['vehicle_type']) ? trim($_GET['vehicle_type']) : 'car';

if ($car_id <= 0) {
    echo json_encode(["success" => false, "message" => "car_id is required"]);
    exit;
}

// Each table has different year/extra column names
if ($vehicle_type === 'motorcycle') {
    $stmt = $conn->prepare("
        SELECT brand, model, motorcycle_year AS vehicle_year, engine_displacement, body_style, price_per_day
        FROM motorcycles
        WHERE id = ?
    ");
} else {
    $stmt = $conn->prepare("
        SELECT brand, model, car_year AS vehicle_year, trim, body_style, price_per_day
        FROM cars
        WHERE id = ?
    ");
}

$stmt->bind_param("i", $car_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Vehicle not found"]);
    exit;
}

$vehicle = $result->fetch_assoc();

// -------------------------------------------------------
// PRICE PREDICTION ALGORITHM (Philippine Peso ₱)
// -------------------------------------------------------

$currentYear  = (int)date('Y');
$vehicleYear  = (int)($vehicle['vehicle_year'] ?? $currentYear - 5);
$brand        = strtolower($vehicle['brand'] ?? '');
$bodyStyle    = strtolower($vehicle['body_style'] ?? '');
$extra        = strtolower($vehicle_type === 'motorcycle' ? ($vehicle['engine_displacement'] ?? '') : ($vehicle['trim'] ?? ''));

// 1. BASE PRICE by vehicle type
$basePrice = ($vehicle_type === 'motorcycle') ? 450.0 : 900.0;

$factors = [];

// 2. BRAND TIER
$luxuryBrands  = ['bmw', 'mercedes', 'mercedes-benz', 'audi', 'lexus', 'porsche',
                   'land rover', 'jaguar', 'bentley', 'rolls-royce'];
$premiumBrands = ['toyota', 'honda', 'mazda', 'subaru', 'volvo', 'isuzu',
                   'ford', 'chevrolet', 'jeep', 'infiniti', 'acura', 'genesis',
                   'peugeot', 'mini', 'alfa romeo', 'kawasaki', 'yamaha', 'triumph', 'ducati'];

if (in_array($brand, $luxuryBrands)) {
    $brandMult  = 2.5;
    $brandLabel = "Luxury brand";
} elseif (in_array($brand, $premiumBrands)) {
    $brandMult  = 1.4;
    $brandLabel = "Premium brand";
} else {
    $brandMult  = 1.0;
    $brandLabel = "Standard brand";
}
$factors[] = ["label" => $brandLabel, "multiplier" => $brandMult];

// 3. VEHICLE AGE
$age = $currentYear - $vehicleYear;
if ($age <= 2) {
    $yearMult  = 1.35;
    $yearLabel = "Brand new / nearly new ($vehicleYear)";
} elseif ($age <= 5) {
    $yearMult  = 1.15;
    $yearLabel = "Relatively new ($vehicleYear)";
} elseif ($age <= 8) {
    $yearMult  = 1.0;
    $yearLabel = "Mid-age ($vehicleYear)";
} elseif ($age <= 12) {
    $yearMult  = 0.85;
    $yearLabel = "Older vehicle ($vehicleYear)";
} else {
    $yearMult  = 0.70;
    $yearLabel = "Vintage ($vehicleYear)";
}
$factors[] = ["label" => $yearLabel, "multiplier" => $yearMult];

// 4. BODY STYLE (cars only)
$bodyMult = 1.0;
if ($vehicle_type !== 'motorcycle') {
    $suvKw   = ['suv', 'crossover', 'van', 'minivan', 'mpv'];
    $truckKw = ['pickup', 'truck', 'utility'];
    foreach ($suvKw as $kw) {
        if (str_contains($bodyStyle, $kw)) { $bodyMult = 1.35; break; }
    }
    if ($bodyMult === 1.0) {
        foreach ($truckKw as $kw) {
            if (str_contains($bodyStyle, $kw)) { $bodyMult = 1.25; break; }
        }
    }
    $bodyLabel = $bodyMult > 1.2 ? "SUV/Van/Pickup body" : ($bodyMult > 1.0 ? "Truck body" : "Sedan/Hatchback body");
    $factors[] = ["label" => $bodyLabel, "multiplier" => $bodyMult];
}

// 5. ENGINE DISPLACEMENT (motorcycles) — larger engine = higher price
$dispMult = 1.0;
if ($vehicle_type === 'motorcycle' && $extra !== '') {
    if (str_contains($extra, '651')) {
        $dispMult  = 1.50; $dispLabel = "Large engine (651cc+)";
    } elseif (str_contains($extra, '401') || str_contains($extra, '300')) {
        $dispMult  = 1.30; $dispLabel = "Mid-large engine (301–650cc)";
    } elseif (str_contains($extra, '201') || str_contains($extra, '150')) {
        $dispMult  = 1.10; $dispLabel = "Mid engine (151–300cc)";
    } else {
        $dispMult  = 1.0;  $dispLabel = "Small engine (≤150cc)";
    }
    $factors[] = ["label" => $dispLabel, "multiplier" => $dispMult];
}

// Compute final suggested price
$suggestedPrice = $basePrice * $brandMult * $yearMult * $bodyMult * $dispMult;

// Round to nearest 50
$suggestedPrice = round($suggestedPrice / 50) * 50;
$minPrice = max(300, round(($suggestedPrice * 0.80) / 50) * 50);
$maxPrice = round(($suggestedPrice * 1.25) / 50) * 50;

echo json_encode([
    "success"       => true,
    "current_price" => (float)$vehicle['price_per_day'],
    "suggested_min" => (float)$minPrice,
    "suggested"     => (float)$suggestedPrice,
    "suggested_max" => (float)$maxPrice,
    "factors"       => $factors,
    "vehicle"       => [
        "brand"      => $vehicle['brand'],
        "model"      => $vehicle['model'],
        "year"       => $vehicle['vehicle_year'],
        "body_style" => $vehicle['body_style'],
    ],
]);

$conn->close();
?>
