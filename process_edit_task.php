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

// Check if it's a POST request and task_id is provided
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['task_id']) && !empty(trim($_POST['task_id']))) {
    // Get form data
    $task_id = trim($_POST['task_id']);
    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $due_date = $_POST["due_date"];
    $user_id = $_SESSION["id"];
    $checklist_items_post = isset($_POST['checklist_item']) ? $_POST['checklist_item'] : [];
    $checklist_ids_post = isset($_POST['checklist_id']) ? $_POST['checklist_id'] : [];

    // Validate input
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a task title']);
        exit;
    }

    // Start transaction
    mysqli_begin_transaction($link);

    try {
        // Update task details
        $update_task_sql = "UPDATE tasks SET title = ?, description = ?, due_date = ? WHERE id = ? AND user_id = ?";
        $update_task_stmt = mysqli_prepare($link, $update_task_sql);
        mysqli_stmt_bind_param($update_task_stmt, "sssii", $title, $description, $due_date, $task_id, $user_id);

        if (!mysqli_stmt_execute($update_task_stmt)) {
            throw new Exception("Error updating task details: " . mysqli_error($link));
        }
        mysqli_stmt_close($update_task_stmt);

        // --- Handle Checklist Items ---

        // Fetch existing checklist item IDs for this task
        $existing_checklist_ids = [];
        $fetch_existing_sql = "SELECT id FROM task_checklist WHERE task_id = ?";
        if ($fetch_stmt = mysqli_prepare($link, $fetch_existing_sql)) {
            mysqli_stmt_bind_param($fetch_stmt, "i", $task_id);
            mysqli_stmt_execute($fetch_stmt);
            $existing_result = mysqli_stmt_get_result($fetch_stmt);
            while ($row = mysqli_fetch_assoc($existing_result)) {
                $existing_checklist_ids[] = $row['id'];
            }
            mysqli_stmt_close($fetch_stmt);
        }

        $updated_checklist_ids = [];
        $update_item_sql = "UPDATE task_checklist SET item_text = ?, is_completed = ? WHERE id = ?";
        $insert_item_sql = "INSERT INTO task_checklist (task_id, item_text, is_completed) VALUES (?, ?, ?)";

        $update_item_stmt = mysqli_prepare($link, $update_item_sql);
        $insert_item_stmt = mysqli_prepare($link, $insert_item_sql);

        foreach ($checklist_items_post as $index => $item_text) {
            $item_text = trim($item_text);
            // We only process non-empty checklist items from the form
            if (!empty($item_text)) {
                // Use the checklist_ids_post for existing items, or null for new ones
                $item_id = $checklist_ids_post[$index] ?? null;
                // For editing, checklist items are not marked complete in the edit form directly
                // Completion status is handled separately if you add checkboxes to the edit items
                $is_completed = 0; // Assuming completion status is not edited on this form

                if ($item_id && in_array($item_id, $existing_checklist_ids)) {
                    // Update existing item: only item_text is updated from this form
                    mysqli_stmt_bind_param($update_item_stmt, "sii", $item_text, $is_completed, $item_id); // is_completed is kept as 0 or fetched if needed
                    if (!mysqli_stmt_execute($update_item_stmt)) {
                        // Handle update error, but don't throw exception to allow other items to be processed
                        error_log("Error updating checklist item ID " . $item_id . ": " . mysqli_error($link));
                    }
                    // Add to updated list regardless of execution success to avoid deletion
                    $updated_checklist_ids[] = $item_id;
                } else {
                    // Insert new item
                    mysqli_stmt_bind_param($insert_item_stmt, "isi", $task_id, $item_text, $is_completed); // is_completed is always 0 for new items
                    if (!mysqli_stmt_execute($insert_item_stmt)) {
                        // Handle insert error
                        error_log("Error inserting new checklist item: " . mysqli_error($link));
                    }
                    // If insertion was successful, get the new ID and add to updated list
                    if (mysqli_stmt_affected_rows($insert_item_stmt) > 0) {
                        $updated_checklist_ids[] = mysqli_insert_id($link);
                    }
                }
            }
        }
        // Close prepared statements
        if ($update_item_stmt) mysqli_stmt_close($update_item_stmt);
        if ($insert_item_stmt) mysqli_stmt_close($insert_item_stmt);

        // Delete checklist items that were removed in the form
        $deleted_ids = array_diff($existing_checklist_ids, $updated_checklist_ids);
        if (!empty($deleted_ids)) {
            // Prepare delete statement for multiple IDs
            $delete_placeholder = implode(',', array_fill(0, count($deleted_ids), '?'));
            $delete_item_sql = "DELETE FROM task_checklist WHERE id IN (" . $delete_placeholder . ")";
            if ($delete_stmt = mysqli_prepare($link, $delete_item_sql)) {
                // Bind parameters dynamically based on the number of IDs
                $types = str_repeat('i', count($deleted_ids));
                mysqli_stmt_bind_param($delete_stmt, $types, ...$deleted_ids);
                if (!mysqli_stmt_execute($delete_stmt)) {
                    error_log("Error deleting checklist items: " . mysqli_error($link));
                }
                mysqli_stmt_close($delete_stmt);
            }
        }

        // Commit transaction
        mysqli_commit($link);
        echo json_encode(['success' => true, 'message' => 'Task updated successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($link);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    // Close connection
    mysqli_close($link);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request or task ID missing']);
}
