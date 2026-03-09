<?php
include "include/db.php";
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$action = $_POST['action'] ?? '';

// ========== INSERT MOTORCYCLE ==========
if ($action === "insert") {
    require_once __DIR__ . '/include/vehicle_validation.php';

    $common = vv_validate_common_insert_fields();
    $moto = vv_validate_motorcycle_fields();

    $owner_id = $common['owner_id'];
    $status = $common['status'];
    $brand = $common['brand'];
    $model = $common['model'];
    $body_style = $common['body_style'];
    $plate_number = $common['plate_number'];
    $color = $common['color'];
    $description = $common['description'];
    $advance_notice = $common['advance_notice'];
    $min_trip_duration = $common['min_trip_duration'];
    $max_trip_duration = $common['max_trip_duration'];
    $delivery_types = $common['delivery_types'];
    $features = $common['features'];
    $rules = $common['rules'];
    $has_unlimited_mileage = (string)$common['has_unlimited_mileage'];
    $price_per_day = $common['price_per_day'];
    $location = $common['location'];
    $latitude = $common['latitude'];
    $longitude = $common['longitude'];

    // Motorcycle-specific fields
    $motorcycle_year = $moto['motorcycle_year'];
    $engine_displacement = $moto['engine_displacement'];

    // -------- UPLOAD FILES --------
    $uploadDir = "uploads/";
    
    // Main Photo
    $mainPhoto = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $mainPhotoName = uniqid("motorcycle_main_") . "." . $ext;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $mainPhotoName)) {
            $mainPhoto = $uploadDir . $mainPhotoName;
        }
    }

    // Official Receipt
    $orPhoto = null;
    if (isset($_FILES['official_receipt']) && $_FILES['official_receipt']['error'] == 0) {
        $ext = pathinfo($_FILES['official_receipt']['name'], PATHINFO_EXTENSION);
        $orName = uniqid("or_") . "." . $ext;
        if (move_uploaded_file($_FILES['official_receipt']['tmp_name'], $uploadDir . $orName)) {
            $orPhoto = $uploadDir . $orName;
        }
    }

    // Certificate of Registration
    $crPhoto = null;
    if (isset($_FILES['certificate_of_registration']) && $_FILES['certificate_of_registration']['error'] == 0) {
        $ext = pathinfo($_FILES['certificate_of_registration']['name'], PATHINFO_EXTENSION);
        $crName = uniqid("cr_") . "." . $ext;
        if (move_uploaded_file($_FILES['certificate_of_registration']['tmp_name'], $uploadDir . $crName)) {
            $crPhoto = $uploadDir . $crName;
        }
    }

    // Extra Photos
    $extraImages = [];
    if (isset($_FILES['extra_photos'])) {
        foreach ($_FILES['extra_photos']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['extra_photos']['error'][$key] == 0) {
                $ext = pathinfo($_FILES['extra_photos']['name'][$key], PATHINFO_EXTENSION);
                $extraName = uniqid("extra_") . "." . $ext;
                if (move_uploaded_file($tmpName, $uploadDir . $extraName)) {
                    $extraImages[] = $uploadDir . $extraName;
                }
            }
        }
    }

    $extraImagesJson = json_encode($extraImages);

    // -------- INSERT INTO DATABASE --------
    $stmt = $conn->prepare("
        INSERT INTO motorcycles (
            owner_id, status, brand, model, body_style, plate_number, color, description,
            advance_notice, min_trip_duration, max_trip_duration, delivery_types,
            features, rules, has_unlimited_mileage, price_per_day,
            location, latitude, longitude, image, official_receipt, certificate_of_registration,
            extra_images, motorcycle_year, engine_displacement, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param(
        "isssssssssssssidsddssssss",
        $owner_id, $status, $brand, $model, $body_style, $plate_number, $color, $description,
        $advance_notice, $min_trip_duration, $max_trip_duration, $delivery_types,
        $features, $rules, $has_unlimited_mileage, $price_per_day,
        $location, $latitude, $longitude, $mainPhoto, $orPhoto, $crPhoto,
        $extraImagesJson, $motorcycle_year, $engine_displacement
    );

    if ($stmt->execute()) {
        $insertedId = $stmt->insert_id;
        
        // Send notification to owner
        $notifStmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, read_status, created_at) 
            VALUES (?, ?, ?, 'unread', NOW())
        ");
        
        $notifTitle = "Motorcycle Submitted ✅";
        $notifMessage = "Your motorcycle '{$brand} {$model}' has been submitted for approval.";
        
        $notifStmt->bind_param("iss", $owner_id, $notifTitle, $notifMessage);
        $notifStmt->execute();
        $notifStmt->close();
        
        echo json_encode(["success" => true, "id" => $insertedId, "message" => "Motorcycle submitted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

// ========== UPDATE MOTORCYCLE STATUS ==========
if ($action === "update_status") {
    $id = $_POST['id'] ?? 0;
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    $stmt = $conn->prepare("UPDATE motorcycles SET status = ?, remarks = ? WHERE id = ?");
    $stmt->bind_param("ssi", $status, $remarks, $id);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => $stmt->error]);
    }
    
    $stmt->close();
    $conn->close();
    exit;
}

// ========== GET MOTORCYCLE BY ID ==========
if ($action === "get_by_id") {
    $id = $_GET['id'] ?? 0;
    
    $stmt = $conn->prepare("SELECT * FROM motorcycles WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(["success" => true, "motorcycle" => $row]);
    } else {
        echo json_encode(["success" => false, "message" => "Motorcycle not found"]);
    }
    
    $stmt->close();
    $conn->close();
    exit;
}

echo json_encode(["success" => false, "message" => "Invalid action"]);
?>