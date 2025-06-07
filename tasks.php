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

// Fetch user profile data for the sidebar
$user_id = $_SESSION["id"];
$sql = "SELECT username, email, full_name, phone, address, profile_picture FROM users WHERE id = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Fetch tasks
$tasks_sql = "SELECT * FROM tasks WHERE user_id = ? AND is_deleted = 0 ORDER BY due_date ASC";
$tasks_stmt = mysqli_prepare($link, $tasks_sql);
mysqli_stmt_bind_param($tasks_stmt, "i", $user_id);
mysqli_stmt_execute($tasks_stmt);
$tasks_result = mysqli_stmt_get_result($tasks_stmt);
$tasks = mysqli_fetch_all($tasks_result, MYSQLI_ASSOC);
mysqli_stmt_close($tasks_stmt);
mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager - Tasks</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            padding: 20px;
        }

        .profile {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .profile img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 10px;
            border: 3px solid #3498db;
        }

        .profile span {
            display: block;
            font-size: 1.1em;
            margin-top: 10px;
        }

        .navigation ul {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }

        .navigation li {
            margin-bottom: 10px;
        }

        .navigation a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .navigation a:hover,
        .navigation li.active a {
            background-color: #34495e;
        }

        .navigation i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            background-color: #f5f6fa;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background-color: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .user-menu {
            display: flex;
            align-items: center;
            position: relative;
        }

        .user-menu img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            cursor: pointer;
        }

        .dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            padding: 10px 0;
            min-width: 150px;
        }

        .user-menu:hover .dropdown {
            display: block;
        }

        .dropdown ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .dropdown li a {
            display: flex;
            align-items: center;
            padding: 10px 20px;
            color: #333;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .dropdown li a:hover {
            background-color: #f5f6fa;
        }

        .dropdown i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .tasks-container {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .tasks-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .add-task-btn {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }

        .add-task-btn:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        .add-task-btn i {
            margin-right: 8px;
        }

        .task-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .task-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s;
        }

        .task-item:hover {
            background-color: #f8f9fa;
        }

        .task-item:last-child {
            border-bottom: none;
        }

        .task-checkbox {
            margin-right: 15px;
        }

        .task-checkbox input[type="checkbox"] {
            cursor: pointer;
            width: 18px;
            height: 18px;
        }

        .task-content {
            flex: 1;
        }

        .task-title {
            margin: 0 0 5px;
            color: #2c3e50;
            font-size: 16px;
        }

        .task-details {
            display: flex;
            gap: 15px;
            font-size: 0.9em;
            color: #7f8c8d;
        }

        .task-detail {
            display: flex;
            align-items: center;
        }

        .task-detail i {
            margin-right: 5px;
        }

        .task-actions {
            display: flex;
            gap: 10px;
        }

        .task-action-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #7f8c8d;
            transition: color 0.3s;
        }

        .task-action-btn:hover {
            color: #3498db;
        }

        .edit-btn:hover {
            color: #f39c12;
        }

        .delete-btn:hover {
            color: #e74c3c;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #bdc3c7;
        }

        .empty-state p {
            margin: 0;
            font-size: 16px;
        }

        .task-status {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .status-pending {
            background-color: #f1c40f;
            color: #fff;
        }

        .status-in-progress {
            background-color: #3498db;
            color: #fff;
        }

        .status-completed {
            background-color: #2ecc71;
            color: #fff;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            /* Slightly darker overlay */
            overflow: auto;
            padding-top: 50px;
            /* Adjust padding top */
            backdrop-filter: blur(5px);
            /* Add a blur effect to the background */
        }

        .modal-content {
            background-color: #ffffff;
            /* White background */
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            /* Slightly less rounded */
            width: 90%;
            max-width: 550px;
            /* Slightly wider */
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            /* Stronger shadow */
            position: relative;
            overflow: hidden;
            animation: animatetop 0.4s;
            /* Add animation */
        }

        /* Modal entry animation */
        @keyframes animatetop {
            from {
                top: -300px;
                opacity: 0
            }

            to {
                top: 5%;
                opacity: 1
            }
        }

        .modal-header {
            background-color: #e9e9eb;
            /* Light grey background */
            padding: 15px 25px;
            /* Adjusted padding */
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
            /* Add bottom border */
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .modal-header h2 {
            margin: 0;
            color: #333;
            /* Darker text */
            font-size: 22px;
            /* Slightly larger font */
            font-weight: 600;
            /* Semibold */
        }

        .modal-header .task-actions-icons {
            display: flex;
            align-items: center;
            gap: 15px;
            /* Increased gap */
        }

        .modal-header .task-actions-icons .task-action-btn {
            color: #666;
            /* Slightly lighter icons */
            font-size: 18px;
            /* Slightly larger icons */
            padding: 5px;
            /* Add some padding */
            transition: color 0.3s ease;
            /* Smooth transition */
        }

        .modal-header .task-actions-icons .task-action-btn:hover {
            color: #000;
            /* Darker on hover */
        }

        .close {
            color: #aaa;
            font-size: 30px;
            /* Larger close button */
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
            /* Smooth transition */
        }

        .close:hover,
        .close:focus {
            color: #777;
            /* Darker on hover/focus */
            text-decoration: none;
            cursor: pointer;
        }

        .modal-body {
            padding: 25px;
            /* Adjusted padding */
        }

        .form-group {
            margin-bottom: 20px;
            /* Increased bottom margin */
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            /* Increased bottom margin */
            color: #555;
            /* Slightly lighter label color */
            font-weight: 600;
            /* Semibold */
            font-size: 15px;
        }

        .form-group input[type="text"],
        .form-group textarea,
        .form-group input[type="date"] {
            width: 100%;
            padding: 10px 12px;
            /* Adjusted padding */
            border: 1px solid #ccc;
            /* Lighter border */
            border-radius: 5px;
            font-size: 15px;
            /* Slightly larger font */
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            /* Smooth transition */
            box-sizing: border-box;
            /* Include padding and border in element's total width and height */
        }

        .form-group input[type="text"]:focus,
        .form-group textarea:focus,
        .form-group input[type="date"]:focus {
            border-color: #007bff;
            /* Blue border on focus */
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.2);
            /* Subtle shadow on focus */
            outline: none;
        }

        .form-actions {
            text-align: right;
            margin-top: 30px;
            /* Increased top margin */
        }

        .submit-btn {
            background-color: #28a745;
            /* Green color for submit button */
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            /* Slightly larger font */
            font-weight: 600;
            /* Semibold */
            transition: background-color 0.3s ease, transform 0.1s ease;
            /* Smooth transition and subtle press effect */
        }

        .submit-btn:hover {
            background-color: #218838;
            /* Darker green on hover */
        }

        .submit-btn:active {
            transform: scale(0.98);
            /* Slightly shrink on click */
        }

        .checklist-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            gap: 10px;
        }

        .checklist-item input[type="text"] {
            flex: 1;
            padding: 8px 10px;
            /* Adjusted padding */
            font-size: 14px;
        }

        .remove-item {
            background: none;
            border: none;
            color: #dc3545;
            /* Red color for remove button */
            font-size: 20px;
            /* Keep size */
            cursor: pointer;
            padding: 5px;
            /* Add padding */
            transition: color 0.3s ease;
        }

        .remove-item:hover {
            color: #c82333;
            /* Darker red on hover */
        }

        .add-item-btn {
            background: none;
            border: 1px dashed #007bff;
            /* Blue dashed border */
            color: #007bff;
            /* Blue text color */
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .add-item-btn:hover {
            background-color: #007bff;
            /* Blue background on hover */
            color: white;
        }

        .add-item-btn i {
            font-size: 12px;
        }

        .preview-checklist {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }

        .checklist-item-preview {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .checklist-item-preview:last-child {
            border-bottom: none;
        }

        .checklist-checkbox {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            cursor: pointer;
            accent-color: #2ecc71;
        }

        .checklist-text {
            flex: 1;
            font-size: 14px;
            color: #2c3e50;
        }

        .checklist-checkbox:checked+.checklist-text {
            text-decoration: line-through;
            color: #95a5a6;
        }
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
                    <li class="active"><a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="trash.php"><i class="fas fa-trash"></i> Trash</a></li>
                </ul>
            </nav>
        </div>
        <div class="main-content">
            <header class="header">
                <div class="header-left">
                    <h2>Tasks</h2>
                </div>
                <div class="header-right">
                    <div class="user-menu">
                        <img src="<?php echo !empty($user_data['profile_picture']) ? htmlspecialchars($user_data['profile_picture']) : 'placeholder.png'; ?>" alt="User Avatar">
                        <div class="dropdown">
                            <ul>
                                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                                <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
                                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Log out</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>
            <div class="tasks-container">
                <div class="tasks-header">
                    <h3>My Tasks</h3>
                    <button class="add-task-btn">
                        <i class="fas fa-plus"></i> Add New Task
                    </button>
                </div>
                <?php if (!empty($tasks)): ?>
                    <ul class="task-list">
                        <?php foreach ($tasks as $task): ?>
                            <li class="task-item">
                                <div class="task-content">
                                    <h3 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h3>
                                    <div class="task-details">
                                        <div class="task-detail"><i class="far fa-calendar"></i> <?php echo htmlspecialchars($task['due_date']); ?></div>
                                    </div>
                                </div>
                                <div class="task-actions">
                                    <button class="task-action-btn view-task-btn" data-task-id="<?php echo $task['id']; ?>" title="View Task">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="task-action-btn edit-task-btn" data-task-id="<?php echo $task['id']; ?>" title="Edit Task">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="task-action-btn delete-task-btn" data-task-id="<?php echo $task['id']; ?>" title="Move to Trash">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <div class="task-checkbox">
                                    <input type="checkbox" data-task-id="<?php echo $task['id']; ?>" <?php echo $task['status'] === 'completed' ? 'checked' : ''; ?>>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard"></i>
                        <p>No tasks found. Add a new task to get started!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Task Modal -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Task</h2>
                <div class="task-actions-icons">
                    <span class="close">&times;</span>
                </div>
            </div>
            <form id="taskForm" action="process_task.php" method="POST">
                <div class="form-group">
                    <label for="title">Task Title</label>
                    <input type="text" id="title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="due_date">Due Date</label>
                    <input type="date" id="due_date" name="due_date" required>
                </div>
                <div class="form-group">
                    <label>Checklist Items</label>
                    <div id="checklist-container">
                        <div class="checklist-item">
                            <input type="text" name="checklist[]" placeholder="Add checklist item">
                            <button type="button" class="remove-item" style="display: none;">×</button>
                        </div>
                    </div>
                    <button type="button" id="add-checklist-item" class="add-item-btn">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
                <div class="form-actions">
                    <button type="submit" class="submit-btn">Create Task</button>
                </div>
            </form>
        </div>
    </div>

    <!-- The Modal Structure -->
    <div id="taskPreviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="taskPreviewTitle"></h2>
                <div class="task-actions-icons">
                    <button class="task-action-btn edit-task-btn" data-task-id="" title="Edit Task"><i class="fas fa-edit"></i></button>
                    <button class="task-action-btn delete-task-btn" data-task-id="" title="Delete Task"><i class="fas fa-trash"></i></button>
                    <span class="close">&times;</span>
                </div>
            </div>
            <div class="modal-body">
                <p class="task-created-date">Created: <span id="taskPreviewCreated"></span></p>
                <p class="task-deadline-status">Deadline: <span id="taskPreviewDeadline"></span> | Status: <span id="taskPreviewStatus"></span></p>
                <div id="taskPreviewDescription"></div>
            </div>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div id="editTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Task</h2>
                <span class="close">&times;</span>
            </div>
            <form id="editTaskForm" action="process_edit_task.php" method="POST">
                <input type="hidden" name="task_id" id="editTaskId">
                <div class="form-group">
                    <label for="editTitle">Task Title</label>
                    <input type="text" id="editTitle" name="title" required>
                </div>
                <div class="form-group">
                    <label for="editDescription">Description</label>
                    <textarea id="editDescription" name="description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="editDueDate">Due Date</label>
                    <input type="date" id="editDueDate" name="due_date" required>
                </div>
                <div class="form-group">
                    <label>Checklist Items</label>
                    <div id="editChecklistContainer">
                        <!-- Checklist items will be loaded here -->
                    </div>
                    <button type="button" id="add-edit-checklist-item" class="add-item-btn">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
                <div class="form-actions">
                    <button type="submit" class="submit-btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Get modal elements
        const modal = document.getElementById('taskModal'); // Create Task Modal
        const addTaskBtn = document.querySelector('.add-task-btn');
        const closeBtn = modal.querySelector('.close'); // Specific close button for Create Task Modal

        // Open Create Task modal when clicking add task button
        addTaskBtn.onclick = function() {
            // Reset the form for creating a new task
            document.getElementById('taskForm').reset();
            document.querySelector('#taskModal h2').innerText = 'Create New Task';
            document.querySelector('#taskForm button[type="submit"]').innerText = 'Create Task';
            // Clear and reset checklist for create form
            const createChecklistContainer = document.getElementById('checklist-container');
            createChecklistContainer.innerHTML = `
                <div class="checklist-item">
                    <input type="text" name="checklist[]" placeholder="Add checklist item">
                    <button type="button" class="remove-item" style="display: none;">×</button>
                </div>
            `;
            // Hide remove button for the single initial item
            createChecklistContainer.querySelector('.remove-item').style.display = 'none';

            modal.style.display = "block";
        }

        // Close Create Task modal when clicking the X
        closeBtn.onclick = function() {
            modal.style.display = "none";
        }

        // Get the preview modal
        const previewModal = document.getElementById("taskPreviewModal");

        // Get the <span> element that closes the preview modal
        const previewCloseBtn = previewModal.querySelector('.close'); // Specific close button for Preview Modal

        // Function to open the preview modal and load task data
        function openTaskPreview(taskId) {
            fetch('get_task_details.php?task_id=' + taskId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const task = data.task;
                        document.getElementById('taskPreviewTitle').innerText = task.title;
                        document.getElementById('taskPreviewCreated').innerText = new Date(task.created_at).toLocaleString();
                        document.getElementById('taskPreviewDeadline').innerText = task.due_date || 'No deadline';
                        document.getElementById('taskPreviewStatus').innerText = task.status;

                        // Update data-task-id for edit and delete buttons in the modal header
                        document.querySelector('#taskPreviewModal .edit-task-btn').setAttribute('data-task-id', task.id);
                        document.querySelector('#taskPreviewModal .delete-task-btn').setAttribute('data-task-id', task.id);

                        let descriptionHTML = '<p>' + (task.description || 'No description') + '</p>';

                        // Append checklist items to description
                        if (task.checklist && task.checklist.length > 0) {
                            descriptionHTML += '<h4>Checklist:</h4><ul class="preview-checklist">';
                            task.checklist.forEach(item => {
                                descriptionHTML += `
                                    <li class="checklist-item-preview">
                                        <input type="checkbox" class="checklist-checkbox" 
                                            data-checklist-id="${item.id}" 
                                            data-task-id="${task.id}"
                                            ${item.is_completed ? 'checked' : ''}>
                                        <span class="checklist-text">${item.item_text}</span>
                                    </li>`;
                            });
                            descriptionHTML += '</ul>';
                        }

                        document.getElementById('taskPreviewDescription').innerHTML = descriptionHTML;

                        previewModal.style.display = "block";
                    } else {
                        alert('Error loading task details: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching task details:', error);
                    alert('An error occurred while loading task details.');
                });
        }

        // Add event listeners to view buttons
        document.querySelectorAll('.view-task-btn').forEach(button => {
            button.addEventListener('click', function() {
                const taskId = this.getAttribute('data-task-id');
                if (taskId) {
                    openTaskPreview(taskId);
                }
            });
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        // When the user clicks on <span> (x), close the preview modal
        previewCloseBtn.onclick = function() {
            previewModal.style.display = "none";
        }

        // When the user clicks anywhere outside of the modals, close the respective modal
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
            if (event.target == previewModal) {
                previewModal.style.display = "none";
            }
        }

        // Function to handle task deletion
        function deleteTask(taskId) {
            if (confirm("Are you sure you want to move this task to trash?")) {
                fetch('delete_task.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'task_id=' + taskId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            // Remove the task item from the list
                            document.querySelector('.task-item button[data-task-id="' + taskId + '"]').closest('.task-item').remove();
                            // Close the preview modal if it's open
                            modal.style.display = "none";
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error deleting task:', error);
                        alert('An error occurred while deleting the task.');
                    });
            }
        }

        // Add event listeners to delete buttons (in task list and modal)
        document.querySelectorAll('.delete-task-btn').forEach(button => {
            button.addEventListener('click', function(event) {
                event.stopPropagation(); // Prevent opening the preview modal when clicking delete icon in task list
                const taskId = this.getAttribute('data-task-id');
                if (taskId) {
                    deleteTask(taskId);
                }
            });
        });

        // Get the edit task modal
        const editTaskModal = document.getElementById("editTaskModal");
        const editCloseBtn = editTaskModal.querySelector('.close'); // Specific close button for Edit Task Modal
        const editTaskForm = document.getElementById('editTaskForm');
        const editTaskIdInput = document.getElementById('editTaskId');
        const editTitleInput = document.getElementById('editTitle');
        const editDescriptionInput = document.getElementById('editDescription');
        const editDueDateInput = document.getElementById('editDueDate');
        const editChecklistContainer = document.getElementById('editChecklistContainer');
        const addEditChecklistItemBtn = document.getElementById('add-edit-checklist-item');

        // Function to open the edit task modal and load data
        function openEditTaskModal(taskId) {
            // Reset form for editing
            editTaskForm.reset();
            editChecklistContainer.innerHTML = ''; // Clear checklist items

            fetch('get_task_details.php?task_id=' + taskId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const task = data.task;
                        editTaskIdInput.value = task.id;
                        editTitleInput.value = task.title;
                        editDescriptionInput.value = task.description;
                        editDueDateInput.value = task.due_date;

                        // Populate checklist items
                        if (task.checklist && task.checklist.length > 0) {
                            task.checklist.forEach(item => {
                                const newItem = document.createElement('div');
                                newItem.className = 'checklist-item';
                                newItem.innerHTML = `
                                    <input type="hidden" name="checklist_id[]" value="${item.id}">
                                    <input type="text" name="checklist_item[]" value="${item.item_text || ''}" placeholder="Add checklist item">
                                    <button type="button" class="remove-item">×</button>
                                `;
                                editChecklistContainer.appendChild(newItem);
                            });
                        } else {
                            // Add a single empty checklist item if none exist
                            const newItem = document.createElement('div');
                            newItem.className = 'checklist-item';
                            newItem.innerHTML = `
                                <input type="text" name="checklist_item[]" placeholder="Add checklist item">
                                <button type="button" class="remove-item" style="display: none;">×</button>
                            `;
                            editChecklistContainer.appendChild(newItem);
                        }

                        // Show/hide remove buttons based on item count
                        const removeButtons = editChecklistContainer.querySelectorAll('.remove-item');
                        removeButtons.forEach(button => {
                            button.style.display = removeButtons.length > 1 ? 'block' : 'none';
                        });

                        // Open the edit modal
                        editTaskModal.style.display = "block";

                    } else {
                        alert('Error loading task details for editing: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching task details for editing:', error);
                    alert('An error occurred while loading task details for editing.');
                });
        }

        // Add event listeners to edit buttons (in task list and preview modal)
        document.querySelectorAll('.edit-task-btn').forEach(button => {
            button.addEventListener('click', function(event) {
                event.stopPropagation(); // Prevent opening the preview modal when clicking edit in task list
                const taskId = this.getAttribute('data-task-id');
                if (taskId) {
                    // Close preview modal if open
                    previewModal.style.display = "none";
                    // Open the edit modal and load task data
                    openEditTaskModal(taskId);
                }
            });
        });

        // Add checklist functionality for edit modal
        addEditChecklistItemBtn.addEventListener('click', function() {
            const container = document.getElementById('editChecklistContainer');
            const newItem = document.createElement('div');
            newItem.className = 'checklist-item';
            newItem.innerHTML = `
                <input type="hidden" name="checklist_id[]" value="">
                <input type="text" name="checklist_item[]" placeholder="Add checklist item">
                <button type="button" class="remove-item">×</button>
            `;
            container.appendChild(newItem);

            // Show remove buttons if there's more than one item
            const removeButtons = container.querySelectorAll('.remove-item');
            removeButtons.forEach(button => {
                button.style.display = removeButtons.length > 1 ? 'block' : 'none';
            });
            // Add event listener to the new remove button
            newItem.querySelector('.remove-item').addEventListener('click', function() {
                this.closest('.checklist-item').remove();
                // Hide remove button if only one item remains
                const remainingRemoveButtons = container.querySelectorAll('.remove-item');
                if (remainingRemoveButtons.length === 1) {
                    remainingRemoveButtons[0].style.display = 'none';
                }
            });
        });

        // Remove checklist item from edit modal
        editChecklistContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-item')) {
                e.target.closest('.checklist-item').remove();
                // Hide remove button if only one item remains
                const removeButtons = this.querySelectorAll('.remove-item');
                if (removeButtons.length === 1) {
                    removeButtons[0].style.display = 'none';
                }
            }
        });

        // Close Edit Task modal when clicking the X
        editCloseBtn.onclick = function() {
            editTaskModal.style.display = "none";
        }

        // When the user clicks anywhere outside of the modals, close the respective modal
        window.onclick = function(event) {
            const modal = document.getElementById('taskModal'); // Create Task Modal
            const previewModal = document.getElementById("taskPreviewModal");
            const editTaskModal = document.getElementById("editTaskModal"); // Edit Task Modal

            if (event.target == modal) {
                modal.style.display = "none";
            }
            if (event.target == previewModal) {
                previewModal.style.display = "none";
            }
            if (event.target == editTaskModal) {
                editTaskModal.style.display = "none";
            }
        }

        // Handle form submission for create task form (Update existing taskForm handler)
        document.getElementById('taskForm').onsubmit = function(e) {
            e.preventDefault();
            // ... existing form submission logic for create task ...
            // Get form data
            const formData = new FormData(this);

            // Filter out empty checklist items
            const checklistItems = formData.getAll('checklist[]').filter(item => item.trim() !== '');
            formData.delete('checklist[]');
            checklistItems.forEach(item => formData.append('checklist[]', item));

            // Send AJAX request
            fetch('process_task.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        // Close modal
                        modal.style.display = "none";
                        // Reload page to show new task
                        window.location.reload();
                    } else {
                        alert(data.message || 'Error creating task');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while creating the task.');
                });
        };

        // Handle form submission for edit task form
        editTaskForm.onsubmit = function(e) {
            e.preventDefault();

            // Get form data
            const formData = new FormData(this);

            // Filter out empty checklist items and prepare for submission
            const checklistItems = [];
            const checklistIds = [];
            document.querySelectorAll('#editChecklistContainer .checklist-item').forEach(itemDiv => {
                const itemIdInput = itemDiv.querySelector('input[name="checklist_id[]"]');
                const itemTextInput = itemDiv.querySelector('input[name^="checklist_item"]');
                if (itemTextInput && itemTextInput.value.trim() !== '') {
                    if (itemIdInput) {
                        checklistIds.push(itemIdInput.value);
                    } else {
                        checklistIds.push(''); // Placeholder for new items
                    }
                    checklistItems.push(itemTextInput.value.trim());
                }
            });

            formData.delete('checklist_id[]');
            formData.delete('checklist_item[]');
            checklistIds.forEach(id => formData.append('checklist_id[]', id));
            checklistItems.forEach(item => formData.append('checklist_item[]', item));

            // Send AJAX request to process_edit_task.php
            fetch('process_edit_task.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        // Close modal
                        editTaskModal.style.display = "none";
                        // Reload page to show updated task
                        window.location.reload();
                    } else {
                        alert(data.message || 'Error updating task');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the task.');
                });
        };

        // Add checklist functionality for create modal
        document.getElementById('add-checklist-item').addEventListener('click', function() {
            console.log('Add item button clicked for create modal');
            const container = document.getElementById('checklist-container');
            const newItem = document.createElement('div');
            newItem.className = 'checklist-item';
            newItem.innerHTML = `
                <input type="text" name="checklist[]" placeholder="Add checklist item">
                <button type="button" class="remove-item">×</button>
            `;
            container.appendChild(newItem);

            // Show remove buttons if there's more than one item
            const removeButtons = container.querySelectorAll('.remove-item');
            removeButtons.forEach(button => {
                button.style.display = removeButtons.length > 1 ? 'block' : 'none';
            });
            // Add event listener to the new remove button
            newItem.querySelector('.remove-item').addEventListener('click', function() {
                this.closest('.checklist-item').remove();
                // Hide remove button if only one item remains
                const remainingRemoveButtons = container.querySelectorAll('.remove-item');
                if (remainingRemoveButtons.length === 1) {
                    remainingRemoveButtons[0].style.display = 'none';
                }
            });
        });

        // Remove checklist item from create modal
        document.getElementById('checklist-container').addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-item')) {
                e.target.closest('.checklist-item').remove();
                // Hide remove button if only one item remains
                const removeButtons = this.querySelectorAll('.remove-item');
                if (removeButtons.length === 1) {
                    removeButtons[0].style.display = 'none';
                }
            }
        });

        // Add this to your existing JavaScript code
        document.addEventListener('DOMContentLoaded', function() {
            // Handle task status updates
            const taskCheckboxes = document.querySelectorAll('.task-checkbox input[type="checkbox"]');
            taskCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const taskId = this.getAttribute('data-task-id');
                    const newStatus = this.checked ? 'completed' : 'pending';

                    // Send AJAX request to update task status
                    const formData = new FormData();
                    formData.append('task_id', taskId);
                    formData.append('status', newStatus);

                    fetch('update_task_status.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update the task status in the UI
                                const taskItem = this.closest('.task-item');
                                const statusElement = taskItem.querySelector('.task-status');
                                if (statusElement) {
                                    statusElement.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                                    statusElement.className = 'task-status status-' + newStatus;
                                }
                            } else {
                                // Revert checkbox if update failed
                                this.checked = !this.checked;
                                alert('Failed to update task status: ' + data.message);
                            }
                        })
                        .catch(error => {
                            // Revert checkbox if request failed
                            this.checked = !this.checked;
                            alert('Error updating task status: ' + error.message);
                        });
                });
            });

            // Handle checklist item status updates
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('checklist-checkbox')) {
                    const checklistId = e.target.getAttribute('data-checklist-id');
                    const taskId = e.target.getAttribute('data-task-id');
                    const isCompleted = e.target.checked;
                    const checklistText = e.target.nextElementSibling;

                    // Update text style immediately for better UX
                    if (isCompleted) {
                        checklistText.style.textDecoration = 'line-through';
                        checklistText.style.color = '#95a5a6';
                    } else {
                        checklistText.style.textDecoration = 'none';
                        checklistText.style.color = '#2c3e50';
                    }

                    // Send AJAX request to update checklist item status
                    const formData = new FormData();
                    formData.append('checklist_id', checklistId);
                    formData.append('task_id', taskId);
                    formData.append('is_completed', isCompleted ? 1 : 0);

                    fetch('update_checklist_status.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                // Revert checkbox and text style if update failed
                                e.target.checked = !isCompleted;
                                if (!isCompleted) {
                                    checklistText.style.textDecoration = 'line-through';
                                    checklistText.style.color = '#95a5a6';
                                } else {
                                    checklistText.style.textDecoration = 'none';
                                    checklistText.style.color = '#2c3e50';
                                }
                                alert('Failed to update checklist item: ' + data.message);
                            }
                        })
                        .catch(error => {
                            // Revert checkbox and text style if request failed
                            e.target.checked = !isCompleted;
                            if (!isCompleted) {
                                checklistText.style.textDecoration = 'line-through';
                                checklistText.style.color = '#95a5a6';
                            } else {
                                checklistText.style.textDecoration = 'none';
                                checklistText.style.color = '#2c3e50';
                            }
                            alert('Error updating checklist item: ' + error.message);
                        });
                }
            });
        });
    </script>
</body>

</html>