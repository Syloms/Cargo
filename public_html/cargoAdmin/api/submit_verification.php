<?php
// ===================================================
//  PERFECT MATCH FOR YOUR TABLE: user_verifications
// ===================================================

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") exit;

require_once __DIR__ . '/../include/db.php';

// ---------- Helper ----------
function respond($ok, $msg, $data = null) {
    echo json_encode([
        "success" => $ok,
        "message" => $msg,
        "data"    => $data
    ]);
    exit;
}

// ===================================================
// 1) Validate user_id
// ===================================================
$userId = isset($_POST["user_id"]) ? intval($_POST["user_id"]) : 0;
if ($userId <= 0) respond(false, "Missing user_id");


// ===================================================
// 2) Prevent duplicate pending/approved submissions
// ===================================================
$check = $conn->prepare("
    SELECT id, status 
    FROM user_verifications 
    WHERE user_id = ? AND status IN ('pending','approved') 
    LIMIT 1
");
$check->bind_param("i", $userId);
$check->execute();
$res = $check->get_result();

if ($res->num_rows > 0) {
    $r = $res->fetch_assoc();
    respond(false, "You already submitted verification (status: {$r['status']})");
}


// ===================================================
// 3) Collect fields coming from Flutter
// ===================================================
$firstName      = $_POST["first_name"] ?? "";
$lastName       = $_POST["last_name"] ?? "";
$email          = $_POST["email"] ?? "";
$mobile         = $_POST["mobile_number"] ?? "";
$gender         = $_POST["gender"] ?? null;

$dateOfBirth    = $_POST["date_of_birth"] ?? null;  
// Flutter sends YYYY-MM-DD ✔

$idType         = $_POST["id_type"] ?? "";

$region         = $_POST["permRegion"] ?? "";
$province       = $_POST["permProvince"] ?? "";
$municipality   = $_POST["permCity"] ?? "";
$barangay       = $_POST["permBarangay"] ?? "";


// ===================================================
// 4) File upload helper functions
// ===================================================
$uploadRoot = __DIR__ . "/../uploads/verifications";

$allowedMime = [
    "image/jpeg" => ".jpg",
    "image/png"  => ".png",
    "image/webp" => ".webp",
];
$maxSize = 5 * 1024 * 1024; // 5MB

if (!is_dir($uploadRoot)) mkdir($uploadRoot, 0755, true);

function saveFile($file, $userId, $uploadRoot, $allowedMime, $maxSize, $prefix) {
    if (!isset($file["tmp_name"]) || empty($file["tmp_name"])) return null;

    $tmp  = $file["tmp_name"];
    $mime = mime_content_type($tmp);
    $size = filesize($tmp);

    if ($size <= 0 || $size > $maxSize) return ["error" => "Invalid file size"];
    if (!isset($allowedMime[$mime])) return ["error" => "Invalid file type: $mime"];

    $ext = $allowedMime[$mime];
    $dir = $uploadRoot . "/" . date("Y") . "/" . date("m");
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $name = "{$prefix}_u{$userId}_" . time() . "_" . bin2hex(random_bytes(4)) . $ext;
    $path = "$dir/$name";

    if (!move_uploaded_file($tmp, $path)) return ["error" => "Failed to save file"];

    return ["path" => str_replace(dirname(__DIR__) . '/', "", $path)];
}

function saveBase64($b64, $userId, $uploadRoot, $allowedMime, $maxSize, $prefix) {
    if (!$b64) return null;

    if (preg_match('#^data:(image/[^;]+);base64,(.+)$#', $b64, $m)) {
        $mime = $m[1];
        $data = base64_decode($m[2]);
    } else {
        $data = base64_decode($b64);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($data);
    }

    if (!$data) return ["error" => "Invalid base64"];
    if (strlen($data) > $maxSize) return ["error" => "File too large"];
    if (!isset($allowedMime[$mime])) return ["error" => "Invalid file type: $mime"];

    $ext = $allowedMime[$mime];
    $dir = $uploadRoot . "/" . date("Y") . "/" . date("m");
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $name = "{$prefix}_u{$userId}_" . time() . "_" . bin2hex(random_bytes(4)) . $ext;
    $path = "$dir/$name";

    if (!file_put_contents($path, $data)) return ["error" => "Failed to save base64 file"];

    return ["path" => str_replace(dirname(__DIR__) . '/', "", $path)];
}


// ===================================================
// 5) Save uploaded files (front, back, selfie)
// ===================================================
$files = ["id_front_photo" => null, "id_back_photo" => null, "selfie_photo" => null];
$keys  = ["id_front", "id_back", "selfie"];

foreach ($keys as $k) {
    $col = $k . "_photo"; 

    // Multipart first
    if (isset($_FILES[$k])) {
        $r = saveFile($_FILES[$k], $userId, $uploadRoot, $allowedMime, $maxSize, $k);
        if (isset($r["error"])) respond(false, $r["error"]);
        $files[$col] = $r["path"];
    }

    // Base64 fallback
    if (!$files[$col] && isset($_POST[$k . "_base64"])) {
        $r = saveBase64($_POST[$k . "_base64"], $userId, $uploadRoot, $allowedMime, $maxSize, $k);
        if (isset($r["error"])) respond(false, $r["error"]);
        $files[$col] = $r["path"];
    }
}

if (!$files["id_front_photo"] || !$files["id_back_photo"] || !$files["selfie_photo"]) {
    respond(false, "All three images (front, back, selfie) are required");
}


// ===================================================
// 6) Insert into user_verifications
// ===================================================
$now = date("Y-m-d H:i:s");

$stmt = $conn->prepare("
    INSERT INTO user_verifications
    (user_id, first_name, last_name, email, mobile_number, gender,
     region, province, municipality, barangay, date_of_birth,
     id_type, id_front_photo, id_back_photo, selfie_photo, status, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
");

$stmt->bind_param(
    "isssssssssssssss",
    $userId,
    $firstName,
    $lastName,
    $email,
    $mobile,
    $gender,
    $region,
    $province,
    $municipality,
    $barangay,
    $dateOfBirth,
    $idType,
    $files["id_front_photo"],
    $files["id_back_photo"],
    $files["selfie_photo"],
    $now
);

if (!$stmt->execute()) {
    respond(false, "Database error: " . $stmt->error);
}


// ===================================================
// 7) Response
// ===================================================
respond(true, "Verification submitted successfully", [
    "verification_id" => $stmt->insert_id,
    "created_at" => $now,
    "id_front_photo" => $files["id_front_photo"],
    "id_back_photo" => $files["id_back_photo"],
    "selfie_photo" => $files["selfie_photo"]
]);

?>
