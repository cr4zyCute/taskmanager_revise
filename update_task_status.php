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

// Check if task_id and status are provided via POST request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['task_id']) && isset($_POST['status'])) {
    $task_id = trim($_POST['task_id']);
    $status = trim($_POST['status']);
    $user_id = $_SESSION["id"];

    // Validate status
    $valid_statuses = ['pending', 'in-progress', 'completed'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    // Prepare an update statement to update the task status
    $sql = "UPDATE tasks SET status = ? WHERE id = ? AND user_id = ?";

    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "sii", $status, $task_id, $user_id);

        // Attempt to execute the prepared statement
        if (mysqli_stmt_execute($stmt)) {
            // Check if any row was affected (task found and updated)
            if (mysqli_stmt_affected_rows($stmt) == 1) {
                // Fetch task title for notification
                $task_title = 'Task'; // Default title if fetching fails
                $fetch_title_sql = "SELECT title FROM tasks WHERE id = ? AND user_id = ? LIMIT 1";
                if ($fetch_title_stmt = mysqli_prepare($link, $fetch_title_sql)) {
                    mysqli_stmt_bind_param($fetch_title_stmt, "ii", $task_id, $user_id);
                    mysqli_stmt_execute($fetch_title_stmt);
                    $title_result = mysqli_stmt_get_result($fetch_title_stmt);
                    if ($title_row = mysqli_fetch_assoc($title_result)) {
                        $task_title = htmlspecialchars($title_row['title']);
                    }
                    mysqli_stmt_close($fetch_title_stmt);
                }

                // Insert a notification for the status change
                $notification_type = 'status_change';
                $notification_message = "Task '" . $task_title . "' status updated to '" . $status . "'.";
                $insert_notification_sql = "INSERT INTO notifications (user_id, task_id, type, message) VALUES (?, ?, ?, ?)";
                if ($insert_notification_stmt = mysqli_prepare($link, $insert_notification_sql)) {
                    mysqli_stmt_bind_param($insert_notification_stmt, "iiss", $user_id, $task_id, $notification_type, $notification_message);
                    mysqli_stmt_execute($insert_notification_stmt);
                    mysqli_stmt_close($insert_notification_stmt);
                }

                echo json_encode(['success' => true, 'message' => 'Task status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Task not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error executing update query: ' . mysqli_error($link)]);
        }

        // Close statement
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error preparing update statement: ' . mysqli_error($link)]);
    }

    // Close connection
    mysqli_close($link);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
