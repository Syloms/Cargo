<?php
/**
 * Vehicle listing validation helpers.
 *
 * Goals:
 * - Basic server-side validation/sanitization for car + motorcycle listing inserts
 * - Keep features/rules flexible: validate shape (JSON array) but don't enforce a fixed list
 */

define('VEHICLE_ALLOWED_ENGINE_DISPLACEMENTS', [
    '100-125cc',
    '126-150cc',
    '151-200cc',
    '201-300cc',
    '301-400cc',
    '401-650cc',
    '651cc+',
]);

/**
 * @return array{0:bool,1:mixed}
 */
function vv_parse_json_array($raw, $default = []) {
    if ($raw === null || $raw === '') {
        return [true, $default];
    }

    if (is_array($raw)) {
        return [true, $raw];
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return [false, $default];
    }

    return [true, $decoded];
}

function vv_trim_string($value, $maxLen = 255) {
    $s = trim((string)($value ?? ''));
    if ($maxLen > 0 && strlen($s) > $maxLen) {
        $s = substr($s, 0, $maxLen);
    }
    return $s;
}

function vv_to_int($value, $default = 0) {
    if ($value === null || $value === '') return $default;
    if (!is_numeric($value)) return $default;
    return (int)$value;
}

function vv_to_float($value, $default = 0.0) {
    if ($value === null || $value === '') return $default;
    if (!is_numeric($value)) return $default;
    return (float)$value;
}

function vv_error($message, $field = null, $httpCode = 400) {
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'field' => $field,
    ]);
    exit;
}

/**
 * Validates common fields for both cars and motorcycles.
 * Returns sanitized associative array.
 */
function vv_validate_common_insert_fields() {
    $owner_id = vv_to_int($_POST['owner_id'] ?? 0, 0);
    if ($owner_id <= 0) vv_error('Invalid owner_id', 'owner_id');

    $status = vv_trim_string($_POST['status'] ?? 'pending', 30);
    if ($status === '') $status = 'pending';

    $brand = vv_trim_string($_POST['brand'] ?? '', 100);
    $model = vv_trim_string($_POST['model'] ?? '', 100);
    $body_style = vv_trim_string($_POST['body_style'] ?? '', 80);
    $plate_number = vv_trim_string($_POST['plate_number'] ?? '', 30);
    $color = vv_trim_string($_POST['color'] ?? '', 50);

    if ($brand === '') vv_error('Brand is required', 'brand');
    if ($model === '') vv_error('Model is required', 'model');
    if ($body_style === '') vv_error('Body style is required', 'body_style');
    if ($plate_number === '') vv_error('Plate number is required', 'plate_number');
    if ($color === '') vv_error('Color is required', 'color');

    $description = vv_trim_string($_POST['description'] ?? '', 2000);

    $advance_notice = vv_trim_string($_POST['advance_notice'] ?? '', 50);
    $min_trip_duration = vv_trim_string($_POST['min_trip_duration'] ?? '', 50);
    $max_trip_duration = vv_trim_string($_POST['max_trip_duration'] ?? '', 50);

    // delivery_types/features/rules should be JSON arrays
    [$deliveryOk, $deliveryTypesArr] = vv_parse_json_array($_POST['delivery_types'] ?? '[]', []);
    if (!$deliveryOk) vv_error('delivery_types must be a JSON array', 'delivery_types');

    [$featuresOk, $featuresArr] = vv_parse_json_array($_POST['features'] ?? '[]', []);
    if (!$featuresOk) vv_error('features must be a JSON array', 'features');

    [$rulesOk, $rulesArr] = vv_parse_json_array($_POST['rules'] ?? '[]', []);
    if (!$rulesOk) vv_error('rules must be a JSON array', 'rules');

    // normalize to arrays of strings
    $deliveryTypesArr = array_values(array_filter(array_map(fn($x) => vv_trim_string($x, 120), $deliveryTypesArr), fn($x) => $x !== ''));
    $featuresArr = array_values(array_filter(array_map(fn($x) => vv_trim_string($x, 120), $featuresArr), fn($x) => $x !== ''));
    $rulesArr = array_values(array_filter(array_map(fn($x) => vv_trim_string($x, 200), $rulesArr), fn($x) => $x !== ''));

    $has_unlimited_mileage = ($_POST['has_unlimited_mileage'] ?? '1');
    $has_unlimited_mileage = ($has_unlimited_mileage === '1' || $has_unlimited_mileage === 1 || $has_unlimited_mileage === true) ? 1 : 0;

    $price_per_day = vv_to_float($_POST['price_per_day'] ?? 0, 0);
    if ($price_per_day < 50) vv_error('price_per_day must be at least 50', 'price_per_day');

    $location = vv_trim_string($_POST['location'] ?? '', 255);
    if ($location === '') vv_error('Location is required', 'location');

    $latitude = vv_to_float($_POST['latitude'] ?? 0, 0);
    $longitude = vv_to_float($_POST['longitude'] ?? 0, 0);

    // basic range checks
    if ($latitude < -90 || $latitude > 90) vv_error('Invalid latitude', 'latitude');
    if ($longitude < -180 || $longitude > 180) vv_error('Invalid longitude', 'longitude');

    return [
        'owner_id' => $owner_id,
        'status' => $status,
        'brand' => $brand,
        'model' => $model,
        'body_style' => $body_style,
        'plate_number' => $plate_number,
        'color' => $color,
        'description' => $description,
        'advance_notice' => $advance_notice,
        'min_trip_duration' => $min_trip_duration,
        'max_trip_duration' => $max_trip_duration,
        'delivery_types' => json_encode($deliveryTypesArr),
        'features' => json_encode($featuresArr),
        'rules' => json_encode($rulesArr),
        'has_unlimited_mileage' => $has_unlimited_mileage,
        'price_per_day' => $price_per_day,
        'location' => $location,
        'latitude' => $latitude,
        'longitude' => $longitude,
    ];
}

function vv_validate_car_fields() {
    $year = vv_trim_string($_POST['year'] ?? '', 10);
    if ($year === '') vv_error('year is required', 'year');
    if (!preg_match('/^\d{4}$/', $year)) vv_error('year must be a 4-digit year', 'year');
    $yearInt = (int)$year;
    $current = (int)date('Y') + 1;
    if ($yearInt < 1980 || $yearInt > $current) vv_error('year is out of range', 'year');

    $trim = vv_trim_string($_POST['trim'] ?? '', 50);
    if ($trim === '') $trim = 'N/A';

    return [
        'car_year' => $year,
        'trim' => $trim,
    ];
}

function vv_validate_motorcycle_fields() {
    $motorcycle_year = vv_trim_string($_POST['motorcycle_year'] ?? ($_POST['year'] ?? ''), 10);
    if ($motorcycle_year === '') vv_error('motorcycle_year is required', 'motorcycle_year');
    if (!preg_match('/^\d{4}$/', $motorcycle_year)) vv_error('motorcycle_year must be a 4-digit year', 'motorcycle_year');

    $yearInt = (int)$motorcycle_year;
    $current = (int)date('Y') + 1;
    if ($yearInt < 1980 || $yearInt > $current) vv_error('motorcycle_year is out of range', 'motorcycle_year');

    $engine_displacement = vv_trim_string($_POST['trim'] ?? '', 30);
    if ($engine_displacement === '') vv_error('engine displacement is required', 'trim');
    if (!in_array($engine_displacement, VEHICLE_ALLOWED_ENGINE_DISPLACEMENTS, true)) {
        vv_error('Invalid engine displacement', 'trim');
    }

    return [
        'motorcycle_year' => $motorcycle_year,
        'engine_displacement' => $engine_displacement,
    ];
}
