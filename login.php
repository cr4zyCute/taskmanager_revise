<?php
// Initialize the session
session_start();

// Check if the user is already logged in, if yes then redirect him to dashboard
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: dashboard.php");
    exit;
}

// Include config file
require_once "config.php";

// Define variables and initialize with empty values
$username_email = $password = "";
$username_email_err = $password_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate username or email
    if (empty(trim($_POST["username_email"]))) {
        $username_email_err = "Please enter your username or email.";
    } else {
        $username_email = trim($_POST["username_email"]);
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Check input errors before attempting to login
    if (empty($username_email_err) && empty($password_err)) {
        // Prepare a select statement (check for username or email)
        $sql = "SELECT id, username, password FROM users WHERE username = ? OR email = ?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ss", $param_username_email, $param_email);

            // Set parameters
            $param_username_email = $username_email;
            $param_email = $username_email; // Use the same input for email check

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Store result
                mysqli_stmt_store_result($stmt);

                // Check if username or email exists, if yes then verify password
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password);
                    if (mysqli_stmt_fetch($stmt)) {
                        if (password_verify($password, $hashed_password)) {
                            // Password is correct, so start a new session
                            session_start();

                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;

                            // Redirect user to dashboard page
                            header("location: dashboard.php");
                        } else {
                            // Display an error message if password is not valid
                            $password_err = "The password you entered was not valid.";
                        }
                    }
                } else {
                    // Display an error message if username or email doesn't exist
                    $username_email_err = "No account found with that username or email.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
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
    <title>Login</title>
    <link rel="icon" href="./images/logo.png" type="image/png">
    <link rel="stylesheet" href="style.css">
    <style>
        /* General Body and Main Container */
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f4f4f4;
            font-family: Arial, sans-serif;
            margin: 0;
            overflow: hidden;
        }

        .main-container {
            display: flex;
            width: 900px;
            height: 600px;
            background-color: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .left-panel {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
        }

        .logo {
            margin-bottom: 20px;
        }

        .logo img {
            width: 40px;
            height: 50px;
        }

        .welcome-section h1 {
            font-size: 36px;
            margin-bottom: 10px;
            color: #333;
        }

        .welcome-section p {
            font-size: 16px;
            color: #777;
            margin-bottom: 30px;
        }

        .login-form {
            width: 100%;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            color: #555;
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            /* Make input fill the wrapper */
            padding: 12px 10px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            outline: none;
            background-color: white;
            /* Inner background for input */
            box-sizing: border-box;
            /* Include padding in width */
        }

        .input-wrapper {
            position: relative;
            border-radius: 8px;
            background: linear-gradient(to right, #a1c4fd, #c2e9fb);
            /* Gradient border */
            padding: 1px;
            /* Creates the border effect */
            display: flex;
            /* Ensures input fills the wrapper */
        }

        .input-wrapper:focus-within {
            box-shadow: 0 0 0 2px rgba(91, 57, 238, 0.5);
            /* Blue shadow on focus of the wrapper */
        }

        .form-group.has-error .input-wrapper {
            background: red;
            /* Red border for error */
        }

        .help-block {
            color: red;
            font-size: 12px;
            margin-top: 5px;
            position: absolute;
            bottom: -18px;
            left: 0;
        }

        .forgot-password {
            text-align: right;
            margin-bottom: 20px;
        }

        .forgot-password a {
            color: #5b39ee;
            text-decoration: none;
            font-size: 14px;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .sign-in-button {
            width: 100%;
            padding: 15px;
            background-color: #000;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-bottom: 20px;
        }

        .sign-in-button:hover {
            background-color: #333;
        }

        .or-continue {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin-bottom: 20px;
            color: #888;
            font-size: 14px;
        }

        .or-continue span.continue {
            white-space: nowrap;
            /* Prevent text from wrapping */
            padding: 0 10px;
            /* Space between text and lines */
        }

        .or-continue span::before,
        .or-continue span::after {
            content: '';
            flex-grow: 1;
            /* Make lines fill available space */
            height: 1px;
            background-color: #ccc;
            vertical-align: middle;
            /* margin: 0 10px; */
            /* Removed fixed margin */
        }

        .google-login-button {
            width: 100%;
            padding: 15px;
            background-color: #fff;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            transition: background-color 0.3s ease;
        }

        .google-login-button:hover {
            background-color: #f0f0f0;
        }

        .google-login-button img {
            width: 20px;
            height: 20px;
            margin-right: 10px;
        }

        .signup-link {
            text-align: center;
            font-size: 14px;
            color: #888;
        }

        .signup-link a {
            color: #5b39ee;
            text-decoration: none;
            font-weight: bold;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        .right-panel {
            flex: 1;
            background-color: #1a1a1a;
            border-radius: 0 20px 20px 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .right-panel img {
            max-width: 100%;
            height: auto;
            margin-bottom: 30px;
            /* Remove absolute positioning */
            position: static;
            top: auto;
            left: auto;
            transform: none;
            z-index: auto;
        }

        .illustration-content {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
        }

        .illustration-content h2 {
            font-size: 30px;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .illustration-content p {
            font-size: 15px;
            color: #bbb;
            line-height: 1.6;
        }

        .pagination-dots {
            position: absolute;
            bottom: 30px;
            width: 100%;
            display: flex;
            justify-content: center;
            gap: 10px;
            z-index: 2;
        }

        .dot {
            width: 10px;
            height: 10px;
            background-color: #555;
            border-radius: 50%;
            cursor: pointer;
        }

        .dot.active {
            background-color: #eee;
        }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="left-panel">
            <div class="logo">
                <img src="./images/logo.png" alt="Logo"> <!-- Placeholder for logo -->
            </div>
            <div class="welcome-section">
                <h1>Welcome Back!</h1>
                <p>Please enter log in details below</p>
            </div>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="login-form">
                <div class="form-group <?php echo (!empty($username_email_err)) ? 'has-error' : ''; ?>">
                    <label for="username_email">Email</label>
                    <div class="input-wrapper">
                        <input type="text" id="username_email" name="username_email" value="<?php echo $username_email; ?>" placeholder="@gmail.com" required>
                    </div>
                    <span class="help-block"><?php echo $username_email_err; ?></span>
                </div>
                <div class="form-group <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="" required>
                    </div>
                    <span class="help-block"><?php echo $password_err; ?></span>
                </div>
                <!-- <div class="forgot-password">
                    <a href="#">Forget password?</a>
                </div> -->
                <button type="submit" class="sign-in-button">Log in</button>
            </form>

            <div class="or-continue">
                <span class="continue"> Don't have an account? <a href="register.php">Sign Up</a></span>
            </div>


            <!-- <div class="signup-link">
                Don't have an account? <a href="register.php">Sign Up</a>
            </div> -->
        </div>
        <div class="right-panel">
            <div class="illustration-content">
                <img src="./images/illustration.png" alt="Illustration"> <!-- Placeholder for illustration -->
                <h2>Organize Your Work Anytime, Anywhere</h2>
                <p>Focus on what matters with a clean, easy-to-use task manager.</p>
            </div>

        </div>
    </div>
</body>

</html>