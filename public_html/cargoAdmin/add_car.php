<?php
include "include/db.php";
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, DELETE");
header("Content-Type: application/json");

// Function to upload image
function uploadImage($file) {
    $targetDir = "../uploads/";
    $filename = time() . "_" . basename($file["name"]);
    $targetFilePath = $targetDir . $filename;

    if(move_uploaded_file($file["tmp_name"], $targetFilePath)) {
        return "uploads/" . $filename;
    }
    return null;
}

// Handle GET (fetch cars)
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $owner_id = isset($_GET["owner_id"]) ? intval($_GET["owner_id"]) : 0;
    $result = $conn->query("SELECT * FROM cars WHERE owner_id = $owner_id ORDER BY created_at DESC");
    $cars = [];
    while($row = $result->fetch_assoc()) {
        $cars[] = $row;
    }
    echo json_encode($cars);
    exit;
}

// Handle POST (add/edit car)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = isset($_POST["id"]) ? intval($_POST["id"]) : 0;
    $owner_id = intval($_POST["owner_id"]);
    $car_name = $_POST["car_name"] ?? "";
    $brand = $_POST["brand"] ?? "";
    $model = $_POST["model"] ?? "";
    $plate_number = $_POST["plate_number"] ?? "";
    $price_per_day = $_POST["price_per_day"] ?? 0;
    $color = $_POST["color"] ?? "";
    $car_year = $_POST["car_year"] ?? 0;
    $body_style = $_POST["body_style"] ?? "";
    $location = $_POST["location"] ?? "";
    $issues = $_POST["issues"] ?? "None";
    $status = $_POST["status"] ?? "Available";

    $image_path = null;
    if(isset($_FILES["image"])) {
        $image_path = uploadImage($_FILES["image"]);
    }

    if($id > 0) {
        // Update existing car
        $sql = "UPDATE cars SET car_name=?, brand=?, model=?, plate_number=?, price_per_day=?, color=?, car_year=?, body_style=?, location=?, issues=?, status=?";
        $params = [$car_name, $brand, $model, $plate_number, $price_per_day, $color, $car_year, $body_style, $location, $issues, $status];

        if($image_path) {
            $sql .= ", image=?";
            $params[] = $image_path;
        }

        $sql .= " WHERE id=?";
        $params[] = $id;

        $stmt = $conn->prepare($sql);
        $types = str_repeat("s", count($params) - 2) . "si"; // last param is int (id)
        $stmt->bind_param($types, ...$params);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(["success"=>$success]);
        exit;
    } else {
        // Add new car
        $stmt = $conn->prepare("INSERT INTO cars (owner_id, car_name, brand, model, plate_number, price_per_day, color, car_year, body_style, location, issues, status, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssdsisssss", $owner_id, $car_name, $brand, $model, $plate_number, $price_per_day, $color, $car_year, $body_style, $location, $issues, $status, $image_path);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(["success"=>$success]);
        exit;
    }
}

// Handle DELETE (delete car)
if ($_SERVER["REQUEST_METHOD"] == "DELETE") {
    parse_str(file_get_contents("php://input"), $data);
    $id = intval($data["id"] ?? 0);
    if($id > 0) {
        $stmt = $conn->prepare("DELETE FROM cars WHERE id=?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(["success"=>$success]);
        exit;
    }
}

$conn->close();
?>
