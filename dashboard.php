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
$tasks_sql = "SELECT * FROM tasks WHERE user_id = ? AND is_deleted = 0 ORDER BY due_date ASC LIMIT 5";
$tasks_stmt = mysqli_prepare($link, $tasks_sql);
mysqli_stmt_bind_param($tasks_stmt, "i", $user_id);
mysqli_stmt_execute($tasks_stmt);
$tasks_result = mysqli_stmt_get_result($tasks_stmt);
$tasks = mysqli_fetch_all($tasks_result, MYSQLI_ASSOC);
mysqli_stmt_close($tasks_stmt);

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

// Fetch tasks for calendar
$calendar_tasks_sql = "SELECT title, due_date, status FROM tasks WHERE user_id = ? AND is_deleted = 0 AND due_date IS NOT NULL";
$calendar_tasks_stmt = mysqli_prepare($link, $calendar_tasks_sql);
mysqli_stmt_bind_param($calendar_tasks_stmt, "i", $user_id);
mysqli_stmt_execute($calendar_tasks_stmt);
$calendar_tasks_result = mysqli_stmt_get_result($calendar_tasks_stmt);
$calendar_tasks = [];
while ($task = mysqli_fetch_assoc($calendar_tasks_result)) {
    $day = date('j', strtotime($task['due_date']));
    if (!isset($calendar_tasks[$day])) {
        $calendar_tasks[$day] = [];
    }
    $calendar_tasks[$day][] = [
        'title' => htmlspecialchars($task['title']),
        'status' => $task['status']
    ];
}
mysqli_stmt_close($calendar_tasks_stmt);

// --- Check for Upcoming Deadlines (Exactly 2 Days Away) and Create Notifications ---
$upcoming_deadline_sql = "SELECT id, title, due_date FROM tasks 
                          WHERE user_id = ? 
                          AND is_deleted = 0 
                          AND status != 'completed' 
                          AND due_date = DATE_ADD(CURDATE(), INTERVAL 2 DAY)";

if ($upcoming_deadline_stmt = mysqli_prepare($link, $upcoming_deadline_sql)) {
    mysqli_stmt_bind_param($upcoming_deadline_stmt, "i", $user_id);
    mysqli_stmt_execute($upcoming_deadline_stmt);
    $upcoming_deadline_result = mysqli_stmt_get_result($upcoming_deadline_stmt);

    while ($task = mysqli_fetch_assoc($upcoming_deadline_result)) {
        $task_id = $task['id'];
        $task_title = htmlspecialchars($task['title']);
        $due_date = $task['due_date'];

        // Check if an upcoming deadline notification already exists for this task
        $existing_notification_sql = "SELECT id FROM notifications 
                                      WHERE user_id = ? 
                                      AND task_id = ? 
                                      AND type = 'upcoming_deadline' 
                                      LIMIT 1";

        if ($existing_notification_stmt = mysqli_prepare($link, $existing_notification_sql)) {
            mysqli_stmt_bind_param($existing_notification_stmt, "ii", $user_id, $task_id);
            mysqli_stmt_execute($existing_notification_stmt);
            mysqli_stmt_store_result($existing_notification_stmt);

            if (mysqli_stmt_num_rows($existing_notification_stmt) == 0) {
                // No existing notification found, create a new one
                $notification_type = 'upcoming_deadline';
                $notification_message = "Task '" . $task_title . "' has an upcoming deadline in 2 days on " . $due_date . ".";
                $insert_notification_sql = "INSERT INTO notifications (user_id, task_id, type, message) VALUES (?, ?, ?, ?)";
                if ($insert_notification_stmt = mysqli_prepare($link, $insert_notification_sql)) {
                    mysqli_stmt_bind_param($insert_notification_stmt, "iiss", $user_id, $task_id, $notification_type, $notification_message);
                    mysqli_stmt_execute($insert_notification_stmt);
                    mysqli_stmt_close($insert_notification_stmt);
                }
            }
            mysqli_stmt_close($existing_notification_stmt);
        }
    }
    mysqli_stmt_close($upcoming_deadline_stmt);
}
// --- End of Upcoming Deadlines Check ---

// --- Check for Overdue Tasks and Create Notifications ---
$overdue_tasks_sql = "SELECT id, title, due_date FROM tasks 
                      WHERE user_id = ? 
                      AND is_deleted = 0 
                      AND status != 'completed' 
                      AND due_date IS NOT NULL 
                      AND due_date < CURDATE()";

if ($overdue_tasks_stmt = mysqli_prepare($link, $overdue_tasks_sql)) {
    mysqli_stmt_bind_param($overdue_tasks_stmt, "i", $user_id);
    mysqli_stmt_execute($overdue_tasks_stmt);
    $overdue_tasks_result = mysqli_stmt_get_result($overdue_tasks_stmt);

    while ($task = mysqli_fetch_assoc($overdue_tasks_result)) {
        $task_id = $task['id'];
        $task_title = htmlspecialchars($task['title']);
        $due_date = $task['due_date'];

        // Check if an overdue notification already exists for this task
        $existing_notification_sql = "SELECT id FROM notifications 
                                      WHERE user_id = ? 
                                      AND task_id = ? 
                                      AND type = 'overdue' 
                                      LIMIT 1";

        if ($existing_notification_stmt = mysqli_prepare($link, $existing_notification_sql)) {
            mysqli_stmt_bind_param($existing_notification_stmt, "ii", $user_id, $task_id);
            mysqli_stmt_execute($existing_notification_stmt);
            mysqli_stmt_store_result($existing_notification_stmt);

            if (mysqli_stmt_num_rows($existing_notification_stmt) == 0) {
                // No existing notification found, create a new one
                $notification_type = 'overdue';
                $notification_message = "Task '" . $task_title . "' is overdue since " . $due_date . ".";
                $insert_notification_sql = "INSERT INTO notifications (user_id, task_id, type, message) VALUES (?, ?, ?, ?)";
                if ($insert_notification_stmt = mysqli_prepare($link, $insert_notification_sql)) {
                    mysqli_stmt_bind_param($insert_notification_stmt, "iiss", $user_id, $task_id, $notification_type, $notification_message);
                    mysqli_stmt_execute($insert_notification_stmt);
                    mysqli_stmt_close($insert_notification_stmt);
                }
            }
            mysqli_stmt_close($existing_notification_stmt);
        }
    }
    mysqli_stmt_close($overdue_tasks_stmt);
}
// --- End of Overdue Tasks Check ---

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager - Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="./images/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
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
            margin-left: 270px;
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
            border: 2px solid #3498db;
            transition: transform 0.2s ease;
        }

        .user-menu img:hover {
            transform: scale(1.05);
        }

        .dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background-color: transparent;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            border-radius: 12px;
            padding: 8px 0;
            min-width: 200px;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .dropdown.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .dropdown::before {
            content: '';
            position: absolute;
            top: -8px;
            right: 20px;
            width: 16px;
            height: 16px;
            background-color: transparent;
            transform: rotate(45deg);
            box-shadow: -2px -2px 5px rgba(0, 0, 0, 0.05);
        }

        .dropdown ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .dropdown li {
            margin: 4px 0;
        }

        .dropdown li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #2c3e50;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.95em;
            border-radius: 8px;
            margin: 0 8px;
        }

        .dropdown li a:hover {
            color: #3498db;
            transform: translateX(5px);
        }

        .dropdown li a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 1.1em;
            color: rgb(0, 0, 0);
            transition: transform 0.2s ease;
            background-color: transparent;
        }

        .dropdown li a:hover i {
            transform: scale(1.1);
        }

        .dropdown li:last-child a {
            color: #e74c3c;
        }

        .dropdown li:last-child a i {
            color: #e74c3c;
        }

        .dropdown li:last-child a:hover {
            color: #c0392b;
        }

        .dropdown li:last-child a:hover i {
            color: #c0392b;
        }

        .dropdown li:not(:last-child)::after {
            content: '';
            display: block;
            height: 1px;
            background-color: #f0f0f0;
            margin: 4px 0;
        }

        .dashboard-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 20px;
        }

        .analysis-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .task-analysis {
            margin-top: 0;
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .calendar {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .recent-tasks-section {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .dashboard-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-title {
            margin: 0;
            color: #2c3e50;
            font-size: 1.2em;
        }

        .view-all {
            color: #3498db;
            text-decoration: none;
            font-size: 0.9em;
            transition: color 0.3s;
        }

        .view-all:hover {
            color: #2980b9;
        }

        .task-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .task-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .task-item:last-child {
            border-bottom: none;
        }

        .task-checkbox {
            margin-right: 15px;
        }

        .task-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: not-allowed;
            opacity: 0.6;
            pointer-events: none;
            accent-color: #2ecc71;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            border: 2px solid #ccc;
            border-radius: 3px;
            position: relative;
        }

        .task-checkbox input[type="checkbox"]:checked {
            background-color: #2ecc71;
            border-color: #2ecc71;
        }

        .task-checkbox input[type="checkbox"]:checked::after {
            content: '✓';
            position: absolute;
            color: white;
            font-size: 12px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .task-content {
            flex: 1;
        }

        .task-title {
            margin: 0 0 5px;
            color: #2c3e50;
            font-size: 14px;
        }

        .task-details {
            display: flex;
            gap: 15px;
            font-size: 0.8em;
            color: #7f8c8d;
        }

        .task-detail {
            display: flex;
            align-items: center;
        }

        .task-detail i {
            margin-right: 5px;
        }

        .task-status {
            padding: 2px 6px;
            border-radius: 10px;
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

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }

        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9em;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .calendar-day:hover {
            background-color: #f5f6fa;
        }

        .calendar-day.today {
            background-color: #3498db;
            color: white;
        }

        .calendar-day.has-tasks {
            position: relative;
        }

        .calendar-day.has-tasks::after {
            content: '';
            position: absolute;
            bottom: 2px;
            width: 4px;
            height: 4px;
            background-color: #3498db;
            border-radius: 50%;
        }

        .calendar-day.today.has-tasks::after {
            background-color: white;
        }

        .empty-state {
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 24px;
            margin-bottom: 10px;
            color: #bdc3c7;
        }

        .empty-state p {
            margin: 0;
            font-size: 14px;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .analytics-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
        }

        .analytics-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .analytics-icon i {
            color: white;
            font-size: 24px;
        }

        .analytics-info h3 {
            margin: 0;
            font-size: 14px;
            color: #7f8c8d;
        }

        .analytics-info p {
            margin: 5px 0 0;
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }

        .task-tooltip {
            display: none;
            position: absolute;
            background-color: #2c3e50;
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            z-index: 1000;
            min-width: 250px;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            left: 50%;
            transform: translateX(-50%);
            margin-top: 8px;
        }

        .task-tooltip::before {
            content: '';
            position: absolute;
            top: -6px;
            left: 50%;
            transform: translateX(-50%);
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-bottom: 6px solid #2c3e50;
        }

        .calendar-day.has-tasks:hover .task-tooltip {
            display: block;
        }

        .task-tooltip-item {
            display: flex;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .task-tooltip-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .task-tooltip-item:first-child {
            padding-top: 0;
        }

        .task-tooltip-item .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 10px;
            flex-shrink: 0;
        }

        .task-tooltip-item.completed .status-indicator {
            background-color: #2ecc71;
        }

        .task-tooltip-item.pending .status-indicator {
            background-color: #f1c40f;
        }

        .task-tooltip-item.completed {
            opacity: 0.7;
            text-decoration: line-through;
        }

        .task-tooltip-item .task-title {
            flex: 1;
            word-break: break-word;
            color: white;
        }

        /* Notification styles */
        .notification-icon {
            position: relative;
            margin-right: 20px;
            cursor: pointer;
            color: #2c3e50;
            font-size: 1.2em;
            display: flex;
            align-items: center;
        }

        .notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 2;
            padding: 0;
            z-index: 1;
            box-sizing: border-box;
            text-align: center;
            transform: translate(0, 0);
        }

        .notification-badge:empty {
            display: none;
        }

        .notification-sidebar {
            position: fixed;
            top: 0;
            right: -300px;
            /* Hidden by default */
            width: 300px;
            height: 100%;
            background-color: #ffffff;
            box-shadow: -2px 0 5px rgba(0, 0, 0, 0.1);
            transition: right 0.3s ease-in-out;
            z-index: 1100;
            display: flex;
            flex-direction: column;
        }

        .notification-sidebar.open {
            right: 0;
        }

        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            background-color: #f5f6fa;
        }

        .sidebar-header h3 {
            margin: 0;
            font-size: 1.1em;
            color: #2c3e50;
        }

        .close-sidebar {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: #7f8c8d;
        }

        .close-sidebar:hover {
            color: #333;
        }

        .sidebar-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        .sidebar-content p {
            color: #7f8c8d;
            text-align: center;
            margin-top: 20px;
        }

        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out;
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .notification-item h4 {
            margin: 0;
            font-size: 1em;
            color: #2c3e50;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notification-item h4 i {
            color: #3498db;
        }

        .notification-item p {
            margin: 0;
            font-size: 0.9em;
            color: #555;
            line-height: 1.4;
            padding-right: 30px;
            /* Make space for the delete button */
        }

        .notification-item small {
            font-size: 0.8em;
            color: #7f8c8d;
            display: block;
        }

        .notification-item .delete-notification {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            padding: 5px;
            opacity: 1;
            transition: color 0.2s ease-in-out;
        }

        .notification-item .delete-notification:hover {
            color: #c0392b;
        }

        .notification-item.unread {
            background-color: #f8f9fa;
            border-left: 4px solid #3498db;
        }

        .notification-item.read {
            opacity: 0.8;
        }

        /* Add icons for different notification types */
        .notification-item h4[data-type="upcoming_deadline"] i {
            color: #f1c40f;
        }

        .notification-item h4[data-type="overdue"] i {
            color: #e74c3c;
        }

        .notification-item h4[data-type="completed"] i {
            color: #2ecc71;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1200;
            /* Increased z-index to be above the notification sidebar */
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 600px;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5em;
            color: #2c3e50;
        }

        .close {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: #7f8c8d;
        }

        .close:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .form-actions {
            text-align: right;
        }

        .submit-btn {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .submit-btn:hover {
            background-color: #2980b9;
        }

        /* Update the header-right to ensure proper alignment */
        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .task-analysis {
            margin-top: 20px;
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .task-analysis h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            font-size: 1.2em;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
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
                    <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="trash.php"><i class="fas fa-trash"></i> Trash</a></li>
                </ul>
            </nav>
        </div>
        <div class="main-content">
            <header class="header">
                <div class="header-left">
                    <h2>Dashboard</h2>
                </div>
                <div class="header-right">
                    <div class="notification-icon">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">0</span>
                    </div>
                    <div class="user-menu">
                        <img src="<?php echo !empty($user_data['profile_picture']) ? htmlspecialchars($user_data['profile_picture']) : 'placeholder.png'; ?>" alt="User Avatar">
                        <div class="dropdown">
                            <ul>
                                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Log out</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>
            <div class="notification-sidebar">
                <div class="sidebar-header">
                    <h3>Notifications</h3>
                    <button class="close-sidebar">&times;</button>
                </div>
                <div class="sidebar-content">
                    <!-- Notification items will be loaded here -->
                    <p>No new notifications.</p>
                </div>
            </div>
            <div class="analytics-grid">
                <div class="analytics-card">
                    <div class="analytics-icon" style="background-color: #3498db;">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="analytics-info">
                        <h3>Total Tasks</h3>
                        <p><?php echo $stats['total_tasks']; ?></p>
                    </div>
                </div>
                <div class="analytics-card">
                    <div class="analytics-icon" style="background-color: #2ecc71;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="analytics-info">
                        <h3>Completed</h3>
                        <p><?php echo $stats['completed_tasks']; ?></p>
                    </div>
                </div>
                <div class="analytics-card">
                    <div class="analytics-icon" style="background-color: #f1c40f;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="analytics-info">
                        <h3>Pending</h3>
                        <p><?php echo $stats['pending_tasks']; ?></p>
                    </div>
                </div>
                <div class="analytics-card">
                    <div class="analytics-icon" style="background-color: #e74c3c;">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="analytics-info">
                        <h3>Overdue</h3>
                        <p><?php echo $stats['overdue_tasks']; ?></p>
                    </div>
                </div>
            </div>

            <div class="analysis-section">
                <div class="task-analysis">
                    <h3>Task Analysis</h3>
                    <div class="chart-container">
                        <canvas id="taskAnalysisChart"></canvas>
                    </div>
                </div>
                <div class="calendar">
                    <div class="calendar-header">
                        <h3 class="card-title">Calendar</h3>
                        <div class="calendar-nav">
                            <button class="calendar-nav-btn"><i class="fas fa-chevron-left"></i></button>
                            <span>March 2024</span>
                            <button class="calendar-nav-btn"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                    <div class="calendar-grid">
                        <?php
                        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                        foreach ($days as $day) {
                            echo "<div class='calendar-day header'>$day</div>";
                        }

                        $currentDay = date('j');
                        $totalDays = date('t');
                        $firstDay = date('w', strtotime(date('Y-m-01')));

                        for ($i = 0; $i < $firstDay; $i++) {
                            echo "<div class='calendar-day'></div>";
                        }

                        for ($day = 1; $day <= $totalDays; $day++) {
                            $isToday = $day == $currentDay;
                            $hasTasks = isset($calendar_tasks[$day]);
                            $classes = ['calendar-day'];
                            if ($isToday) $classes[] = 'today';
                            if ($hasTasks) $classes[] = 'has-tasks';

                            echo "<div class='" . implode(' ', $classes) . "' data-day='$day'>$day</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div class="recent-tasks-section">
                <div class="card-header">
                    <h3 class="card-title">Recent Tasks</h3>
                    <a href="tasks.php" class="view-all">View All</a>
                </div>
                <?php if (!empty($tasks)): ?>
                    <ul class="task-list">
                        <?php foreach ($tasks as $task): ?>
                            <li class="task-item">
                                <div class="task-checkbox">
                                    <input type="checkbox" <?php echo $task['status'] === 'completed' ? 'checked disabled' : 'disabled'; ?>>
                                </div>
                                <div class="task-content">
                                    <h4 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h4>
                                    <div class="task-details">
                                        <div class="task-detail">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                        </div>
                                        <div class="task-status status-<?php echo strtolower(str_replace(' ', '-', $task['status'])); ?>">
                                            <?php echo ucfirst($task['status']); ?>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <p>No tasks found. Add a new task to get started!</p>
                    </div>
                <?php endif; ?>
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

    <!-- Notification Preview Modal -->
    <div id="notificationPreviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="notificationPreviewTitle">Notification Details</h2>
                <span class="close" id="closeNotificationPreviewModal">&times;</span>
            </div>
            <div class="modal-body">
                <p><strong>Type:</strong> <span id="notificationPreviewType"></span></p>
                <p><strong>Time:</strong> <span id="notificationPreviewTime"></span></p>
                <hr>
                <p><strong>Message:</strong></p>
                <p id="notificationPreviewMessage"></p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarDays = document.querySelectorAll('.calendar-day:not(.header)');
            const calendarTasks = <?php echo json_encode($calendar_tasks); ?>;

            console.log('Calendar Tasks Data:', calendarTasks);

            calendarDays.forEach(day => {
                const dayNum = day.getAttribute('data-day');
                if (calendarTasks[dayNum]) {
                    day.classList.add('has-tasks');

                    // Create tooltip
                    const tooltip = document.createElement('div');
                    tooltip.className = 'task-tooltip';
                    tooltip.innerHTML = calendarTasks[dayNum].map(task => {
                        const statusClass = task.status === 'completed' ? 'completed' : 'pending';
                        return `<div class="task-tooltip-item ${statusClass}">
                            <span class="status-indicator"></span>
                            <span class="task-title">${task.title}</span>
                        </div>`;
                    }).join('');

                    day.appendChild(tooltip);
                }
            });

            // Notification Sidebar Functionality
            const notificationIcon = document.querySelector('.notification-icon');
            const notificationSidebar = document.querySelector('.notification-sidebar');
            const closeSidebarBtn = document.querySelector('.close-sidebar');
            const notificationBadge = document.querySelector('.notification-badge');
            const sidebarContent = document.querySelector('.notification-sidebar .sidebar-content');

            // Notification Preview Modal Elements
            const notificationPreviewModal = document.getElementById('notificationPreviewModal');
            const closeNotificationPreviewModalBtn = document.getElementById('closeNotificationPreviewModal');
            const notificationPreviewType = document.getElementById('notificationPreviewType');
            const notificationPreviewTime = document.getElementById('notificationPreviewTime');
            const notificationPreviewMessage = document.getElementById('notificationPreviewMessage');

            // Function to fetch and display notifications
            function fetchAndDisplayNotifications() {
                fetch('fetch_notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update badge count
                            const unreadCount = data.unread_count || 0;
                            notificationBadge.textContent = unreadCount;
                            notificationBadge.style.display = unreadCount > 0 ? 'block' : 'none';

                            // Display notifications in sidebar
                            sidebarContent.innerHTML = ''; // Clear previous content
                            if (data.notifications && data.notifications.length > 0) {
                                data.notifications.forEach(notification => {
                                    const notificationElement = document.createElement('div');
                                    notificationElement.classList.add('notification-item');
                                    if (!notification.is_read) {
                                        notificationElement.classList.add('unread');
                                    }
                                    notificationElement.setAttribute('data-notification-id', notification.id);
                                    notificationElement.dataset.notification = JSON.stringify(notification);

                                    notificationElement.innerHTML = `
                                        <h4 data-type="${notification.type}">
                                            <i class="fas ${getNotificationIcon(notification.type)}"></i>
                                            ${notification.type.replace('_', ' ').toUpperCase()}
                                        </h4>
                                        <p>${notification.message}</p>
                                        <small>${new Date(notification.created_at).toLocaleString()}</small>
                                        <button class="delete-notification" data-notification-id="${notification.id}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    `;
                                    sidebarContent.appendChild(notificationElement);
                                });
                            } else {
                                sidebarContent.innerHTML = '<p>No new notifications.</p>';
                            }
                        } else {
                            console.error('Error fetching notifications:', data.message);
                            sidebarContent.innerHTML = '<p>Error loading notifications.</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching notifications:', error);
                        sidebarContent.innerHTML = '<p>Error loading notifications.</p>';
                    });
            }

            // Function to get notification icon based on type
            function getNotificationIcon(type) {
                switch (type) {
                    case 'upcoming_deadline':
                        return 'fa-clock';
                    case 'overdue':
                        return 'fa-exclamation-circle';
                    case 'completed':
                        return 'fa-check-circle';
                    default:
                        return 'fa-bell';
                }
            }

            // Fetch notifications on page load
            fetchAndDisplayNotifications();

            // Periodically fetch notifications (every 60 seconds)
            setInterval(fetchAndDisplayNotifications, 60000);

            // Add click handler for notification items
            sidebarContent.addEventListener('click', function(event) {
                const notificationItem = event.target.closest('.notification-item');
                if (notificationItem) {
                    const notification = JSON.parse(notificationItem.dataset.notification);

                    // Mark notification as read
                    if (!notification.is_read) {
                        fetch('mark_notification_read.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    notification_id: notification.id
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    notificationItem.classList.remove('unread');
                                    // Update unread count
                                    const currentCount = parseInt(notificationBadge.textContent);
                                    if (currentCount > 0) {
                                        notificationBadge.textContent = currentCount - 1;
                                    }
                                }
                            })
                            .catch(error => console.error('Error marking notification as read:', error));
                    }
                }

                // Handle delete notification
                const deleteButton = event.target.closest('.delete-notification');
                if (deleteButton) {
                    const notificationId = deleteButton.dataset.notificationId;
                    const notificationItem = deleteButton.closest('.notification-item');

                    fetch('delete_notification.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `notification_id=${notificationId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                notificationItem.remove();
                                // Update unread count
                                notificationBadge.textContent = data.unread_count;
                                notificationBadge.style.display = data.unread_count > 0 ? 'block' : 'none';
                            }
                        })
                        .catch(error => console.error('Error deleting notification:', error));
                }
            });

            // Profile dropdown functionality
            const userMenu = document.querySelector('.user-menu');
            const dropdown = document.querySelector('.dropdown');
            const profileImg = userMenu.querySelector('img');

            // Toggle dropdown on profile image click
            profileImg.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdown.classList.toggle('show');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!userMenu.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            });

            // Toggle sidebar visibility
            notificationIcon.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationSidebar.classList.toggle('open');
                if (notificationSidebar.classList.contains('open')) {
                    fetchAndDisplayNotifications();
                }
            });

            // Close sidebar
            closeSidebarBtn.addEventListener('click', function() {
                notificationSidebar.classList.remove('open');
            });

            // Close sidebar when clicking outside
            document.addEventListener('click', function(e) {
                if (!notificationSidebar.contains(e.target) && !notificationIcon.contains(e.target) && notificationSidebar.classList.contains('open')) {
                    notificationSidebar.classList.remove('open');
                }
            });

            // Close notification preview modal
            closeNotificationPreviewModalBtn.addEventListener('click', function() {
                notificationPreviewModal.style.display = 'none';
            });

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target == notificationPreviewModal) {
                    notificationPreviewModal.style.display = 'none';
                }
            });

        });
    </script>
</body>

</html>