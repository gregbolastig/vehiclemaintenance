<?php
// register_handler.php
require_once 'dbconnection.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Also handle form data
if (!$input) {
    $input = $_POST;
}

// Validate required fields
if (!isset($input['registration_type']) || !in_array($input['registration_type'], ['driver', 'supervisor'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid registration type']);
    exit;
}

try {
    if ($input['registration_type'] === 'driver') {
        registerDriver($conn, $input);
    } else {
        registerSupervisor($conn, $input);
    }
} catch (Exception $e) {
    error_log('Registration error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
}

function registerDriver($conn, $data) {
    // Validate required fields
    $required_fields = ['first_name', 'last_name', 'employee_no', 'date_of_birth', 'age', 'email', 'contact_number', 'address', 'license_number', 'license_expiration', 'password'];
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            echo json_encode(['success' => false, 'message' => 'Missing required field: ' . $field]);
            return;
        }
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    // Validate password confirmation
    if ($data['password'] !== $data['confirm_password']) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        return;
    }
    
    // Hash password
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Check if email, employee number, or license number already exists
    $check_sql = "SELECT COUNT(*) as count FROM Driver WHERE EmailAddress = ? OR EmployeeNo = ? OR DriverLicenseNumber = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('sss', $data['email'], $data['employee_no'], $data['license_number']);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Email, employee number, or license number already exists']);
        return;
    }
    
    // Insert driver
    $sql = "INSERT INTO Driver (FirstName, MiddleName, LastName, EmployeeNo, DateOfBirth, Age, EmailAddress, ContactNumber, Address, DriverLicenseNumber, DriverLicenseExpiration, Password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssssssssss', 
        $data['first_name'],
        $data['middle_name'] ?? '',
        $data['last_name'],
        $data['employee_no'],
        $data['date_of_birth'],
        $data['age'],
        $data['email'],
        $data['contact_number'],
        $data['address'],
        $data['license_number'],
        $data['license_expiration'],
        $hashed_password
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Driver registered successfully', 'driver_id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed']);
    }
}

function registerSupervisor($conn, $data) {
    // Validate required fields
    $required_fields = ['first_name', 'last_name', 'email', 'contact_number', 'supervisor_location', 'password'];
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            echo json_encode(['success' => false, 'message' => 'Missing required field: ' . $field]);
            return;
        }
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    // Validate password confirmation
    if ($data['password'] !== $data['confirm_password']) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        return;
    }
    
    // Validate supervisor location
    $valid_locations = ['Boracay', 'Caticlan', 'Kalibo'];
    if (!in_array($data['supervisor_location'], $valid_locations)) {
        echo json_encode(['success' => false, 'message' => 'Invalid supervisor location']);
        return;
    }
    
    // Hash password
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Check if email already exists
    $check_sql = "SELECT COUNT(*) as count FROM Supervisor WHERE Email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('s', $data['email']);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        return;
    }
    
    // Insert supervisor
    $sql = "INSERT INTO Supervisor (FirstName, MiddleName, LastName, Email, ContactNumber, SupervisorLocation, Password) VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssss', 
        $data['first_name'],
        $data['middle_name'] ?? '',
        $data['last_name'],
        $data['email'],
        $data['contact_number'],
        $data['supervisor_location'],
        $hashed_password
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Supervisor registered successfully', 'supervisor_id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed']);
    }
}
?>
