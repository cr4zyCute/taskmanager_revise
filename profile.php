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

// Fetch user profile data
$user_id = $_SESSION["id"];
$sql = "SELECT username, email, full_name, phone, address, profile_picture FROM users WHERE id = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Fetch task statistics
$stats_sql = "SELECT 
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
    SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue_tasks
FROM tasks 
WHERE user_id = ? AND is_deleted = 0";

$stats_stmt = mysqli_prepare($link, $stats_sql);
mysqli_stmt_bind_param($stats_stmt, "i", $user_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);
mysqli_stmt_close($stats_stmt);

// Removed Fetch recent tasks
// $recent_tasks_sql = "SELECT * FROM tasks WHERE user_id = ? AND is_deleted = 0 ORDER BY created_at DESC LIMIT 5";
// $recent_tasks_stmt = mysqli_prepare($link, $recent_tasks_sql);
// mysqli_stmt_bind_param($recent_tasks_stmt, "i", $user_id);
// mysqli_stmt_execute($recent_tasks_stmt);
// $recent_tasks_result = mysqli_stmt_get_result($recent_tasks_stmt);
// $recent_tasks = mysqli_fetch_all($recent_tasks_result, MYSQLI_ASSOC);
// mysqli_stmt_close($recent_tasks_stmt);
mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager - Profile</title>
    <link rel="icon" href="./images/logo.png" type="image/png">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 200px;
            background-color: #2c3e50;
            color: white;
            padding: 15px;
            overflow-y: auto;
            /* Add scrolling for sidebar if content overflows */
        }

        .main-content {
            flex: 1;
            background-color: #f5f6fa;
            padding: 10px;
            display: grid;
            /* Use grid for layout */
            grid-template-columns: minmax(300px, 400px) 1fr;
            /* Profile column fixed/minmax, Stats/Tasks column takes rest */
            grid-template-rows: auto auto auto 1fr;
            /* Header, Profile/Stats row, Progress bar, Recent Tasks */
            gap: 10px;
            /* Gap between grid items */
            height: 100%;
            /* Occupy full height of container */
            overflow: hidden;
            /* Prevent overall main content scrolling */
        }

        .header {
            grid-column: 1 / -1;
            /* Span across all columns */
            grid-row: 1;
            /* Place in the first row */
            background-color: white;
            padding: 8px 15px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
            /* Remove bottom margin */
            flex-shrink: 0;
            /* Prevent header from shrinking */
        }

        .header h2 {
            font-size: 1.2em;
            margin: 0;
        }

        /* Profile Card */
        .profile-card {
            grid-column: 1;
            /* Place in the first column */
            grid-row: 2;
            /* Place in the second row */
            background-color: white;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            /* Hide overflow */
        }

        .profile-header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            flex-shrink: 0;
            /* Prevent shrinking */
        }

        .profile-picture {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin: 0 auto 8px auto;
            /* Center image and add margin below */
            border: 2px solid #3498db;
            object-fit: cover;
            /* Ensure image covers area */
        }

        .profile-header h2 {
            font-size: 1.1em;
            margin: 5px 0;
        }

        .username {
            font-size: 0.8em;
            color: #7f8c8d;
        }

        .profile-details {
            flex: 1;
            /* Allow details to take available space */
            overflow-y: auto;
            /* Allow scrolling if details overflow */
            padding-right: 5px;
            /* Add padding for scrollbar */
        }

        .detail-item {
            display: flex;
            align-items: center;
            padding: 6px;
            margin-bottom: 6px;
            background-color: #f8f9fa;
            border-radius: 4px;
            font-size: 0.85em;
        }

        .detail-item i {
            margin-right: 8px;
            color: #3498db;
            font-size: 1em;
            width: 16px;
            text-align: center;
            flex-shrink: 0;
            /* Prevent icon from shrinking */
        }

        .profile-actions {
            margin-top: 10px;
            text-align: center;
            flex-shrink: 0;
            /* Prevent shrinking */
        }

        .edit-profile-btn {
            display: inline-block;
            padding: 6px 15px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.85em;
            transition: background-color 0.2s;
        }

        .edit-profile-btn:hover {
            background-color: #2980b9;
        }

        /* Task Statistics Section */
        .task-statistics {
            grid-column: 2;
            /* Place in the second column */
            grid-row: 2;
            /* Place in the second row */
            background-color: white;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            /* Hide overflow */
        }

        .task-statistics h3 {
            font-size: 1em;
            margin: 0 0 10px 0;
            /* Add margin below heading */
            color: #2c3e50;
            flex-shrink: 0;
            /* Prevent shrinking */
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-top: 0;
            /* Remove top margin */
            flex-shrink: 0;
            /* Prevent shrinking */
        }

        .stat-card {
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 6px;
            display: flex;
            align-items: center;
        }

        .stat-card i {
            font-size: 1.1em;
            color: #3498db;
            margin-right: 8px;
            width: 20px;
            text-align: center;
            flex-shrink: 0;
            /* Prevent icon from shrinking */
        }

        .stat-info {
            display: flex;
            flex-direction: column;
        }

        .stat-value {
            font-size: 1.1em;
            font-weight: bold;
            color: #2c3e50;
        }

        .stat-label {
            font-size: 0.75em;
            color: #7f8c8d;
        }

        /* Style for the chart container */
        .chart-container {
            position: relative;
            height: 150px;
            /* Adjust height as needed */
            margin-top: 10px;
            flex: 1;
            /* Allow chart container to grow */
            overflow: hidden;
            /* Hide overflow */
        }

        /* Style for the overall progress bar */
        .overall-progress {
            grid-column: 1 / -1;
            /* Span across all columns */
            grid-row: 3;
            /* Place in the third row */
            margin-top: 0;
            /* Remove top margin */
            margin-bottom: 10px;
            /* Add bottom margin */
            height: 15px;
            background-color: #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.2);
            flex-shrink: 0;
            /* Prevent shrinking */
        }

        .progress-bar {
            height: 100%;
            width: 0%;
            /* Initial width */
            background-color: #2ecc71;
            /* Green */
            transition: width 0.5s ease-in-out;
            text-align: center;
            line-height: 15px;
            /* Center text vertically */
            color: white;
            font-size: 0.7em;
            font-weight: bold;
        }

        /* Ensure empty state also respects flex/grid layout */
        .empty-state {
            text-align: center;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            flex: 1;
            /* Allow to grow */
            display: flex;
            /* Use flex to center content */
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .empty-state i {
            font-size: 2em;
            /* Smaller icon for compact view */
            color: #bdc3c7;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #7f8c8d;
            margin: 0;
            font-size: 0.9em;
        }

        /* Add smooth scrolling */
        html {
            scroll-behavior: smooth;
        }

        @media (max-width: 1200px) {
            .main-content {
                grid-template-columns: 1fr;
                /* Stack columns on smaller screens */
                grid-template-rows: auto auto auto 1fr;
                /* Header, profile, stats, then recent tasks */
                height: auto;
                /* Allow height to adjust */
                overflow-y: visible;
                /* Allow normal scrolling */
                display: flex;
                /* Revert to flex for stacking */
                flex-direction: column;
            }

            .profile-card {
                grid-column: auto;
                /* Remove grid positioning */
                grid-row: auto;
                /* Remove grid positioning */
                flex-shrink: 0;
                /* Prevent shrinking */
                margin-bottom: 10px;
                /* Add margin below section */
            }

            .task-statistics {
                grid-column: auto;
                /* Remove grid positioning */
                grid-row: auto;
                /* Remove grid positioning */
                flex-shrink: 0;
                /* Prevent shrinking */
                margin-bottom: 10px;
                /* Add margin below section */
            }

            .overall-progress {
                grid-column: auto;
                /* Remove grid positioning */
                grid-row: auto;
                /* Remove grid positioning */
                flex-shrink: 0;
                /* Prevent shrinking */
                margin-bottom: 10px;
                /* Add margin below section */
            }

            .profile-details,
            .stats-grid

            /* Removed .task-list */
                {
                overflow-y: visible;
                /* Allow normal scrolling */
                padding-right: 0;
                /* Remove padding */
            }

            /* Removed Recent Tasks List Display Change */

            /* Removed Recent Task Item Display Change */
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                padding: 8px;
                height: auto;
                /* Allow sidebar to take necessary height on mobile */
                overflow-y: visible;
                /* Allow normal scrolling */
            }

            .main-content {
                height: auto;
                /* Allow main content to take necessary height on mobile */
                overflow-y: visible;
                /* Allow normal scrolling on mobile */
                grid-template-columns: 1fr;
                /* Ensure single column layout on very small screens */
                gap: 8px;
                /* Reduce gap */
            }

            .header {
                padding: 6px 10px;
                /* Reduce header padding */
            }

            .profile-card,
            .task-statistics

            /* Removed .recent-tasks-section */
                {
                padding: 10px;
                /* Reduce section/card padding */
                margin-bottom: 8px;
                /* Adjust margin */
            }

            .task-statistics:last-child,
            .profile-card:last-child {
                margin-bottom: 8px;
                /* Ensure margin for last items in stacked layout */
            }

            .overall-progress {
                margin-top: 8px;
                margin-bottom: 8px;
            }

            /* Removed Task Item Media Query */
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="sidebar">
            <div class="profile">
                <img src="<?php echo !empty($user_data['profile_picture']) ? htmlspecialchars($user_data['profile_picture']) : 'placeholder.png'; ?>" alt="User Profile">

            </div>
            <nav class="navigation">
                <ul>
                    <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>

                </ul>
            </nav>
        </div>
        <div class="main-content">
            <header class="header">
                <div class="header-left">
                    <h2>Profile</h2>
                </div>
                <div class="header-right">

                </div>
            </header>

            <!-- Profile Card -->
            <div class="profile-card">
                <div class="profile-header">
                    <img src="<?php echo !empty($user_data['profile_picture']) ? htmlspecialchars($user_data['profile_picture']) : 'placeholder.png'; ?>" alt="Profile Picture" class="profile-picture">
                    <h2><?php echo htmlspecialchars($user_data['full_name'] ?? $user_data['username']); ?></h2>
                    <p class="username">@<?php echo htmlspecialchars($user_data['username']); ?></p>
                </div>
                <div class="profile-details">
                    <div class="detail-item">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo htmlspecialchars($user_data['email']); ?></span>
                    </div>
                    <?php if (!empty($user_data['phone'])): ?>
                        <div class="detail-item">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($user_data['phone']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($user_data['address'])): ?>
                        <div class="detail-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($user_data['address']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="profile-actions">
                    <a href="edit_profile.php" class="edit-profile-btn">Edit Profile</a>
                </div>
            </div>

            <!-- Task Statistics Section -->
            <div class="task-statistics">
                <h3>Task Statistics</h3>
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-tasks"></i>
                        <div class="stat-info">
                            <span class="stat-value"><?php echo $stats['total_tasks']; ?></span>
                            <span class="stat-label">Total Tasks</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-check-circle"></i>
                        <div class="stat-info">
                            <span class="stat-value"><?php echo $stats['completed_tasks']; ?></span>
                            <span class="stat-label">Completed</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-clock"></i>
                        <div class="stat-info">
                            <span class="stat-value"><?php echo $stats['pending_tasks']; ?></span>
                            <span class="stat-label">Pending</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-exclamation-circle"></i>
                        <div class="stat-info">
                            <span class="stat-value"><?php echo $stats['overdue_tasks']; ?></span>
                            <span class="stat-label">Overdue</span>
                        </div>
                    </div>
                </div>
                <!-- Canvas for the chart -->
                <div class="chart-container">
                    <canvas id="taskStatusChart"></canvas>
                </div>
            </div>

            <!-- Overall Progress Bar -->
            <div class="overall-progress">
                <div class="progress-bar" id="overallProgressBar"></div>
            </div>
        </div>
    </div>
</body>
<script>
    // Get task statistics data from PHP
    const taskStats = <?php echo json_encode($stats); ?>;

    // Get the canvas element
    const ctx = document.getElementById('taskStatusChart').getContext('2d');

    // Create the pie chart
    const taskStatusChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Completed', 'Pending', 'Overdue'],
            datasets: [{
                label: 'Task Status',
                data: [taskStats.completed_tasks, taskStats.pending_tasks, taskStats.overdue_tasks],
                backgroundColor: [
                    '#2ecc71', // Completed (Green)
                    '#f1c40f', // Pending (Yellow)
                    '#e74c3c' // Overdue (Red)
                ],
                borderColor: [
                    '#ffffff',
                    '#ffffff',
                    '#ffffff'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 10
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(tooltipItem) {
                            const label = tooltipItem.label || '';
                            const value = tooltipItem.raw || 0;
                            // Calculate total from the stats object directly
                            const total = (taskStats.total_tasks || 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // Update the overall progress bar
    const progressBar = document.getElementById('overallProgressBar');
    const completedTasks = taskStats.completed_tasks || 0;
    const totalTasks = taskStats.total_tasks || 0;
    const completionPercentage = totalTasks > 0 ? (completedTasks / totalTasks) * 100 : 0;

    progressBar.style.width = completionPercentage.toFixed(1) + '%';
    progressBar.innerText = completionPercentage.toFixed(0) + '%'; // Display whole number percentage
</script>

</html>