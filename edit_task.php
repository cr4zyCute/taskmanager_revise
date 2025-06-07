<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Include config file
require_once "config.php";

$task_id = null;
$title = $description = $due_date = "";
$checklist_items = [];
$user_id = $_SESSION["id"];
$error_message = "";

// Fetch user profile data for the sidebar
$user_sql = "SELECT username, email, full_name, phone, address, profile_picture FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($link, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user_data = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($user_stmt);

// Check if task_id is provided in the URL
if (isset($_GET['task_id']) && !empty(trim($_GET['task_id']))) {
    $task_id = trim($_GET['task_id']);

    // Prepare a select statement to fetch task details
    $sql = "SELECT title, description, due_date FROM tasks WHERE id = ? AND user_id = ?";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $task_id, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) == 1) {
                $task = mysqli_fetch_assoc($result);
                $title = $task['title'];
                $description = $task['description'];
                $due_date = $task['due_date'];

                // Fetch checklist items
                $checklist_sql = "SELECT id, item_text, is_completed FROM task_checklist WHERE task_id = ? ORDER BY id ASC";
                if ($checklist_stmt = mysqli_prepare($link, $checklist_sql)) {
                    mysqli_stmt_bind_param($checklist_stmt, "i", $task_id);
                    if (mysqli_stmt_execute($checklist_stmt)) {
                        $checklist_result = mysqli_stmt_get_result($checklist_stmt);
                        while ($row = mysqli_fetch_assoc($checklist_result)) {
                            $checklist_items[] = $row;
                        }
                    }
                    mysqli_stmt_close($checklist_stmt);
                }
            } else {
                $error_message = "Task not found.";
            }
        } else {
            $error_message = "Error fetching task: " . mysqli_error($link);
        }
        mysqli_stmt_close($stmt);
    }
} else if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Process form submission
    $task_id = $_POST['task_id'];
    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $due_date = $_POST["due_date"];
    $checklist_items_post = isset($_POST['checklist_item']) ? $_POST['checklist_item'] : [];
    $checklist_ids_post = isset($_POST['checklist_id']) ? $_POST['checklist_id'] : [];
    $checklist_completed_post = isset($_POST['checklist_completed']) ? $_POST['checklist_completed'] : [];

    // Validate input
    if (empty($title)) {
        $error_message = "Please enter a task title.";
    }

    if (empty($error_message)) {
        // Update task details
        $update_task_sql = "UPDATE tasks SET title = ?, description = ?, due_date = ? WHERE id = ? AND user_id = ?";
        if ($update_task_stmt = mysqli_prepare($link, $update_task_sql)) {
            mysqli_stmt_bind_param($update_task_stmt, "sssii", $title, $description, $due_date, $task_id, $user_id);
            mysqli_stmt_execute($update_task_stmt);
            mysqli_stmt_close($update_task_stmt);
        }

        // Update and insert checklist items
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
            if (!empty($item_text)) {
                $item_id = $checklist_ids_post[$index] ?? null;
                $is_completed = in_array($item_id, $checklist_completed_post) ? 1 : 0;

                if ($item_id && in_array($item_id, $existing_checklist_ids)) {
                    // Update existing item
                    mysqli_stmt_bind_param($update_item_stmt, "sii", $item_text, $is_completed, $item_id);
                    mysqli_stmt_execute($update_item_stmt);
                    $updated_checklist_ids[] = $item_id;
                } else {
                    // Insert new item
                    mysqli_stmt_bind_param($insert_item_stmt, "isi", $task_id, $item_text, $is_completed);
                    mysqli_stmt_execute($insert_item_stmt);
                }
            }
        }
        mysqli_stmt_close($update_item_stmt);
        mysqli_stmt_close($insert_item_stmt);

        // Delete checklist items that were removed in the form
        $deleted_ids = array_diff($existing_checklist_ids, $updated_checklist_ids);
        if (!empty($deleted_ids)) {
            $delete_item_sql = "DELETE FROM task_checklist WHERE id IN (" . implode(",", $deleted_ids) . ")";
            mysqli_query($link, $delete_item_sql);
        }

        // Redirect to tasks page after successful update
        header("location: tasks.php");
        exit;
    }
}

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager - Edit Task</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Add specific styles for the edit task page if needed */
    </style>
</head>

<body>
    <div class="container">
        <div class="sidebar">
            <div class="profile">
                <img src="<?php echo !empty($user_data['profile_picture']) ? htmlspecialchars($user_data['profile_picture']) : 'placeholder.png'; ?>" alt="User Profile">
                <span><?php echo htmlspecialchars($user_data['full_name'] ?? $user_data['username']); ?></span>
            </div>
            <nav class="navigation">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="trash.php"><i class="fas fa-trash"></i> Trash</a></li>
                </ul>
            </nav>
        </div>
        <div class="main-content">
            <header class="header">
                <h2>Edit Task</h2>
                <div class="user-menu">
                    <img src="<?php echo !empty($user_data['profile_picture']) ? htmlspecialchars($user_data['profile_picture']) : 'placeholder.png'; ?>" alt="User Profile" id="userMenuBtn">
                    <div class="dropdown" id="userDropdown">
                        <ul>
                            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <div class="tasks-container">
                <?php if (!empty($error_message)) : ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <?php if ($task_id !== null && empty($error_message)) : ?>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($title); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($description); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Due Date</label>
                            <input type="date" name="due_date" class="form-control" value="<?php echo htmlspecialchars($due_date); ?>">
                        </div>

                        <div class="form-group">
                            <label>Checklist Items</label>
                            <div id="checklistItemsContainer">
                                <?php if (!empty($checklist_items)) : ?>
                                    <?php foreach ($checklist_items as $item) : ?>
                                        <div class="checklist-item">
                                            <input type="hidden" name="checklist_id[]" value="<?php echo $item['id']; ?>">
                                            <input type="checkbox" name="checklist_completed[]" value="<?php echo $item['id']; ?>" <?php echo $item['is_completed'] ? 'checked' : ''; ?>>
                                            <input type="text" name="checklist_item[]" class="form-control" value="<?php echo htmlspecialchars($item['item_text']); ?>">
                                            <button type="button" class="remove-item"><i class="fas fa-times"></i></button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <div id="newChecklistItemTemplate" style="display: none;">
                                    <div class="checklist-item">
                                        <input type="text" name="checklist_item[]" class="form-control" value="">
                                        <button type="button" class="remove-item"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" id="addChecklistItemBtn" class="add-item-btn"><i class="fas fa-plus"></i> Add Item</button>
                        </div>

                        <div class="form-group">
                            <input type="submit" class="btn btn-primary" value="Save Changes">
                            <a href="tasks.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                <?php elseif ($task_id === null) : ?>
                    <p>No task ID provided for editing.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // JavaScript to handle adding and removing checklist items dynamically
        document.getElementById('addChecklistItemBtn').addEventListener('click', function() {
            const container = document.getElementById('checklistItemsContainer');
            const template = document.getElementById('newChecklistItemTemplate').innerHTML;
            container.insertAdjacentHTML('beforeend', template);
            // Add event listener to the new remove button
            container.lastElementChild.querySelector('.remove-item').addEventListener('click', function() {
                this.closest('.checklist-item').remove();
            });
        });

        // Add event listeners to initial remove buttons
        document.querySelectorAll('#checklistItemsContainer .remove-item').forEach(button => {
            button.addEventListener('click', function() {
                this.closest('.checklist-item').remove();
            });
        });
        // Toggle user dropdown menu
        const userMenuBtn = document.getElementById('userMenuBtn');
        const userDropdown = document.getElementById('userDropdown');

        userMenuBtn.addEventListener('click', function(event) {
            event.stopPropagation(); // Prevent click from closing dropdown immediately
            userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
        });

        // Close dropdown when clicking outside
        window.addEventListener('click', function(event) {
            if (!userMenuBtn.contains(event.target) && !userDropdown.contains(event.target)) {
                userDropdown.style.display = 'none';
            }
        });
    </script>
</body>

</html>