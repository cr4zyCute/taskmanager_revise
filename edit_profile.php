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

// Define variables and initialize with empty values
$full_name = $phone = $address = $profile_picture = "";
$full_name_err = $phone_err = $address_err = $profile_picture_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate full name
    if (empty(trim($_POST["full_name"]))) {
        $full_name_err = "Please enter your full name.";
    } else {
        $full_name = trim($_POST["full_name"]);
    }

    // Validate phone
    if (!empty(trim($_POST["phone"]))) {
        $phone = trim($_POST["phone"]);
    }

    // Validate address
    if (!empty(trim($_POST["address"]))) {
        $address = trim($_POST["address"]);
    }

    // Handle profile picture upload
    if (isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] == 0) {
        $allowed = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png");
        $filename = $_FILES["profile_picture"]["name"];
        $filetype = $_FILES["profile_picture"]["type"];
        $filesize = $_FILES["profile_picture"]["size"];

        // Verify file extension
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (!array_key_exists($ext, $allowed)) {
            $profile_picture_err = "Please select a valid file format (JPG, JPEG, PNG, GIF).";
        }

        // Verify file size - 5MB maximum
        $maxsize = 5 * 1024 * 1024;
        if ($filesize > $maxsize) {
            $profile_picture_err = "File size is larger than the allowed limit (5MB).";
        }

        // Verify MIME type of the file
        if (in_array($filetype, $allowed)) {
            // Check if file exists before uploading
            if (file_exists("uploads/" . $filename)) {
                $filename = time() . '_' . $filename;
            }
            $profile_picture = "uploads/" . $filename;
            move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $profile_picture);
        } else {
            $profile_picture_err = "There was a problem with the uploaded file.";
        }
    }

    // Check input errors before updating the database
    if (empty($full_name_err) && empty($phone_err) && empty($address_err) && empty($profile_picture_err)) {
        // Prepare an update statement
        $sql = "UPDATE users SET full_name = ?, phone = ?, address = ?";
        $params = array($full_name, $phone, $address);
        $types = "sss";

        // If a new profile picture was uploaded, add it to the update
        if (!empty($profile_picture)) {
            $sql .= ", profile_picture = ?";
            $params[] = $profile_picture;
            $types .= "s";
        }

        $sql .= " WHERE id = ?";
        $params[] = $_SESSION["id"];
        $types .= "i";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, $types, ...$params);

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Profile updated successfully
                header("location: profile.php");
                exit();
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }

    // Close connection
    mysqli_close($link);
} else {
    // Fetch current user data
    $sql = "SELECT full_name, phone, address, profile_picture FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $full_name = $row["full_name"];
                $phone = $row["phone"];
                $address = $row["address"];
                $profile_picture = $row["profile_picture"];
            }
        }
        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager - Edit Profile</title>
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
            border-bottom: 1px solid rgba(255,255,255,0.1);
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

        .navigation a:hover, .navigation li.active a {
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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

        .edit-profile-form {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            border-color: #3498db;
            outline: none;
        }

        .invalid-feedback {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
        }

        .profile-picture-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 20px auto;
            display: block;
            object-fit: cover;
            border: 3px solid #3498db;
        }

        .btn-submit {
            background-color: #3498db;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .form-actions {
            text-align: center;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="profile">
                <img src="<?php echo !empty($profile_picture) ? htmlspecialchars($profile_picture) : 'placeholder.png'; ?>" alt="User Profile">
                <span><?php echo htmlspecialchars($full_name); ?></span>
            </div>
            <nav class="navigation">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="#"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="#"><i class="fas fa-trash"></i> Trash</a></li>
                </ul>
            </nav>
        </div>
        <div class="main-content">
            <header class="header">
                <div class="header-left">
                    <h2>Edit Profile</h2>
                </div>
                <div class="header-right">
                    <div class="user-menu">
                        <img src="<?php echo !empty($profile_picture) ? htmlspecialchars($profile_picture) : 'placeholder.png'; ?>" alt="User Avatar">
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
            <div class="edit-profile-form">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Profile Picture</label>
                        <img src="<?php echo !empty($profile_picture) ? htmlspecialchars($profile_picture) : 'placeholder.png'; ?>" alt="Profile Picture" class="profile-picture-preview">
                        <input type="file" name="profile_picture" class="form-control" accept="image/*">
                        <span class="invalid-feedback"><?php echo $profile_picture_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $full_name; ?>">
                        <span class="invalid-feedback"><?php echo $full_name_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $phone; ?>">
                        <span class="invalid-feedback"><?php echo $phone_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" class="form-control <?php echo (!empty($address_err)) ? 'is-invalid' : ''; ?>" rows="3"><?php echo $address; ?></textarea>
                        <span class="invalid-feedback"><?php echo $address_err; ?></span>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-submit">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>