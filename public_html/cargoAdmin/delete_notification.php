<?php
include 'include/db.php';

$id = $_POST['id'];

$sql = "DELETE FROM notifications WHERE id = '$id'";

if(mysqli_query($conn, $sql)) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
}
?>
