<?php
// registration.php
require_once 'dbconnection.php';

// Handle POST requests for registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set content type to JSON
    header('Content-Type: application/json');
    
    // Enable CORS if needed
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type');
    
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
    
    exit; // Stop execution after handling POST request
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
    if (!isset($data['confirm_password']) || $data['password'] !== $data['confirm_password']) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        return;
    }
    
    // Validate password strength
    if (strlen($data['password']) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
        return;
    }
    
    // Validate age is numeric
    if (!is_numeric($data['age']) || $data['age'] < 18 || $data['age'] > 100) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid age between 18 and 100']);
        return;
    }
    
    // Validate date of birth
    $dob = DateTime::createFromFormat('Y-m-d', $data['date_of_birth']);
    if (!$dob || $dob->format('Y-m-d') !== $data['date_of_birth']) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid date of birth']);
        return;
    }
    
    // Validate license expiration date
    $license_exp = DateTime::createFromFormat('Y-m-d', $data['license_expiration']);
    if (!$license_exp || $license_exp->format('Y-m-d') !== $data['license_expiration']) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid license expiration date']);
        return;
    }
    
    // Check if license is not expired
    if ($license_exp <= new DateTime()) {
        echo json_encode(['success' => false, 'message' => 'Driver license has expired']);
        return;
    }
    
    // Hash password
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Check if email, employee number, or license number already exists
    $check_sql = "SELECT COUNT(*) as count FROM Driver WHERE EmailAddress = ? OR EmployeeNo = ? OR DriverLicenseNumber = ?";
    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        return;
    }
    
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
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        return;
    }
    
    $middle_name = isset($data['middle_name']) ? $data['middle_name'] : '';
    $age_str = (string)$data['age']; // Convert to string as per database schema
    
    $stmt->bind_param('ssssssssssss', 
        $data['first_name'],
        $middle_name,
        $data['last_name'],
        $data['employee_no'],
        $data['date_of_birth'],
        $age_str,
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
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }
    
    $stmt->close();
    $check_stmt->close();
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
    if (!isset($data['confirm_password']) || $data['password'] !== $data['confirm_password']) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        return;
    }
    
    // Validate password strength
    if (strlen($data['password']) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
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
    if (!$check_stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        return;
    }
    
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
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        return;
    }
    
    $middle_name = isset($data['middle_name']) ? $data['middle_name'] : '';
    
    $stmt->bind_param('sssssss', 
        $data['first_name'],
        $middle_name,
        $data['last_name'],
        $data['email'],
        $data['contact_number'],
        $data['supervisor_location'],
        $hashed_password
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Supervisor registered successfully', 'supervisor_id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }
    
    $stmt->close();
    $check_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Management System - Registration</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #E5E5E5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .registration-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
            padding: 40px 30px;
            border: 1px solid #E0E0E0;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 120px;
            height: 120px;
            background: #2E7D32;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .portal-title {
            font-size: 24px;
            font-weight: 600;
            color: #2E7D32;
            text-align: center;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .registration-type-toggle {
            display: flex;
            background: #F5F5F5;
            border-radius: 8px;
            padding: 4px;
            margin-bottom: 30px;
        }
        
        .toggle-button {
            flex: 1;
            padding: 12px;
            border: none;
            background: transparent;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #666;
        }
        
        .toggle-button.active {
            background: #2E7D32;
            color: white;
            box-shadow: 0 2px 4px rgba(46, 125, 50, 0.2);
        }
        
        .form-container {
            display: none;
        }
        
        .form-container.active {
            display: block;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            flex: 1 1 100%;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #424242;
            margin-bottom: 8px;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            height: 48px;
            border: 2px solid #BDBDBD;
            border-radius: 6px;
            padding: 0 16px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            background: white;
            transition: border-color 0.2s ease;
        }
        
        .form-textarea {
            height: 100px;
            padding: 12px 16px;
            resize: vertical;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #2E7D32;
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.1);
        }
        
        .form-input::placeholder, .form-textarea::placeholder {
            color: #9E9E9E;
        }
        
        .register-button {
            width: 100%;
            height: 48px;
            background: #2E7D32;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: background-color 0.2s ease;
            margin-top: 20px;
            letter-spacing: 0.5px;
        }
        
        .register-button:hover {
            background: #1B5E20;
        }
        
        .register-button:active {
            transform: translateY(1px);
        }
        
        .register-button:disabled {
            background: #BDBDBD;
            cursor: not-allowed;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .login-link a {
            color: #2E7D32;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .password-input-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            cursor: pointer;
            z-index: 2;
            background-size: contain;
            background-repeat: no-repeat;
            opacity: 0.6;
            transition: opacity 0.2s ease;
        }
        
        .password-toggle:hover {
            opacity: 1;
        }
        
        .password-toggle.show {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23757575' viewBox='0 0 24 24'%3E%3Cpath d='M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z'/%3E%3C/svg%3E");
        }
        
        .password-toggle.hide {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23757575' viewBox='0 0 24 24'%3E%3Cpath d='M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z'/%3E%3C/svg%3E");
        }
        
        .required {
            color: #d32f2f;
        }
        
        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }
        
        .message.success {
            background: #E8F5E8;
            color: #2E7D32;
            border: 1px solid #C8E6C9;
        }
        
        .message.error {
            background: #FFEBEE;
            color: #D32F2F;
            border: 1px solid #FFCDD2;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #2E7D32;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 640px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .registration-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>

<body>
    <div class="registration-container">
        <div class="logo-container">
            <div class="logo">VMS</div>
            <h1 class="portal-title">Vehicle Management System</h1>
        </div>
        
        <div class="registration-type-toggle">
            <button class="toggle-button active" onclick="switchForm('driver')">Driver Registration</button>
            <button class="toggle-button" onclick="switchForm('supervisor')">Supervisor Registration</button>
        </div>
        
        <div class="message" id="message"></div>
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <div>Processing registration...</div>
        </div>
        
        <!-- Driver Registration Form -->
        <form class="form-container active" id="driverForm">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" class="form-input" placeholder="Enter first name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middle_name" class="form-input" placeholder="Enter middle name">
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" class="form-input" placeholder="Enter last name" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Employee Number <span class="required">*</span></label>
                    <input type="text" name="employee_no" class="form-input" placeholder="Enter employee number" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Date of Birth <span class="required">*</span></label>
                    <input type="date" name="date_of_birth" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Age <span class="required">*</span></label>
                    <input type="number" name="age" class="form-input" placeholder="Enter age" min="18" max="100" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Email Address <span class="required">*</span></label>
                    <input type="email" name="email" class="form-input" placeholder="Enter email address" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Number <span class="required">*</span></label>
                    <input type="tel" name="contact_number" class="form-input" placeholder="Enter contact number" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Address <span class="required">*</span></label>
                <textarea name="address" class="form-textarea" placeholder="Enter complete address" required></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Driver License Number <span class="required">*</span></label>
                    <input type="text" name="license_number" class="form-input" placeholder="Enter license number" required>
                </div>
                <div class="form-group">
                    <label class="form-label">License Expiration <span class="required">*</span></label>
                    <input type="date" name="license_expiration" class="form-input" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password <span class="required">*</span></label>
                    <div class="password-input-container">
                        <input type="password" name="password" class="form-input" placeholder="Enter password (min 6 chars)" minlength="6" required>
                        <div class="password-toggle show" onclick="togglePassword(this)"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password <span class="required">*</span></label>
                    <div class="password-input-container">
                        <input type="password" name="confirm_password" class="form-input" placeholder="Confirm password" minlength="6" required>
                        <div class="password-toggle show" onclick="togglePassword(this)"></div>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="register-button">Register as Driver</button>
            
            <div class="login-link">
                <a href="login.php">Already have an account? Login here</a>
            </div>
        </form>
        
        <!-- Supervisor Registration Form -->
        <form class="form-container" id="supervisorForm">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" class="form-input" placeholder="Enter first name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middle_name" class="form-input" placeholder="Enter middle name">
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" class="form-input" placeholder="Enter last name" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Email Address <span class="required">*</span></label>
                    <input type="email" name="email" class="form-input" placeholder="Enter email address" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Number <span class="required">*</span></label>
                    <input type="tel" name="contact_number" class="form-input" placeholder="Enter contact number" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Supervisor Location <span class="required">*</span></label>
                <select name="supervisor_location" class="form-select" required>
                    <option value="">Select location</option>
                    <option value="Boracay">Boracay</option>
                    <option value="Caticlan">Caticlan</option>
                    <option value="Kalibo">Kalibo</option>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password <span class="required">*</span></label>
                    <div class="password-input-container">
                        <input type="password" name="password" class="form-input" placeholder="Enter password (min 6 chars)" minlength="6" required>
                        <div class="password-toggle show" onclick="togglePassword(this)"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password <span class="required">*</span></label>
                    <div class="password-input-container">
                        <input type="password" name="confirm_password" class="form-input" placeholder="Confirm password" minlength="6" required>
                        <div class="password-toggle show" onclick="togglePassword(this)"></div>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="register-button">Register as Supervisor</button>
            
            <div class="login-link">
                <a href="login.php">Already have an account? Login here</a>
            </div>
        </form>
    </div>
    
    <script>
        function switchForm(type) {
            // Remove active class from all toggle buttons
            document.querySelectorAll('.toggle-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Remove active class from all form containers
            document.querySelectorAll('.form-container').forEach(form => {
                form.classList.remove('active');
            });
            
            // Add active class to clicked button
            event.target.classList.add('active');
            
            // Show corresponding form
            if (type === 'driver') {
                document.getElementById('driverForm').classList.add('active');
            } else {
                document.getElementById('supervisorForm').classList.add('active');
            }
            
            // Hide messages
            hideMessage();
        }
        
        function togglePassword(element) {
            const input = element.previousElementSibling;
            const isPassword = input.type === 'password';
            
            input.type = isPassword ? 'text' : 'password';
            element.classList.toggle('show', isPassword);
            element.classList.toggle('hide', !isPassword);
        }
        
        function showMessage(message, type = 'success') {
            const messageDiv = document.getElementById('message');
            messageDiv.textContent = message;
            messageDiv.className = `message ${type}`;
            messageDiv.style.display = 'block';
            
            // Hide message after 5 seconds
            setTimeout(() => {
                hideMessage();
            }, 5000);
        }
        
        function hideMessage() {
            const messageDiv = document.getElementById('message');
            messageDiv.style.display = 'none';
        }
        
        function showLoading() {
            document.getElementById('loading').style.display = 'block';
        }
        
        function hideLoading() {
            document.getElementById('loading').style.display = 'none';
        }
        
        async function submitForm(formData, registrationType) {
            showLoading();
            hideMessage();
            
            // Disable submit button
            const submitButton = document.querySelector(`#${registrationType}Form button[type="submit"]`);
            submitButton.disabled = true;
            
            try {
                const response = await fetch('registration.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showMessage(result.message, 'success');
                    // Reset form
                    document.getElementById(`${registrationType}Form`).reset();
                } else {
                    showMessage(result.message, 'error');
                }
            } catch (error) {
                showMessage('Network error. Please try again.', 'error');
                console.error('Error:', error);
            } finally {
                hideLoading();
                submitButton.disabled = false;
            }
        }
        
        // Form validation and submission
        document.getElementById('driverForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            data.registration_type = 'driver';
            
            // Client-side validation
            if (data.password !== data.confirm_password) {
                showMessage('Passwords do not match!', 'error');
                return;
            }
            
            submitForm(data, 'driver');
        });
        
        document.getElementById('supervisorForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            data.registration_type = 'supervisor';
            
            // Client-side validation
            if (data.password !== data.confirm_password) {
                showMessage('Passwords do not match!', 'error');
                return;
            }
            
            submitForm(data, 'supervisor');
        });
    </script>
</body>
</html>