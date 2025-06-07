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
mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager - Profile</title>
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

        .profile-card {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 0 auto;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin-bottom: 20px;
            border: 3px solid #3498db;
            object-fit: cover;
        }

        .profile-header h2 {
            margin: 10px 0 5px;
            color: #2c3e50;
            font-size: 24px;
        }

        .username {
            color: #7f8c8d;
            margin: 0;
            font-size: 16px;
        }

        .profile-details {
            max-width: 600px;
            margin: 0 auto;
        }

        .detail-item {
            display: flex;
            align-items: center;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: transform 0.2s;
        }

        .detail-item:hover {
            transform: translateX(5px);
        }

        .detail-item i {
            margin-right: 20px;
            color: #3498db;
            font-size: 1.4em;
            width: 24px;
            text-align: center;
        }

        .detail-item span {
            font-size: 16px;
            color: #2c3e50;
        }

        .profile-actions {
            text-align: center;
            margin-top: 40px;
        }

        .edit-profile-btn {
            display: inline-block;
            padding: 12px 30px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
            font-size: 16px;
        }

        .edit-profile-btn:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
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
                </ul>
            </nav>
        </div>
        <div class="main-content">
            <header class="header">
                <div class="header-left">
                    <h2>Profile</h2>
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
        </div>
    </div>
</body>

</html>