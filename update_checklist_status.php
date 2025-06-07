<?php

ob_start(); // Start output buffering at the very beginning

// Turn off error displaying to prevent HTML output
ini_set('display_errors', 0);
error_reporting(0);

// Set the content type to application/json
header('Content-Type: application/json');

// Initialize the session
session_start();

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    // Clean any previous output before echoing JSON
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    ob_end_flush();
    exit;
}

// Include config file within a try block to catch potential errors during inclusion
try {
    require_once "config.php";

    // Check for database connection error after including config.php
    if (isset($db_connection_error) && $db_connection_error !== null) {
        throw new Exception('Database connection error: ' . $db_connection_error);
    }

    // Check if required parameters are present
    if (!isset($_POST['checklist_id']) || !isset($_POST['task_id']) || !isset($_POST['is_completed'])) {
        throw new Exception('Missing required parameters');
    }

    $checklist_id = $_POST['checklist_id'];
    $task_id = $_POST['task_id'];
    $is_completed = $_POST['is_completed'];
    $user_id = $_SESSION["id"];

    // Verify that the task belongs to the user
    $verify_sql = "SELECT id FROM tasks WHERE id = ? AND user_id = ?";
    $verify_stmt = mysqli_prepare($link, $verify_sql);

    // Check if statement preparation failed
    if ($verify_stmt === false) {
        throw new Exception('Database query error (verify): ' . mysqli_error($link));
    }

    mysqli_stmt_bind_param($verify_stmt, "ii", $task_id, $user_id);
    mysqli_stmt_execute($verify_stmt);
    mysqli_stmt_store_result($verify_stmt);

    if (mysqli_stmt_num_rows($verify_stmt) == 0) {
        mysqli_stmt_close($verify_stmt);
        throw new Exception('Task not found or unauthorized');
    }

    mysqli_stmt_close($verify_stmt);

    // Update the checklist item status
    $update_sql = "UPDATE task_checklist SET is_completed = ? WHERE id = ? AND task_id = ?";
    $update_stmt = mysqli_prepare($link, $update_sql);

    // Check if statement preparation failed
    if ($update_stmt === false) {
        throw new Exception('Database query error (update): ' . mysqli_error($link));
    }

    mysqli_stmt_bind_param($update_stmt, "iii", $is_completed, $checklist_id, $task_id);

    if (mysqli_stmt_execute($update_stmt)) {
        // Clean any previous output before echoing JSON
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Checklist item updated successfully']);
    } else {
        throw new Exception('Error updating checklist item: ' . mysqli_error($link));
    }

    mysqli_stmt_close($update_stmt);
} catch (Exception $e) {
    // Catch any exceptions, clean buffer, and return a JSON error
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    // Ensure connection is closed and buffer is flushed
    if (isset($link) && $link) {
        mysqli_close($link);
    }
    ob_end_flush();
}
