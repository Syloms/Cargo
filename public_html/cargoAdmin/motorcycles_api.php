<?php
include "include/db.php";
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$action = $_POST['action'] ?? '';

// ========== INSERT MOTORCYCLE ==========
if ($action === "insert") {
    $owner_id = $_POST['owner_id'] ?? 0;
    $status = $_POST['status'] ?? 'pending';
    $brand = $_POST['brand'] ?? '';
    $model = $_POST['model'] ?? '';
    $body_style = $_POST['body_style'] ?? '';
    $plate_number = $_POST['plate_number'] ?? '';
    $color = $_POST['color'] ?? '';
    $description = $_POST['description'] ?? '';
    $advance_notice = $_POST['advance_notice'] ?? '';
    $min_trip_duration = $_POST['min_trip_duration'] ?? '';
    $max_trip_duration = $_POST['max_trip_duration'] ?? '';
    $delivery_types = $_POST['delivery_types'] ?? '[]';
    $features = $_POST['features'] ?? '[]';
    $rules = $_POST['rules'] ?? '[]';
    $has_unlimited_mileage = $_POST['has_unlimited_mileage'] ?? '1';
    $price_per_day = $_POST['price_per_day'] ?? 0;
    $location = $_POST['location'] ?? '';
    $latitude = $_POST['latitude'] ?? 0;
    $longitude = $_POST['longitude'] ?? 0;
    
    // Motorcycle-specific fields
    $motorcycle_year = $_POST['motorcycle_year'] ?? $_POST['year'] ?? '';
    $engine_displacement = $_POST['trim'] ?? ''; // Using trim field for engine size

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