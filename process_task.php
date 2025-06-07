<?php
// Initialize the session
session_start();

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Please log in to create tasks']);
    exit;
}

// Include config file
require_once "config.php";

// Check if it's a POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $due_date = $_POST["due_date"];
    $user_id = $_SESSION["id"];
    $checklist_items = isset($_POST["checklist"]) ? $_POST["checklist"] : [];

    // Validate input
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a task title']);
        exit;
    }

    // Start transaction
    mysqli_begin_transaction($link);

    try {
        // Insert task
        $sql = "INSERT INTO tasks (user_id, title, description, due_date, status) VALUES (?, ?, ?, ?, 'pending')";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "isss", $user_id, $title, $description, $due_date);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error creating task");
        }

        $task_id = mysqli_insert_id($link);
        mysqli_stmt_close($stmt);

        // Insert checklist items if any
        if (!empty($checklist_items)) {
            $checklist_sql = "INSERT INTO task_checklist (task_id, item_text, is_completed) VALUES (?, ?, 0)";
            $checklist_stmt = mysqli_prepare($link, $checklist_sql);

            foreach ($checklist_items as $item) {
                if (!empty(trim($item))) {
                    mysqli_stmt_bind_param($checklist_stmt, "is", $task_id, $item);
                    if (!mysqli_stmt_execute($checklist_stmt)) {
                        throw new Exception("Error adding checklist items");
                    }
                }
            }
            mysqli_stmt_close($checklist_stmt);
        }

        // Commit transaction
        mysqli_commit($link);
        echo json_encode(['success' => true, 'message' => 'Task created successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($link);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    // Close connection
    mysqli_close($link);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
