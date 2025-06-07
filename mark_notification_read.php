<?php
// Initialize the session
session_start();

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Include config file
require_once "config.php";

// Get the notification ID from the POST data
$data = json_decode(file_get_contents('php://input'), true);
$notification_id = isset($data['notification_id']) ? $data['notification_id'] : null;

if (!$notification_id) {
    echo json_encode(['success' => false, 'message' => 'No notification ID provided']);
    exit;
}

// Update the notification as read
$sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $notification_id, $_SESSION["id"]);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating notification']);
    }

    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Error preparing statement']);
}

mysqli_close($link);
