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

// Check if task_id is provided via GET request
if (isset($_GET['task_id']) && !empty(trim($_GET['task_id']))) {
    $task_id = trim($_GET['task_id']);
    $user_id = $_SESSION["id"];

    // Prepare a select statement to get task details
    $sql = "SELECT id, title, description, due_date, created_at, status FROM tasks WHERE id = ? AND user_id = ?";

    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "ii", $task_id, $user_id);

        // Attempt to execute the prepared statement
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) == 1) {
                $task = mysqli_fetch_assoc($result);

                // Now fetch checklist items for this task
                $checklist_sql = "SELECT item_text, is_completed FROM task_checklist WHERE task_id = ? ORDER BY id ASC";
                if ($checklist_stmt = mysqli_prepare($link, $checklist_sql)) {
                    mysqli_stmt_bind_param($checklist_stmt, "i", $task_id);
                    if (mysqli_stmt_execute($checklist_stmt)) {
                        $checklist_result = mysqli_stmt_get_result($checklist_stmt);
                        $checklist_items = [];
                        while ($row = mysqli_fetch_assoc($checklist_result)) {
                            $checklist_items[] = $row;
                        }
                        $task['checklist'] = $checklist_items;
                    }
                    mysqli_stmt_close($checklist_stmt);
                }

                echo json_encode(['success' => true, 'task' => $task]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Task not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error executing task query: ' . mysqli_error($link)]);
        }

        // Close statement
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error preparing task statement: ' . mysqli_error($link)]);
    }

    // Close connection
    mysqli_close($link);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
}
