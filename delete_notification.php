<?php
// Initialize the session
session_start();

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Include config file
require_once "config.php";

// Check if notification_id is provided
if (!isset($_POST['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
    exit;
}

$notification_id = $_POST['notification_id'];
$user_id = $_SESSION["id"];

// Prepare delete statement
$sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $notification_id, $user_id);

    if (mysqli_stmt_execute($stmt)) {
        // Get updated unread count
        $count_sql = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0";
        if ($count_stmt = mysqli_prepare($link, $count_sql)) {
            mysqli_stmt_bind_param($count_stmt, "i", $user_id);
            mysqli_stmt_execute($count_stmt);
            $result = mysqli_stmt_get_result($count_stmt);
            $row = mysqli_fetch_assoc($result);
            $unread_count = $row['unread_count'];
            mysqli_stmt_close($count_stmt);

            echo json_encode([
                'success' => true,
                'message' => 'Notification deleted successfully',
                'unread_count' => $unread_count
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error getting unread count']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting notification']);
    }

    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Error preparing statement']);
}

mysqli_close($link);
