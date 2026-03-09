<?php
// Enable error logging
// Load configuration
require_once __DIR__ . '/include/config.php';
error_log("=== Cars API Started ===");

// DON'T use output buffering yet - let's see the actual error
include "include/db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$action = $_POST['action'] ?? $_GET['action'] ?? 'fetch';
error_log("Action: $action");


// ========== FETCH ALL VEHICLES (CARS + MOTORCYCLES) ==========
if ($action === "fetch") {

    $owner_id = $_POST['owner_id'] ?? $_GET['owner_id'] ?? 0;
    error_log("Fetching for owner_id: $owner_id");
    $vehicles = [];

    /* -------- FETCH CARS -------- */
    error_log("Preparing car query...");
    $carStmt = $conn->prepare("
        SELECT
            id,
            owner_id,
            brand,
            model,
            color,
            body_style,
            plate_number,
            price_per_day,
            image,
            location,
            status,
            created_at,
            car_year,
            fuel_type,
            transmission,
            seat,
            'car' AS vehicle_type
        FROM cars
        WHERE owner_id = ?
    ");
    
    if (!$carStmt) {
        error_log("Car prepare failed: " . $conn->error);
        die(json_encode(["error" => "Car query prepare failed: " . $conn->error]));
    }
    
    $carStmt->bind_param("i", $owner_id);
    $carStmt->execute();
    $carResult = $carStmt->get_result();
    
    error_log("Car query executed, rows: " . $carResult->num_rows);

    while ($row = $carResult->fetch_assoc()) {
        $vehicles[] = $row;
    }
    $carStmt->close();
    error_log("Cars fetched: " . count($vehicles));

    /* -------- FETCH MOTORCYCLES -------- */
    error_log("Preparing motorcycle query...");
    $motorStmt = $conn->prepare("
        SELECT
            id,
            owner_id,
            brand,
            model,
            color,
            body_style,
            plate_number,
            price_per_day,
            image,
            location,
            status,
            created_at,
            motorcycle_year,
            engine_displacement,
            transmission_type,
            'motorcycle' AS vehicle_type
        FROM motorcycles
        WHERE owner_id = ?
    ");
    
    if (!$motorStmt) {
        error_log("Motorcycle prepare failed: " . $conn->error);
        die(json_encode(["error" => "Motorcycle query prepare failed: " . $conn->error]));
    }
    
    $motorStmt->bind_param("i", $owner_id);
    $motorStmt->execute();
    $motorResult = $motorStmt->get_result();
    
    error_log("Motorcycle query executed, rows: " . $motorResult->num_rows);

    while ($row = $motorResult->fetch_assoc()) {
        $vehicles[] = $row;
    }
    $motorStmt->close();
    error_log("Motorcycles fetched: " . count($vehicles) . " total vehicles");

    /* -------- SORT BY DATE -------- */
    error_log("Sorting vehicles...");
    usort($vehicles, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    // Log for debugging
    error_log("Cars API: owner_id=$owner_id, found " . count($vehicles) . " vehicles");
    error_log("About to output JSON...");
    
    // Clean up data to ensure valid UTF-8
    foreach ($vehicles as &$vehicle) {
        foreach ($vehicle as $key => &$value) {
            if (is_string($value)) {
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
        }
    }
    unset($vehicle, $value); // Break references
    
    $json = json_encode($vehicles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if ($json === false) {
        error_log("JSON ENCODE FAILED: " . json_last_error_msg());
        echo json_encode(["error" => "JSON encoding failed: " . json_last_error_msg()]);
    } else {
        error_log("JSON length: " . strlen($json));
        error_log("JSON first 200 chars: " . substr($json, 0, 200));
        echo $json;
    }
    
    $conn->close();
    exit;
}



// ========== INSERT CAR ==========
if ($action === "insert") {
    require_once __DIR__ . '/include/vehicle_validation.php';

    $common = vv_validate_common_insert_fields();
    $car = vv_validate_car_fields();

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

    // Car-specific fields
    $car_year = $car['car_year'];
    $trim = $car['trim'];

    // -------- UPLOAD FILES --------
    $uploadDir = "uploads/";
    
    // Main Photo
    $mainPhoto = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $mainPhotoName = uniqid("car_main_") . "." . $ext;
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
        INSERT INTO cars (
            owner_id, status, brand, model, body_style, plate_number, color, description,
            advance_notice, min_trip_duration, max_trip_duration, delivery_types,
            features, rules, has_unlimited_mileage, price_per_day,
            location, latitude, longitude, image, official_receipt, certificate_of_registration,
            extra_images, car_year, trim, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param(
        "isssssssssssssidsddssssss",
        $owner_id, $status, $brand, $model, $body_style, $plate_number, $color, $description,
        $advance_notice, $min_trip_duration, $max_trip_duration, $delivery_types,
        $features, $rules, $has_unlimited_mileage, $price_per_day,
        $location, $latitude, $longitude, $mainPhoto, $orPhoto, $crPhoto,
        $extraImagesJson, $car_year, $trim
    );

    if ($stmt->execute()) {
        $insertedId = $stmt->insert_id;
        
        // Send notification to owner
        $notifStmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, read_status, created_at) 
            VALUES (?, ?, ?, 'unread', NOW())
        ");
        
        $notifTitle = "Car Submitted ✅";
        $notifMessage = "Your car '{$brand} {$model}' has been submitted for approval.";
        
        $notifStmt->bind_param("iss", $owner_id, $notifTitle, $notifMessage);
        $notifStmt->execute();
        $notifStmt->close();
        
        echo json_encode(["success" => true, "id" => $insertedId, "message" => "Car submitted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

// ========== DELETE VEHICLE (CAR OR MOTORCYCLE) ==========
if ($action === "delete") {
    error_log("=== DELETE ACTION TRIGGERED ===");
    
    $id = $_POST['id'] ?? $_GET['id'] ?? 0;
    $vehicle_type = $_POST['vehicle_type'] ?? $_GET['vehicle_type'] ?? null;
    $owner_id = $_POST['owner_id'] ?? $_GET['owner_id'] ?? null;
    
    error_log("Delete request - ID: $id, Type: $vehicle_type, Owner: $owner_id");
    
    if (!$id) {
        error_log("Delete failed: Missing vehicle ID");
        echo json_encode(["success" => false, "message" => "Vehicle ID is required"]);
        exit;
    }
    
    // Auto-detect vehicle type if not provided
    if (!$vehicle_type) {
        // Check in cars table first
        $checkCar = $conn->prepare("SELECT id FROM cars WHERE id = ?");
        $checkCar->bind_param("i", $id);
        $checkCar->execute();
        if ($checkCar->get_result()->num_rows > 0) {
            $vehicle_type = 'car';
        } else {
            // Check in motorcycles table
            $checkMotor = $conn->prepare("SELECT id FROM motorcycles WHERE id = ?");
            $checkMotor->bind_param("i", $id);
            $checkMotor->execute();
            if ($checkMotor->get_result()->num_rows > 0) {
                $vehicle_type = 'motorcycle';
            }
        }
        error_log("Auto-detected vehicle type: $vehicle_type");
    }
    
    if (!$vehicle_type) {
        error_log("Delete failed: Vehicle not found in either table");
        echo json_encode(["success" => false, "message" => "Vehicle not found"]);
        exit;
    }
    
    // Determine table name
    $table = ($vehicle_type === 'motorcycle') ? 'motorcycles' : 'cars';
    
    // Verify ownership if owner_id is provided
    if ($owner_id) {
        $verifyStmt = $conn->prepare("SELECT id FROM $table WHERE id = ? AND owner_id = ?");
        $verifyStmt->bind_param("ii", $id, $owner_id);
        $verifyStmt->execute();
        if ($verifyStmt->get_result()->num_rows === 0) {
            error_log("Delete failed: Ownership verification failed");
            echo json_encode(["success" => false, "message" => "Unauthorized: You do not own this vehicle"]);
            exit;
        }
    }
    
    // Check if vehicle is currently rented
    $bookingCheck = $conn->prepare("
        SELECT id FROM bookings 
        WHERE car_id = ? 
        AND vehicle_type = ? 
        AND status IN ('pending', 'approved')
        AND return_date >= CURDATE()
    ");
    $bookingCheck->bind_param("is", $id, $vehicle_type);
    $bookingCheck->execute();
    
    if ($bookingCheck->get_result()->num_rows > 0) {
        error_log("Delete failed: Vehicle has active bookings");
        echo json_encode([
            "success" => false, 
            "message" => "Cannot delete vehicle with active or pending bookings"
        ]);
        exit;
    }
    
    // Get vehicle details before deletion (for cleanup and logging)
    $getVehicle = $conn->prepare("SELECT image, official_receipt, certificate_of_registration, extra_images FROM $table WHERE id = ?");
    $getVehicle->bind_param("i", $id);
    $getVehicle->execute();
    $vehicleData = $getVehicle->get_result()->fetch_assoc();
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Delete related records first (to maintain referential integrity)
        
        // 1. Delete from vehicle_availability
        $deleteAvailability = $conn->prepare("DELETE FROM vehicle_availability WHERE vehicle_id = ? AND vehicle_type = ?");
        $deleteAvailability->bind_param("is", $id, $vehicle_type);
        $deleteAvailability->execute();
        error_log("Deleted availability records");
        
        // 2. Delete from favorites
        $deleteFavorites = $conn->prepare("DELETE FROM favorites WHERE vehicle_id = ? AND vehicle_type = ?");
        $deleteFavorites->bind_param("is", $id, $vehicle_type);
        $deleteFavorites->execute();
        error_log("Deleted favorites records");
        
        // 3. Delete the vehicle
        $deleteVehicle = $conn->prepare("DELETE FROM $table WHERE id = ?");
        $deleteVehicle->bind_param("i", $id);
        
        if ($deleteVehicle->execute()) {
            error_log("Vehicle deleted from database");
            
            // Commit transaction
            $conn->commit();
            
            // Optional: Delete uploaded files (uncomment if you want to delete files)
            // if ($vehicleData) {
            //     $filesToDelete = [
            //         $vehicleData['image'],
            //         $vehicleData['official_receipt'],
            //         $vehicleData['certificate_of_registration']
            //     ];
            //     
            //     // Add extra images
            //     if ($vehicleData['extra_images']) {
            //         $extraImages = json_decode($vehicleData['extra_images'], true);
            //         if (is_array($extraImages)) {
            //             $filesToDelete = array_merge($filesToDelete, $extraImages);
            //         }
            //     }
            //     
            //     foreach ($filesToDelete as $file) {
            //         if ($file && file_exists($file)) {
            //             unlink($file);
            //             error_log("Deleted file: $file");
            //         }
            //     }
            // }
            
            echo json_encode([
                "success" => true, 
                "message" => "Vehicle deleted successfully"
            ]);
        } else {
            throw new Exception("Failed to delete vehicle: " . $conn->error);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Delete failed: " . $e->getMessage());
        echo json_encode([
            "success" => false, 
            "message" => "Failed to delete vehicle: " . $e->getMessage()
        ]);
    }
    
    $conn->close();
    exit;
}

error_log("Invalid action reached: $action");
echo json_encode(["success" => false, "message" => "Invalid action: $action"]);
?>