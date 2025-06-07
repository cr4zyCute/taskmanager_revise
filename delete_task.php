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

// Check if task_id is provided via POST request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['task_id']) && !empty(trim($_POST['task_id']))) {
    $task_id = trim($_POST['task_id']);
    $user_id = $_SESSION["id"];

    // Prepare an update statement to mark the task as deleted
    $sql = "UPDATE tasks SET is_deleted = 1 WHERE id = ? AND user_id = ?";

    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "ii", $task_id, $user_id);

        // Attempt to execute the prepared statement
        if (mysqli_stmt_execute($stmt)) {
            // Check if any row was affected (task found and updated)
            if (mysqli_stmt_affected_rows($stmt) == 1) {
                echo json_encode(['success' => true, 'message' => 'Task moved to trash']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Task not found or already in trash']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error executing delete query: ' . mysqli_error($link)]);
        }

        // Close statement
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error preparing delete statement: ' . mysqli_error($link)]);
    }

    // Close connection
    mysqli_close($link);

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?> 