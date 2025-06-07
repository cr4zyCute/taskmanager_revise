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
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f4f4f4;
        }

        .login-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 300px;
            text-align: center;
        }

        .login-container h2 {
            margin-bottom: 20px;
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            /* Include padding and border in element's total width and height */
        }

        button {
            width: 100%;
            padding: 10px;
            background-color: #5b39ee;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background-color: #4a2cdb;
        }

        .link {
            margin-top: 15px;
            font-size: 0.9em;
        }

        .link a {
            color: #5b39ee;
            text-decoration: none;
        }

        .link a:hover {
            text-decoration: underline;
        }

        .help-block {
            color: red;
            font-size: 0.8em;
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h2>Login</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group <?php echo (!empty($username_email_err)) ? 'has-error' : ''; ?>">
                <label for="username_email">Username or Email</label>
                <input type="text" id="username_email" name="username_email" value="<?php echo $username_email; ?>" required>
                <span class="help-block"><?php echo $username_email_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <span class="help-block"><?php echo $password_err; ?></span>
            </div>
            <button type="submit">Login</button>
        </form>
        <div class="link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>
</body>

</html>