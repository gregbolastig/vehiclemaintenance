<?php
// Database configuration
$host = 'localhost';
$dbname = 'VehicleManagementSystem';
$username = 'root';
$password = '';

// Create database connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Function to validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to validate contact number
function validateContact($contact) {
    return preg_match('/^[\d\s\-\+\(\)]+$/', $contact) && strlen($contact) >= 10;
}

// Function to calculate age
function calculateAge($birthdate) {
    $today = new DateTime();
    $birth = new DateTime($birthdate);
    $age = $today->diff($birth)->y;
    return $age;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        if (isset($_POST['registration_type'])) {
            $registration_type = $_POST['registration_type'];
            
            if ($registration_type === 'driver') {
                // Handle Driver Registration
                $firstName = sanitizeInput($_POST['firstName']);
                $middleName = sanitizeInput($_POST['middleName']);
                $lastName = sanitizeInput($_POST['lastName']);
                $employeeNo = sanitizeInput($_POST['employeeNo']);
                $dateOfBirth = sanitizeInput($_POST['dateOfBirth']);
                $email = sanitizeInput($_POST['email']);
                $contactNumber = sanitizeInput($_POST['contactNumber']);
                $address = sanitizeInput($_POST['address']);
                $driverLicenseNumber = sanitizeInput($_POST['driverLicenseNumber']);
                $licenseExpiration = sanitizeInput($_POST['licenseExpiration']);
                $password = $_POST['password'];
                
                // Validation
                if (empty($firstName) || empty($lastName) || empty($employeeNo) || 
                    empty($dateOfBirth) || empty($email) || empty($contactNumber) || 
                    empty($address) || empty($driverLicenseNumber) || 
                    empty($licenseExpiration) || empty($password)) {
                    throw new Exception('Please fill in all required fields.');
                }
                
                if (!validateEmail($email)) {
                    throw new Exception('Please enter a valid email address.');
                }
                
                if (!validateContact($contactNumber)) {
                    throw new Exception('Please enter a valid contact number.');
                }
                
                // Check if driver is at least 18 years old
                $age = calculateAge($dateOfBirth);
                if ($age < 18) {
                    throw new Exception('Driver must be at least 18 years old.');
                }
                
                // Check if license is not expired
                $licenseExpDate = new DateTime($licenseExpiration);
                $today = new DateTime();
                if ($licenseExpDate < $today) {
                    throw new Exception('Driver license has expired. Please renew your license.');
                }
                
                // Check for duplicate employee number, license, or email
                $checkQuery = "SELECT COUNT(*) FROM Driver WHERE EmployeeNo = ? OR DriverLicenseNumber = ? OR EmailAddress = ?";
                $checkStmt = $pdo->prepare($checkQuery);
                $checkStmt->execute([$employeeNo, $driverLicenseNumber, $email]);
                
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception('Employee number, license number, or email already exists.');
                }
                
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert driver into database
                $sql = "INSERT INTO Driver (FirstName, MiddleName, LastName, EmployeeNo, DateOfBirth, Age, EmailAddress, ContactNumber, Address, DriverLicenseNumber, DriverLicenseExpiration, Password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    $firstName,
                    $middleName,
                    $lastName,
                    $employeeNo,
                    $dateOfBirth,
                    $age,
                    $email,
                    $contactNumber,
                    $address,
                    $driverLicenseNumber,
                    $licenseExpiration,
                    $hashedPassword
                ]);
                
                if ($result) {
                    $response['success'] = true;
                    $response['message'] = 'Driver registration successful! Welcome to the Vehicle Management System.';
                } else {
                    throw new Exception('Registration failed. Please try again.');
                }
                
            } else if ($registration_type === 'supervisor') {
                // Handle Supervisor Registration
                $firstName = sanitizeInput($_POST['firstName']);
                $middleName = sanitizeInput($_POST['middleName']);
                $lastName = sanitizeInput($_POST['lastName']);
                $email = sanitizeInput($_POST['email']);
                $contactNumber = sanitizeInput($_POST['contactNumber']);
                $location = sanitizeInput($_POST['location']);
                $password = $_POST['password'];
                $confirmPassword = $_POST['confirmPassword'];
                
                // Validation
                if (empty($firstName) || empty($lastName) || empty($email) || 
                    empty($contactNumber) || empty($location) || empty($password) || 
                    empty($confirmPassword)) {
                    throw new Exception('Please fill in all required fields.');
                }
                
                if (!validateEmail($email)) {
                    throw new Exception('Please enter a valid email address.');
                }
                
                if (!validateContact($contactNumber)) {
                    throw new Exception('Please enter a valid contact number.');
                }
                
                if ($password !== $confirmPassword) {
                    throw new Exception('Passwords do not match.');
                }
                
                if (strlen($password) < 6) {
                    throw new Exception('Password must be at least 6 characters long.');
                }
                
                // Check for duplicate email
                $checkQuery = "SELECT COUNT(*) FROM Supervisor WHERE Email = ?";
                $checkStmt = $pdo->prepare($checkQuery);
                $checkStmt->execute([$email]);
                
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception('Email address already exists.');
                }
                
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert supervisor into database
                $sql = "INSERT INTO Supervisor (FirstName, MiddleName, LastName, Email, ContactNumber, SupervisorLocation, Password) VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    $firstName,
                    $middleName,
                    $lastName,
                    $email,
                    $contactNumber,
                    $location,
                    $hashedPassword
                ]);
                
                if ($result) {
                    $response['success'] = true;
                    $response['message'] = 'Supervisor registration successful! Welcome to the Vehicle Management System.';
                } else {
                    throw new Exception('Registration failed. Please try again.');
                }
            }
        }
        
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
    }
    
    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            padding: 40px;
            max-width: 800px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header p {
            color: #666;
            font-size: 1.1rem;
        }

        .tab-container {
            display: flex;
            margin-bottom: 30px;
            border-radius: 10px;
            overflow: hidden;
            background: #f8f9fa;
        }

        .tab {
            flex: 1;
            padding: 15px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            border: none;
            background: transparent;
            color: #666;
        }

        .tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .tab:hover:not(.active) {
            background: #e9ecef;
        }

        .form-container {
            display: none;
        }

        .form-container.active {
            display: block;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .required::after {
            content: ' *';
            color: #e74c3c;
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
            border: 1px solid #f5c6cb;
        }

        .age-display {
            display: inline-block;
            margin-left: 10px;
            padding: 5px 10px;
            background: #e9ecef;
            border-radius: 5px;
            font-size: 0.9rem;
            color: #666;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
                margin: 10px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Vehicle Management System</h1>
            <p>Register as Driver or Supervisor</p>
        </div>

        <div class="tab-container">
            <button class="tab active" onclick="switchTab('driver')">Driver Registration</button>
            <button class="tab" onclick="switchTab('supervisor')">Supervisor Registration</button>
        </div>

        <div id="success-message" class="success-message">
            Registration successful! Welcome to the Vehicle Management System.
        </div>

        <div id="error-message" class="error-message">
            Please fill in all required fields correctly.
        </div>

        <!-- Driver Registration Form -->
        <div id="driver-form" class="form-container active">
            <form id="driverRegistrationForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="driverFirstName" class="required">First Name</label>
                        <input type="text" id="driverFirstName" name="firstName" required>
                    </div>
                    <div class="form-group">
                        <label for="driverMiddleName">Middle Name</label>
                        <input type="text" id="driverMiddleName" name="middleName">
                    </div>
                    <div class="form-group">
                        <label for="driverLastName" class="required">Last Name</label>
                        <input type="text" id="driverLastName" name="lastName" required>
                    </div>
                    <div class="form-group">
                        <label for="employeeNo" class="required">Employee Number</label>
                        <input type="text" id="employeeNo" name="employeeNo" required>
                    </div>
                    <div class="form-group">
                        <label for="dateOfBirth" class="required">Date of Birth</label>
                        <input type="date" id="dateOfBirth" name="dateOfBirth" required onchange="calculateAge()">
                        <span id="ageDisplay" class="age-display" style="display: none;"></span>
                    </div>
                    <div class="form-group">
                        <label for="driverEmail" class="required">Email Address</label>
                        <input type="email" id="driverEmail" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="driverContact" class="required">Contact Number</label>
                        <input type="tel" id="driverContact" name="contactNumber" required>
                    </div>
                    <div class="form-group">
                        <label for="driverLicense" class="required">Driver License Number</label>
                        <input type="text" id="driverLicense" name="driverLicenseNumber" required>
                    </div>
                    <div class="form-group">
                        <label for="licenseExpiration" class="required">License Expiration Date</label>
                        <input type="date" id="licenseExpiration" name="licenseExpiration" required>
                    </div>
                    <div class="form-group">
                        <label for="driverPassword" class="required">Password</label>
                        <input type="password" id="driverPassword" name="password" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="driverAddress" class="required">Address</label>
                    <textarea id="driverAddress" name="address" required placeholder="Enter your complete address"></textarea>
                </div>
                <button type="submit" class="submit-btn">Register as Driver</button>
            </form>
        </div>

        <!-- Supervisor Registration Form -->
        <div id="supervisor-form" class="form-container">
            <form id="supervisorRegistrationForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="supervisorFirstName" class="required">First Name</label>
                        <input type="text" id="supervisorFirstName" name="firstName" required>
                    </div>
                    <div class="form-group">
                        <label for="supervisorMiddleName">Middle Name</label>
                        <input type="text" id="supervisorMiddleName" name="middleName">
                    </div>
                    <div class="form-group">
                        <label for="supervisorLastName" class="required">Last Name</label>
                        <input type="text" id="supervisorLastName" name="lastName" required>
                    </div>
                    <div class="form-group">
                        <label for="supervisorEmail" class="required">Email Address</label>
                        <input type="email" id="supervisorEmail" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="supervisorContact" class="required">Contact Number</label>
                        <input type="tel" id="supervisorContact" name="contactNumber" required>
                    </div>
                    <div class="form-group">
                        <label for="supervisorLocation" class="required">Location</label>
                        <select id="supervisorLocation" name="location" required>
                            <option value="">Select Location</option>
                            <option value="Boracay">Boracay</option>
                            <option value="Manila">Manila</option>
                            <option value="Cebu">Cebu</option>
                            <option value="Davao">Davao</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="supervisorPassword" class="required">Password</label>
                        <input type="password" id="supervisorPassword" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirmPassword" class="required">Confirm Password</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" required>
                    </div>
                </div>
                <button type="submit" class="submit-btn">Register as Supervisor</button>
            </form>
        </div>
    </div>

    <script>
        // Switch between tabs
        function switchTab(tabName) {
            // Hide all forms
            document.querySelectorAll('.form-container').forEach(form => {
                form.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected form
            document.getElementById(tabName + '-form').classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
            
            // Hide messages
            hideMessages();
        }

        // Calculate age from date of birth
        function calculateAge() {
            const birthDate = new Date(document.getElementById('dateOfBirth').value);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            const ageDisplay = document.getElementById('ageDisplay');
            if (age && age > 0) {
                ageDisplay.textContent = `Age: ${age} years`;
                ageDisplay.style.display = 'inline-block';
            } else {
                ageDisplay.style.display = 'none';
            }
        }

        // Hide messages
        function hideMessages() {
            document.getElementById('success-message').style.display = 'none';
            document.getElementById('error-message').style.display = 'none';
        }

        // Show success message
        function showSuccess(message) {
            const successDiv = document.getElementById('success-message');
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            document.getElementById('error-message').style.display = 'none';
        }

        // Show error message
        function showError(message) {
            const errorDiv = document.getElementById('error-message');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            document.getElementById('success-message').style.display = 'none';
        }

        // Validate email format
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        // Validate contact number
        function validateContact(contact) {
            const re = /^[\d\s\-\+\(\)]+$/;
            return re.test(contact) && contact.length >= 10;
        }

        // Store registration data (simulated)
        let registrations = {
            drivers: [],
            supervisors: []
        };

        // Handle Driver Registration
            document.getElementById('driverRegistrationForm').addEventListener('submit', function(e) {
                e.preventDefault();
                hideMessages();
                
                const formData = new FormData(this);
                formData.append('registration_type', 'driver');
                
                // Show loading state
                const submitBtn = this.querySelector('.submit-btn');
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Registering...';
                submitBtn.disabled = true;
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccess(data.message);
                        this.reset();
                        document.getElementById('ageDisplay').style.display = 'none';
                    } else {
                        showError(data.message);
                    }
                })
                .catch(error => {
                    showError('Registration failed. Please try again.');
                    console.error('Error:', error);
                })
                .finally(() => {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                });
            });

            // Handle Supervisor Registration
        document.getElementById('supervisorRegistrationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        hideMessages();
        
        const formData = new FormData(this);
        formData.append('registration_type', 'supervisor');
        
        // Show loading state
        const submitBtn = this.querySelector('.submit-btn');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Registering...';
        submitBtn.disabled = true;
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccess(data.message);
                this.reset();
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            showError('Registration failed. Please try again.');
            console.error('Error:', error);
        })
        .finally(() => {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date for date of birth (must be at least 18 years old)
            const today = new Date();
            const maxBirthDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
            document.getElementById('dateOfBirth').max = maxBirthDate.toISOString().split('T')[0];
            
            // Set minimum date for license expiration (must be future date)
            document.getElementById('licenseExpiration').min = today.toISOString().split('T')[0];
        });
    </script>
</body>
</html>