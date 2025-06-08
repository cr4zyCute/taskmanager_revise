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

// Fetch deleted tasks
$tasks_sql = "SELECT * FROM tasks WHERE user_id = ? AND is_deleted = 1 ORDER BY deleted_at DESC";
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
    <title>Task Manager - Trash</title>
    <link rel="icon" href="./images/logo.png" type="image/png">
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

        .trash-container {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .trash-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .empty-trash-btn {
            background-color: #e74c3c;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }

        .empty-trash-btn:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }

        .empty-trash-btn i {
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

        .task-content {
            flex: 1;
        }

        .task-title {
            margin: 0 0 5px;
            color: #2c3e50;
            font-size: 16px;
        }

        .task-deleted-date {
            font-size: 0.9em;
            color: #7f8c8d;
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

        .restore-btn:hover {
            color: #2ecc71;
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
                    <li class="active"><a href="trash.php"><i class="fas fa-trash"></i> Trash</a></li>
                </ul>
            </nav>
        </div>
        <div class="main-content">
            <header class="header">
                <div class="header-left">
                    <h2>Trash</h2>
                </div>
                <!-- <div class="header-right">
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
                </div> -->
            </header>
            <div class="trash-container">
                <div class="trash-header">
                    <h3>Deleted Tasks</h3>
                    <button class="empty-trash-btn">
                        <i class="fas fa-trash"></i> Empty Trash
                    </button>
                </div>
                <?php if (!empty($tasks)): ?>
                    <ul class="task-list">
                        <?php foreach ($tasks as $task): ?>
                            <li class="task-item">
                                <div class="task-content">
                                    <h4 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h4>
                                    <div class="task-deleted-date">
                                        Deleted: <?php echo date('M d, Y', strtotime($task['deleted_at'])); ?>
                                    </div>
                                </div>
                                <div class="task-actions">
                                    <button class="task-action-btn restore-btn" title="Restore" data-task-id="<?php echo $task['id']; ?>">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    <button class="task-action-btn delete-btn" title="Delete Permanently" data-task-id="<?php echo $task['id']; ?>">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-trash"></i>
                        <p>No deleted tasks found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Add event listeners to restore buttons
        document.querySelectorAll('.restore-btn').forEach(button => {
            button.addEventListener('click', function() {
                const taskId = this.getAttribute('data-task-id'); // Assuming you add data-task-id to the button
                if (taskId) {
                    restoreTask(taskId);
                }
            });
        });

        // Function to handle task restoration
        function restoreTask(taskId) {
            if (confirm("Are you sure you want to restore this task?")) {
                fetch('restore_task.php', {
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
                            // Remove the task item from the trash list
                            document.querySelector('.task-item .restore-btn[data-task-id="' + taskId + '"]').closest('.task-item').remove();
                            // Optionally, provide feedback to the user that it's restored (e.g., redirect to tasks page or show a message)
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error restoring task:', error);
                        alert('An error occurred while restoring the task.');
                    });
            }
        }

        // Add event listeners to delete permanently buttons
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const taskId = this.getAttribute('data-task-id'); // Assuming you add data-task-id to the button
                if (taskId) {
                    deleteTaskPermanently(taskId);
                }
            });
        });

        // Function to handle permanent task deletion
        function deleteTaskPermanently(taskId) {
            if (confirm("Are you sure you want to permanently delete this task? This action cannot be undone.")) {
                fetch('permanent_delete_task.php', { // We will create this file next
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
                            // Remove the task item from the trash list
                            document.querySelector('.task-item .delete-btn[data-task-id="' + taskId + '"]').closest('.task-item').remove();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error permanently deleting task:', error);
                        alert('An error occurred while permanently deleting the task.');
                    });
            }
        }

        // Toggle user dropdown menu (assuming you have this in trash.php as well)
        const userMenuBtn = document.getElementById('userMenuBtn'); // Make sure your user menu button has this ID
        const userDropdown = document.getElementById('userDropdown'); // Make sure your user dropdown has this ID

        if (userMenuBtn && userDropdown) { // Check if elements exist
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
        }
    </script>
</body>

</html>