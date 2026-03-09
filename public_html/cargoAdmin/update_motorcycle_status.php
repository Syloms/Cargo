<?php
include "include/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if (empty($id) || empty($status)) {
        header("Location: get_motorcycle_admin.php?error=missing_data");
        exit;
    }

    // Update motorcycle status
    $stmt = $conn->prepare("UPDATE motorcycles SET status = ?, remarks = ? WHERE id = ?");
    $stmt->bind_param("ssi", $status, $remarks, $id);

    if ($stmt->execute()) {
        // Get owner_id and motorcycle details
        $motorcycleStmt = $conn->prepare("SELECT owner_id, brand, model FROM motorcycles WHERE id = ?");
        $motorcycleStmt->bind_param("i", $id);
        $motorcycleStmt->execute();
        $result = $motorcycleStmt->get_result();
        $motorcycle = $result->fetch_assoc();

        if ($motorcycle) {
            $owner_id = $motorcycle['owner_id'];
            $vehicleName = $motorcycle['brand'] . ' ' . $motorcycle['model'];

            // Send notification
            $notifStmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, read_status, created_at) 
                VALUES (?, ?, ?, 'unread', NOW())
            ");

            if ($status === 'approved') {
                $title = "Motorcycle Approved ✅";
                $message = "Your motorcycle '$vehicleName' has been approved and is now visible to renters.";
            } else {
                $title = "Motorcycle Rejected ❌";
                $message = "Your motorcycle '$vehicleName' was rejected. Reason: $remarks";
            }

            $notifStmt->bind_param("iss", $owner_id, $title, $message);
            $notifStmt->execute();
            $notifStmt->close();
        }

        $motorcycleStmt->close();
        header("Location: get_motorcycle_admin.php?success=status_updated");
    } else {
        header("Location: get_motorcycle_admin.php?error=update_failed");
    }

    $stmt->close();
    $conn->close();
}
?>