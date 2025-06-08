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
$new_email = $current_password = $new_password = $confirm_password = "";
$email_err = $current_password_err = $new_password_err = $confirm_password_err = "";
$email_success_msg = $password_success_msg = "";

// Processing email form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_email"])) {
    // Check if it's an email update
    if (!empty(trim($_POST["new_email"]))) {
        $new_email = trim($_POST["new_email"]);

        // Validate email
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Please enter a valid email address.";
        } else {
            // Check if email already exists
            $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "si", $new_email, $_SESSION["id"]);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);
                    if (mysqli_stmt_num_rows($stmt) > 0) {
                        $email_err = "This email is already taken.";
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
    }

    // Update email if no errors
    if (empty($email_err)) {
        // Update email
        $sql = "UPDATE users SET email = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "si", $new_email, $_SESSION["id"]);
            if (mysqli_stmt_execute($stmt)) {
                $email_success_msg = "Email updated successfully!";
                $new_email = ""; // Clear the email field
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Processing password form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_password"])) {
    // Validate current password
    if (empty(trim($_POST["current_password_pwd"]))) {
        $current_password_err = "Please enter your current password.";
    } else {
        $current_password = trim($_POST["current_password_pwd"]);
    }

    // Check if it's a password update
    if (!empty(trim($_POST["new_password"]))) {
        $new_password = trim($_POST["new_password"]);

        // Validate password
        if (strlen($new_password) < 6) {
            $new_password_err = "Password must have at least 6 characters.";
        }

        // Validate confirm password
        if (empty(trim($_POST["confirm_password"]))) {
            $confirm_password_err = "Please confirm the password.";
        } else {
            $confirm_password = trim($_POST["confirm_password"]);
            if (empty($new_password_err) && ($new_password != $confirm_password)) {
                $confirm_password_err = "Password did not match.";
            }
        }
    }

    // Verify current password and update if no errors
    if (empty($current_password_err) && empty($new_password_err) && empty($confirm_password_err)) {
        // Verify current password
        $sql = "SELECT password FROM users WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    mysqli_stmt_bind_result($stmt, $hashed_password);
                    if (mysqli_stmt_fetch($stmt)) {
                        if (password_verify($current_password, $hashed_password)) {
                            // Password is correct, update password
                            mysqli_stmt_close($stmt); // Close the select statement

                            $sql = "UPDATE users SET password = ? WHERE id = ?";
                            $update_stmt = mysqli_prepare($link, $sql);
                            if ($update_stmt) {
                                $param_password = password_hash($new_password, PASSWORD_DEFAULT);
                                mysqli_stmt_bind_param($update_stmt, "si", $param_password, $_SESSION["id"]);
                                if (mysqli_stmt_execute($update_stmt)) {
                                    $password_success_msg = "Password updated successfully!";
                                    $current_password = ""; // Clear the password fields
                                    $new_password = "";
                                    $confirm_password = "";
                                } else {
                                    echo "Oops! Something went wrong. Please try again later.";
                                }
                                mysqli_stmt_close($update_stmt);
                            }
                        } else {
                            $current_password_err = "The current password you entered is incorrect.";
                            mysqli_stmt_close($stmt);
                        }
                    } else {
                        mysqli_stmt_close($stmt);
                    }
                } else {
                    mysqli_stmt_close($stmt);
                }
            } else {
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Fetch current user data
$sql = "SELECT email FROM users WHERE id = ?";
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $current_email = $row["email"];
        }
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Task Manager</title>
    <link rel="icon" href="./images/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .settings-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .settings-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .settings-header h2 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .settings-header p {
            color: #7f8c8d;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            color: #2c3e50;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 8px;
            padding: 12px;
            border: 1px solid #dee2e6;
        }

        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .btn-primary {
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background-color: #3498db;
            border-color: #3498db;
        }

        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }

        .invalid-feedback {
            font-size: 0.875rem;
            margin-top: 5px;
        }

        .back-link {
            color: #6c757d;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .back-link:hover {
            color: #3498db;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message i {
            font-size: 1.2em;
        }

        .settings-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .settings-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.2em;
        }

        .password-field {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 0;
            z-index: 10;
        }

        .password-toggle:hover {
            color: #3498db;
        }

        .password-field .form-control {
            padding-right: 40px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="settings-container">
            <a href="dashboard.php" class="back-link">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>

            <div class="settings-header">
                <h2>Account Settings</h2>
                <p>Manage your account preferences and security</p>
            </div>

            <?php if (!empty($email_success_msg)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $email_success_msg; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($password_success_msg)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $password_success_msg; ?>
                </div>
            <?php endif; ?>

            <!-- Email Update Form -->
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="settings-section">
                <h3>Change Email</h3>
                <div class="form-group">
                    <label for="current_email" class="form-label">Current Email</label>
                    <input type="email"
                        id="current_email"
                        class="form-control"
                        value="<?php echo htmlspecialchars($current_email); ?>"
                        disabled>
                </div>
                <div class="form-group">
                    <label for="new_email" class="form-label">New Email</label>
                    <input type="email"
                        name="new_email"
                        id="new_email"
                        class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>"
                        value="<?php echo htmlspecialchars($new_email); ?>">
                    <div class="invalid-feedback"><?php echo $email_err; ?></div>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" name="update_email" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Email
                    </button>
                </div>
            </form>

            <!-- Password Update Form -->
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="settings-section">
                <h3>Change Password</h3>
                <div class="form-group">
                    <label for="current_password_pwd" class="form-label">Current Password</label>
                    <div class="password-field">
                        <input type="password"
                            name="current_password_pwd"
                            id="current_password_pwd"
                            class="form-control <?php echo (!empty($current_password_err)) ? 'is-invalid' : ''; ?>"
                            value="<?php echo htmlspecialchars($current_password); ?>">
                        <button type="button" class="password-toggle" onclick="togglePassword('current_password_pwd')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback"><?php echo $current_password_err; ?></div>
                </div>
                <div class="form-group">
                    <label for="new_password" class="form-label">New Password</label>
                    <div class="password-field">
                        <input type="password"
                            name="new_password"
                            id="new_password"
                            class="form-control <?php echo (!empty($new_password_err)) ? 'is-invalid' : ''; ?>"
                            value="<?php echo htmlspecialchars($new_password); ?>">
                        <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback"><?php echo $new_password_err; ?></div>
                </div>
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <div class="password-field">
                        <input type="password"
                            name="confirm_password"
                            id="confirm_password"
                            class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>"
                            value="<?php echo htmlspecialchars($confirm_password); ?>">
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" name="update_password" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>

</html>