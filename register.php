<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Include config file
require_once "config.php";

// Define variables and initialize with empty values
$username = $email = $password = $confirm_password = $full_name = $phone = $address = $profile_picture = "";
$username_err = $email_err = $password_err = $confirm_password_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Debugging: Dump $_FILES array
    var_dump($_FILES);
    echo "<br>";

    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE username = ?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_username);

            // Set parameters
            $param_username = trim($_POST["username"]);

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // store result
                mysqli_stmt_store_result($stmt);

                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }

    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE email = ?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_email);

            // Set parameters
            $param_email = trim($_POST["email"]);

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // store result
                mysqli_stmt_store_result($stmt);

                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $email_err = "This email is already registered.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have atleast 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }

    // Handle profile picture upload
    if (isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] == 0) {
        $allowed_types = array("image/jpeg", "image/png", "image/gif");
        $max_size = 5 * 1024 * 1024; // 5MB

        if (in_array($_FILES["profile_picture"]["type"], $allowed_types) && $_FILES["profile_picture"]["size"] <= $max_size) {
            $upload_dir = "uploads/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION);
            $new_filename = uniqid() . "." . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $upload_path)) {
                $profile_picture = $upload_path;
            } else {
                echo "Error moving uploaded file. Error code: " . $_FILES["profile_picture"]["error"] . "<br>";
            }
        } else {
            echo "Invalid file type or size. Type: " . $_FILES["profile_picture"]["type"] . ", Size: " . $_FILES["profile_picture"]["size"] . "<br>";
        }
    } else {
        echo "Profile picture not uploaded or an error occurred. Error code: " . (isset($_FILES["profile_picture"]) ? $_FILES["profile_picture"]["error"] : "N/A") . "<br>";
    }

    // Get profile information
    $full_name = trim($_POST["full_name"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);

    // Check input errors before inserting in database
    if (empty($username_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)) {

        // Prepare an insert statement
        $sql = "INSERT INTO users (username, email, password, full_name, phone, address, profile_picture, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, current_timestamp())";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "sssssss", $param_username, $param_email, $param_password, $param_full_name, $param_phone, $param_address, $param_profile_picture);

            // Set parameters
            $param_username = $username;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            $param_full_name = $full_name;
            $param_phone = $phone;
            $param_address = $address;
            $param_profile_picture = $profile_picture;

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Redirect to login page
                header("location: login.php");
            } else {
                echo "Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }

    // Close connection
    mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="icon" href="./images/logo.png" type="image/png">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Profile Picture Size Variables - Adjust these values to change the size */
        :root {
            --profile-size: 120px;
            /* Main profile picture size */
            --edit-icon-size: 35px;
            /* Edit icon size */
            --edit-icon-offset: 30%;
            /* How far the edit icon is offset */
            --profile-border: 4px;
            /* Border width */
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .register-container {
            background-color: rgba(255, 255, 255, 0.95);
            padding: 70px 50px 30px 50px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 800px;
            max-width: 90%;
            text-align: center;
            position: relative;
            padding-top: calc(var(--profile-size) / 2 + 50px);
            /* Adjusted to match new profile position */
            backdrop-filter: blur(10px);
            margin: 20px;
        }

        .register-container h2 {
            display: none;
            /* Hide the h2 tag */
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 0.95em;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            box-sizing: border-box;
            background-color: #fff;
            transition: all 0.3s ease;
            font-size: 0.95em;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group textarea:focus,
        .form-group input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 8px rgba(102, 126, 234, 0.2);
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .link {
            margin-top: 20px;
            font-size: 0.95em;
            color: #666;
        }

        .link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .link a:hover {
            color: #764ba2;
        }

        .help-block {
            color: #dc3545;
            font-size: 0.85em;
            margin-top: 5px;
            display: block;
        }

        .profile-picture-upload {
            text-align: center;
            position: absolute;
            width: var(--profile-size);
            height: var(--profile-size);
            top: 2%;
            /* Adjusted to move lower */
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            overflow: hidden;
            cursor: pointer;
            border-radius: 50%;
            /* Ensure the container itself is a circle */
        }

        .profile-picture-preview {
            width: var(--profile-size);
            height: var(--profile-size);
            border-radius: 50%;
            object-fit: cover;
            display: block;
            border: var(--profile-border) solid #fff;
            background-color: #f8f9fa;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .profile-picture-upload:hover .profile-picture-preview {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .edit-icon {
            position: absolute;
            bottom: 10px;
            /* Adjusted to bring it further inside the circle */
            right: 10px;
            /* Adjusted to bring it further inside the circle */
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            width: var(--edit-icon-size);
            height: var(--edit-icon-size);
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            /* Removed transform: translate to prevent clipping */
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            border: none;
            z-index: 11;
        }

        .edit-icon:hover {
            transform: scale(1.1);
            /* Adjusted transform for hover */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .edit-icon img {
            width: calc(var(--edit-icon-size) * 0.45);
            /* Icon size relative to container */
            height: calc(var(--edit-icon-size) * 0.45);
            filter: invert(100%);
            transition: transform 0.3s ease;
        }

        .edit-icon:hover img {
            transform: rotate(15deg);
        }

        .profile-picture-upload::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .profile-picture-upload:hover::before {
            opacity: 1;
        }

        .profile-picture-upload input[type="file"] {
            display: none;
        }

        /* Add a placeholder text for the profile picture */
        .profile-picture-upload::after {
            content: 'Click to upload photo';
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.85em;
            color: #666;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .profile-picture-upload:hover::after {
            opacity: 1;
        }

        .form-content {
            display: flex;
            justify-content: space-between;
            gap: 40px;
            margin-top: 40px;
        }

        .left-column,
        .right-column {
            width: 48%;
            /* Adjusted width to account for gap */
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            :root {
                --profile-size: 150px;
                /* Smaller size on mobile */
                --edit-icon-size: 40px;
            }

            .register-container {
                width: 95%;
                padding: 70px 30px 30px 30px;
            }

            .form-content {
                flex-direction: column;
                gap: 20px;
            }

            .left-column,
            .right-column {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            :root {
                --profile-size: 120px;
                /* Even smaller size on very small screens */
                --edit-icon-size: 35px;
            }

            .register-container {
                width: 100%;
                padding: 70px 20px 30px 20px;
                margin: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="register-container">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <div class="profile-picture-upload">
                <img id="preview" src="./images/profile-preview.png" alt="Profile Picture Preview" class="profile-picture-preview">
                <label for="profile_picture" class="edit-icon"><img src="./images/pencil.png" alt="Edit Icon"></label>
                <input type="file" id="profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this)">
            </div>
            <div class="form-content">
                <div class="left-column">
                    <div class="form-group <?php echo (!empty($username_err)) ? 'has-error' : ''; ?>">
                        <label for="username">User name</label>
                        <input type="text" id="username" name="username" value="<?php echo $username; ?>" required>
                        <span class="help-block"><?php echo $username_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label for="full_name">full name</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo $full_name; ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo $phone; ?>">
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="2"><?php echo $address; ?></textarea>
                    </div>
                </div>
                <div class="right-column">
                    <div class="form-group <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo $email; ?>" required>
                        <span class="help-block"><?php echo $email_err; ?></span>
                    </div>
                    <div class="form-group <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                        <span class="help-block"><?php echo $password_err; ?></span>
                    </div>
                    <div class="form-group <?php echo (!empty($confirm_password_err)) ? 'has-error' : ''; ?>">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <span class="help-block"><?php echo $confirm_password_err; ?></span>
                    </div>
                </div>
            </div>
            <button type="submit">Register</button>
        </form>
        <div class="link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>

</html>