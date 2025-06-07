<?php
// Database credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'taskmanager');

// Attempt to connect to MySQL database
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($link === false) {
    // Store error message instead of dying
    $db_connection_error = "ERROR: Could not connect. " . mysqli_connect_error();
    $link = null; // Set link to null to indicate failure
}
