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
    <title>Edit Profile</title>
    <link rel="icon" href="./images/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .profile-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-picture-container {
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            position: relative;
        }

        .profile-picture {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #007bff;
        }

        .profile-picture-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #007bff;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .profile-picture-upload:hover {
            background: #0056b3;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-control {
            border-radius: 8px;
            padding: 12px;
            border: 1px solid #dee2e6;
        }

        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .btn-primary {
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            color: #007bff;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="profile-container">
            <a href="profile.php" class="back-link">
                <i class="fas fa-arrow-left me-2"></i> Back to Profile
            </a>

            <div class="profile-header">
                <h2>Edit Profile</h2>
                <p class="text-muted">Update your personal information</p>
            </div>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                <div class="profile-picture-container">
                    <img src="<?php echo !empty($profile_picture) ? htmlspecialchars($profile_picture) : 'assets/default-profile.png'; ?>"
                        alt="Profile Picture"
                        class="profile-picture"
                        id="profile-preview">
                    <label for="profile_picture" class="profile-picture-upload">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file"
                        name="profile_picture"
                        id="profile_picture"
                        accept="image/*"
                        style="display: none;"
                        onchange="previewImage(this);">
                </div>

                <div class="form-group">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text"
                        name="full_name"
                        id="full_name"
                        class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>"
                        value="<?php echo htmlspecialchars($full_name); ?>">
                    <div class="invalid-feedback"><?php echo $full_name_err; ?></div>
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel"
                        name="phone"
                        id="phone"
                        class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>"
                        value="<?php echo htmlspecialchars($phone); ?>">
                    <div class="invalid-feedback"><?php echo $phone_err; ?></div>
                </div>

                <div class="form-group">
                    <label for="address" class="form-label">Address</label>
                    <textarea name="address"
                        id="address"
                        class="form-control <?php echo (!empty($address_err)) ? 'is-invalid' : ''; ?>"
                        rows="3"><?php echo htmlspecialchars($address); ?></textarea>
                    <div class="invalid-feedback"><?php echo $address_err; ?></div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>

</html>