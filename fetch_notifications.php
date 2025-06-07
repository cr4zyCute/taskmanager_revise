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

$user_id = $_SESSION["id"];

// Get unread count
$count_sql = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0";
$count_stmt = mysqli_prepare($link, $count_sql);
mysqli_stmt_bind_param($count_stmt, "i", $user_id);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$unread_count = mysqli_fetch_assoc($count_result)['unread_count'];
mysqli_stmt_close($count_stmt);

// Get notifications
$sql = "SELECT id, type, message, created_at, is_read 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $notifications = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = [
                'id' => $row['id'],
                'type' => $row['type'],
                'message' => $row['message'],
                'created_at' => $row['created_at'],
                'is_read' => (bool)$row['is_read']
            ];
        }

        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => (int)$unread_count
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error executing query: ' . mysqli_error($link)
        ]);
    }

    mysqli_stmt_close($stmt);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error preparing statement: ' . mysqli_error($link)
    ]);
}

mysqli_close($link);
