<?php
// Secure database connection using MySQLi with error handling

$host = 'localhost';         // or your server address
$dbname = 'VehicleManagementSystem';
$username = 'root';
$password = '';

// Enable error reporting for development (remove or set to 0 in production)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Create new MySQLi connection
    $conn = new mysqli($host, $username, $password, $dbname);
    
    // Set charset to utf8mb4 to support all characters and prevent injection
    $conn->set_charset('utf8mb4');

} catch (mysqli_sql_exception $e) {
    // Log error in production instead of displaying
    error_log('Database connection error: ' . $e->getMessage());
    
    // Show generic error message
    die('Database connection failed. Please try again later.');
}
?>
