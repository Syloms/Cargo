<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include("include/db.php");

$data = json_decode(file_get_contents("php://input"), true);
$email = $data["email"];

if (!$email) {
    echo json_encode(["status" => "error", "message" => "Email is required"]);
    exit;
}

$sql = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $code = rand(100000, 999999);

    // TODO: send email — for now, just return the code
    echo json_encode(["status" => "success", "message" => "Verification code sent", "code" => $code]);
} else {
    echo json_encode(["status" => "error", "message" => "Email not found"]);
}
?>
