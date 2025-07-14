<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "VehicleManagementSystem";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'driver') {
    header("Location: index.php");
    exit();
}

$driver_id = $_SESSION['user_id'];

// Fetch driver information
$stmt = $pdo->prepare("SELECT FirstName, MiddleName, LastName, EmployeeNo, EmailAddress FROM Driver WHERE DriverID = ?");
$stmt->execute([$driver_id]);
$driver = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$driver) {
    header("Location: index.php");
    exit();
}

// Generate driver initials
$initials = strtoupper(substr($driver['FirstName'], 0, 1) . substr($driver['LastName'], 0, 1));
$full_name = $driver['FirstName'] . ' ' . $driver['MiddleName'] . ' ' . $driver['LastName'];

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $plate_number = strtoupper(trim($_POST['plate_number']));
        $model = trim($_POST['model']);
        $chassis_number = trim($_POST['chassis_number']);
        $start_odometer = floatval($_POST['start_odometer']);
        $remarks = trim($_POST['remarks']);
        
        // Vehicle inspection checklist
        $checklist_items = [
            'battery' => isset($_POST['battery']),
            'lights' => isset($_POST['lights']),
            'oil' => isset($_POST['oil']),
            'water' => isset($_POST['water']),
            'brakes' => isset($_POST['brakes']),
            'air' => isset($_POST['air']),
            'gas' => isset($_POST['gas']),
            'engine' => isset($_POST['engine']),
            'tires' => isset($_POST['tires']),
            'self' => isset($_POST['self'])
        ];
        
        // Check if at least one checklist item is checked
        if (!array_filter($checklist_items)) {
            throw new Exception("Please complete the vehicle inspection checklist before submitting.");
        }
        
        // Validate required fields
        if (empty($plate_number) || empty($model) || empty($chassis_number) || empty($start_odometer)) {
            throw new Exception("Please fill in all required fields.");
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if vehicle exists, if not create it
        $stmt = $pdo->prepare("SELECT VehicleID FROM Vehicle WHERE PlateNumber = ?");
        $stmt->execute([$plate_number]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vehicle) {
            // Create new vehicle
            $stmt = $pdo->prepare("INSERT INTO Vehicle (PlateNumber, Model, ChassisNumber) VALUES (?, ?, ?)");
            $stmt->execute([$plate_number, $model, $chassis_number]);
            $vehicle_id = $pdo->lastInsertId();
        } else {
            $vehicle_id = $vehicle['VehicleID'];
            
            // Update vehicle details if needed
            $stmt = $pdo->prepare("UPDATE Vehicle SET Model = ?, ChassisNumber = ? WHERE VehicleID = ?");
            $stmt->execute([$model, $chassis_number, $vehicle_id]);
        }
        
        // Check if driver has an active route
        $stmt = $pdo->prepare("SELECT RouteID FROM Route WHERE DriverID = ? AND EndDateTime IS NULL");
        $stmt->execute([$driver_id]);
        $active_route = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($active_route) {
            throw new Exception("You have an active route. Please end the current route before starting a new one.");
        }
        
        // Create new route
        $stmt = $pdo->prepare("INSERT INTO Route (DriverID, VehicleID, StartOdometer, StartDateTime) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$driver_id, $vehicle_id, $start_odometer]);
        $route_id = $pdo->lastInsertId();
        
        // Add odometer reading
        $stmt = $pdo->prepare("INSERT INTO OdometerReadings (VehicleID, OdometerReading) VALUES (?, ?)");
        $stmt->execute([$vehicle_id, $start_odometer]);
        
        // Store checklist and remarks in session for potential future use
        $_SESSION['last_checklist'] = $checklist_items;
        $_SESSION['last_remarks'] = $remarks;
        
        $debugStmt = $pdo->prepare("DESCRIBE Vehicle");
$debugStmt->execute();
$columns = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
error_log("Vehicle table columns: " . print_r($columns, true));

// Also check what's actually in the database
$debugStmt2 = $pdo->prepare("SELECT * FROM Vehicle WHERE PlateNumber = ? LIMIT 1");
$debugStmt2->execute([$plate_number]);
$debugVehicle = $debugStmt2->fetch(PDO::FETCH_ASSOC);
error_log("Raw vehicle data from database: " . print_r($debugVehicle, true));
          
        $pdo->commit();
        
        $success_message = "Route started successfully! Route ID: " . $route_id;
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error_message = $e->getMessage();
    }
}

// Get vehicles for suggestions (optional)
$stmt = $pdo->prepare("SELECT DISTINCT PlateNumber, Model FROM Vehicle ORDER BY PlateNumber");
$stmt->execute();
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Replace your existing GET vehicle endpoint with this corrected version:

if (isset($_GET['action']) && $_GET['action'] === 'get_vehicle' && isset($_GET['plate_number'])) {
    header('Content-Type: application/json');
    
    $plate_number = strtoupper(trim($_GET['plate_number']));
    
    // Debug: Log the plate number being searched
    error_log("Searching for plate number: " . $plate_number);
    
    try {
        // Make sure we're selecting the correct column name from the database
        $stmt = $pdo->prepare("SELECT PlateNumber, Model, ChassisNumber FROM Vehicle WHERE PlateNumber = ?");
        $stmt->execute([$plate_number]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug: Log the query result
        error_log("Query result: " . json_encode($vehicle));
        
        if ($vehicle) {
            // Debug: Check what we actually got from database
            error_log("Raw vehicle data: " . print_r($vehicle, true));
            
            // Clean up the chassis number - remove any invalid data
            $chassisNumber = $vehicle['ChassisNumber'] ?? '';
            
            // Check if chassis number looks like a date (invalid data)
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $chassisNumber)) {
                $chassisNumber = ''; // Clear invalid data
            }
            
            $response = [
                'success' => true,
                'data' => [
                    'PlateNumber' => $vehicle['PlateNumber'] ?? '',
                    'Model' => $vehicle['Model'] ?? '',
                    'ChassisNumber' => $chassisNumber
                ]
            ];
            
            error_log("Cleaned chassis number: " . $chassisNumber);
            error_log("Sending response: " . json_encode($response));
            echo json_encode($response);
        } else {
            $response = [
                'success' => false,
                'message' => 'Vehicle not found'
            ];
            error_log("Vehicle not found for plate: " . $plate_number);
            echo json_encode($response);
        }
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
        error_log("Database error: " . $e->getMessage());
        echo json_encode($response);
    }
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>New Route</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      zoom:1.2;
      font-family: 'Inter', sans-serif;
      background-color: #f5f5f5;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      min-height: 100vh;
      padding: 0;
    }

    .container {
      width: 360px;
      background-color: #fff;
      padding: 10px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
      min-height: 100vh;
    }

    .header {
      text-align: center;
      font-weight: bold;
      color: #2f9e44;
      padding: 10px 0;
      position: relative;
    }

    .header i {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #333;
    }

    .profile-card {
      background: #f5f5f5;
      border-radius: 16px;
      padding: 20px;
      text-align: center;
      margin-top: 10px;
    }

    .profile-circle {
      width: 60px;
      height: 60px;
      background: #ccc;
      border-radius: 50%;
      margin: 0 auto 10px;
      font-weight: 600;
      font-size: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #333;
    }

    .profile-name {
      font-weight: 600;
      font-size: 18px;
      margin-bottom: 4px;
    }

    .profile-info {
      font-size: 13px;
      color: #666;
      font-weight: 400;
    }

    .section {
      background: #f2f2f2;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 10px;
    }
    .section i {
        color: #299149;
    }

    .section-title {
      text-align: center;
      font-weight: 600;
      color: #2f9e44;
      margin-bottom: 10px;
    }

    .form-group {
      margin-bottom: 15px;
    }

    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-size: 14px;
      color: #333;
      font-weight: 300;
    }

    .form-group input {
      width: 100%;
      padding: 10px;
      border: 1px solid #299149;
      border-left: 3px solid #299149;
      border-radius: 6px;
      font-size: 14px;
      color: #333;
      font-family: 'Inter', sans-serif;
      background: #fff;
      box-sizing: border-box;
    }

    .form-group input:focus {
      outline: none;
      border-color: #2f9e44;
    }

    .form-group input:disabled {
      background: #f8f9fa;
      color: #999;
    }

    .form-group input::placeholder {
      color: #999;
      font-style: italic;
    }

    .checklist-container {
        border-radius: 6px;
        border: 1px solid #299149;
        border-left: 3px solid #299149;
      margin-bottom: 15px;
    }

    .checklist-title {
        text-align: center;
      font-weight: 300;
      color: #333;
      margin-bottom: 10px;
      font-size: 14px;
    }

    .checklist {
      border: 1px solid #ddd;
      border-radius: 6px;
      padding: 10px;
      background: #fff;
    }

    .checkbox-item {
      display: flex;
      align-items: center;
      margin-bottom: 10px;
    }

    .checkbox-item:last-child {
      margin-bottom: 0;
    }

    .checkbox-item input[type="checkbox"] {
      width: 18px;
      height: 18px;
      margin-right: 10px;
      cursor: pointer;
    }

    .checkbox-item label {
      font-size: 14px;
      color: #333;
      cursor: pointer;
      flex: 1;
      margin: 0;
      font-weight: 400;
    }

    .submit-btn {
      width: 100%;
      padding: 12px;
      background-color: #2f9e44;
      color: white;
      border: none;
      font-weight: 600;
      border-radius: 6px;
      font-size: 14px;
      font-family: 'Inter', sans-serif;
      cursor: pointer;
      margin-top: 10px;
    }

    .submit-btn:hover {
      background-color: #268a3c;
      transform: scale(0.98);
    }

    .sidebar {
      position: fixed;
      top: 0;
      left: -250px;
      width: 220px;
      height: 100%;
      background-color: white;
      color: #2f9e44;
      padding: 20px 15px;
      transition: left 0.3s ease-in-out;
      z-index: 1000;
      box-shadow: 2px 0 5px rgba(0,0,0,0.1);
      display: flex;
      flex-direction: column;
      overflow-y: auto;
    }

    .sidebar-logo {
      text-align: center;
      margin-bottom: 20px;
      flex-shrink: 0;
    }

    .sidebar-logo img {
      width: 100px;
      max-width: 80%;
      height: auto;
      border-radius: 8px;
    }

    .sidebar-content {
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .sidebar-nav {
      flex: 1;
    }

.sidebar-footer {
  flex-shrink: 0;
  margin-top: auto;
  text-align: center;
  font-size: 12px;
  color: rgba(30, 30, 30, 0.64);
  padding: 100px 0;
  border-top: 1px solid rgba(63, 61, 61, 0.2);
}

    .sidebar.active {
      left: 0;
    }

.sidebar a {
  display: block;
  color: #2f9e44;
  padding: 10px 0;
  text-decoration: none;
  font-weight: 500;
  font-size: 13px;
}

.sidebar a:hover {
  background-color: rgba(180, 185, 181, 0.28);
  color: #299149;
}
.sidebar a i {
  margin-right: 10px;
  width: 16px;
  text-align: center;
}

    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      height: 100%;
      width: 100%;
      background: rgba(0,0,0,0.4);
      z-index: 999;
      display: none;
    }

    .overlay.active {
      display: block;
    }

    .report-problem {
  display: inline-block;
  background-color:#a8432f;
  color: white;

  padding: 6px 10px;
  border: none;
  border-radius: 4px;
  font-size: 12px;
  font-family: 'Inter', sans-serif;
  cursor: pointer;
  transition: background-color 0.3s ease, transform 0.2s ease;
  box-shadow: 0 4px 10px rgba(255, 77, 79, 0.3);
}

.report-problem i {
  margin-right: 6px;
}

.report-problem:hover {
  background-color: #a8432f;
  transform: translateY(-2px);
}
i:hover {
  transform: translateY(-2px);
}
#bars:hover {
  transform: translateY(-10px);
}

.report-problem:active {
  transform: scale(0.98);
}

    .alert {
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 6px;
      font-size: 14px;
    }

    .alert-success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .alert-error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .vehicle-suggestions {
      position: relative;
    }

    .suggestions-list {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #ddd;
      border-radius: 6px;
      max-height: 150px;
      overflow-y: auto;
      z-index: 100;
      display: none;
    }

    .suggestion-item {
      padding: 10px;
      cursor: pointer;
      border-bottom: 1px solid #eee;
      font-size: 14px;
    }

    .suggestion-item:hover {
      background-color: #f8f9fa;
    }

    .suggestion-item:last-child {
      border-bottom: none;
    }

     @media (max-width: 768px) {
        body {
            background-color: #fff;
            
        }
    .container {
      width: 360px;
      background-color: #fff;
      padding: 5px 5px 5px 5px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0);
      min-height: 100vh;
    }
        }
  </style>
</head>
<body>
<div class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <img src="img/logo1.png" alt="Company Logo">
  </div>
<a href="driver.php"><i class="fas fa-home"></i> Home</a>
<a href="new_route.php"><i class="fas fa-plus"></i> New Route</a>
<a href="driver_history.php"><i class="fas fa-history"></i> History</a>
<a href="report_vehicle_issue.php"><i class="fas fa-exclamation-triangle"></i> Report Vehicle Issue</a>

<a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
<br><br>
<a href="#"><i class="fas fa-info-circle"></i> Report System Issue</a>

  <div class="sidebar-footer">
    All rights reserved © <span id="current-year"></span>
  </div>
</div>
      

<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

  <div class="container">
    <div class="header">
      <i class="fas fa-bars" id="bars" onclick="toggleSidebar()"></i>
      NEW ROUTE
    </div>

    <div class="profile-card">
      <div class="profile-circle"><?php echo $initials; ?></div>
      <div class="profile-name"><?php echo htmlspecialchars($full_name); ?></div>
      <div class="profile-info">Employee ID: <?php echo htmlspecialchars($driver['EmployeeNo']); ?></div>
      <div class="profile-info">Email: <?php echo htmlspecialchars($driver['EmailAddress']); ?></div>
    </div>  
<br>

    <?php if ($success_message): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
      </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
      <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="new_route.php" id="routeForm">
      <div class="section">
          <i class="fas fa-arrow-left" onclick="history.back(); return false;"></i>
        <div class="section-title">Vehicle Details</div>

        <div class="form-group vehicle-suggestions">
          <label>Plate no. *</label>
          <input type="text" name="plate_number" id="plate_number" placeholder="// ex. ABG6556 (no spacing)" required autocomplete="off" />
          <div class="suggestions-list" id="plateSuggestions"></div>
        </div>

        <div class="form-group">
          <label>Model *</label>
          <input type="text" name="model" id="model" placeholder="// ex. Toyota HI-ACE" required />
        </div>

        <div class="form-group">
          <label>Body no. *</label>
          <input type="text" name="chassis_number" id="chassis_number" placeholder="// enter chassis no." required />
        </div>
        
        <div class="form-group">
          <label>Start Odometer Reading (km) *</label>
          <input type="number" name="start_odometer" step="0.01" placeholder="// follow this format: 10293" required />
        </div>
        
        <div class="form-group">
          <label>Time and Date (automatic)</label>
          <input type="text" id="datetime" placeholder="// Automatic (uneditable)" disabled />
        </div>
<br>
<div class="checklist-title">Vehicle Inspection Checklist</div>
        <div class="checklist-container">
          <div class="checklist">
            <div class="checkbox-item">
              <input type="checkbox" name="battery" id="battery" />
              <label for="battery"><i class="fas fa-battery-full"></i> Battery</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="lights" id="lights" />
              <label for="lights"><i class="fas fa-lightbulb"></i> Lights</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="oil" id="oil" />
              <label for="oil"><i class="fas fa-oil-can"></i> Oil</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="water" id="water" />
              <label for="water"><i class="fas fa-tint"></i> Water</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="brakes" id="brakes" />
              <label for="brakes"><i class="fas fa-hand-paper"></i> Brakes</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="air" id="air" />
              <label for="air"><i class="fas fa-wind"></i> Air</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="gas" id="gas" />
              <label for="gas"><i class="fas fa-gas-pump"></i> Gas</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="engine" id="engine" />
              <label for="engine"><i class="fas fa-gears"></i> Engine</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="tires" id="tires" />
              <label for="tires"><i class="fas fa-compact-disc"></i> Tires</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="self" id="self" />
              <label for="self"><i class="fas fa-user-check"></i> Self</label>
            </div>
          </div>
        </div>
        <div class="form-group">
          <label>Remarks:</label>
          <input type="text" name="remarks" placeholder="// leave blank if none" />
        </div>
        <button type="submit" class="submit-btn">START ROUTE</button>
      </div>
    </form>

<div style="text-align:center; margin-top: 30px;">
  <p style="font-size: 12px; color: #333; margin-bottom: 10px;">Found problem in vehicle?</p>
  <button class="btn report-problem" onclick="window.location.href='report_vehicle_issue.php'"><i class="fas fa-exclamation-triangle" style="color: white;"></i>send report</button>
</div>

<br><br>
  </div>

  <script>
    // Vehicle data for suggestions
    const vehicles = <?php echo json_encode($vehicles); ?>;
    
    // Auto-populate current date and time
    const timeInput = document.getElementById('datetime');
    const now = new Date();
    const formattedDateTime = now.toLocaleString('en-US', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: true
    });
    timeInput.value = formattedDateTime;

    // Auto-suggestions for plate number
    const plateInput = document.getElementById('plate_number');
    const modelInput = document.getElementById('model');
    const chassisInput = document.getElementById('chassis_number');
    const suggestions = document.getElementById('plateSuggestions');

    // Replace the existing plateInput.addEventListener('input', function() { ... }) with this:

    plateInput.addEventListener('input', function() {
      const value = this.value.toUpperCase();
      this.value = value; // Convert to uppercase as user types
      
      suggestions.innerHTML = '';
      
      if (value.length > 0) {
        const filtered = vehicles.filter(vehicle => 
          vehicle.PlateNumber.includes(value)
        );
        
        if (filtered.length > 0) {
          suggestions.style.display = 'block';
          filtered.forEach(vehicle => {
            const div = document.createElement('div');
            div.className = 'suggestion-item';
            div.textContent = `${vehicle.PlateNumber} - ${vehicle.Model}`;
            div.addEventListener('click', function() {
              plateInput.value = vehicle.PlateNumber;
              modelInput.value = vehicle.Model;
              suggestions.style.display = 'none';
              // Auto-populate chassis number when suggestion is clicked
              fetchVehicleData(vehicle.PlateNumber);
            });
            suggestions.appendChild(div);
          });
        } else {
          suggestions.style.display = 'none';
        }
      } else {
        suggestions.style.display = 'none';
      }
    });

    // Add this new event listener for when plate number is typed/pasted directly
    plateInput.addEventListener('blur', function() {
      const plateNumber = this.value.trim();
      if (plateNumber.length > 0) {
        fetchVehicleData(plateNumber);
      }
    });

    // Replace your fetchVehicleData function with this debug version:

    // Replace your existing fetchVehicleData function with this corrected version:

function fetchVehicleData(plateNumber) {
    console.log('Fetching data for plate:', plateNumber); // Debug log
    
    fetch(`new_route.php?action=get_vehicle&plate_number=${encodeURIComponent(plateNumber)}`)
        .then(response => {
            console.log('Response status:', response.status); // Debug log
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Received data:', data); // Debug log
            
            if (data.success && data.data) {
                console.log('Vehicle data:', data.data); // Debug log
                console.log('ChassisNumber from response:', data.data.ChassisNumber); // Debug log
                
                // Auto-populate the fields - make sure we're accessing the correct property
                modelInput.value = data.data.Model || '';
                chassisInput.value = data.data.ChassisNumber || '';
                
                // Optional: Show a subtle indication that data was loaded
                modelInput.style.backgroundColor = '#f0f8ff';
                chassisInput.style.backgroundColor = '#f0f8ff';
                
                // Remove the background color after 2 seconds
                setTimeout(() => {
                    modelInput.style.backgroundColor = '';
                    chassisInput.style.backgroundColor = '';
                }, 2000);
                
                console.log('Fields populated - Model:', modelInput.value, 'Chassis:', chassisInput.value);
            } else {
                console.log('Vehicle not found or invalid response:', data.message || 'Unknown error');
                // If vehicle not found, clear the model and chassis fields
                modelInput.value = '';
                chassisInput.value = '';
            }
        })
        .catch(error => {
            console.error('Error fetching vehicle data:', error);
            // Don't clear fields on error - let user manually input
        });
}

    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.vehicle-suggestions')) {
        suggestions.style.display = 'none';
      }
    });

    // Form validation
    document.getElementById('routeForm').addEventListener('submit', function(e) {
      const checkboxes = document.querySelectorAll('input[type="checkbox"]');
      const checkedItems = Array.from(checkboxes).filter(cb => cb.checked);
      
      if (checkedItems.length === 0) {
        e.preventDefault();
        alert('Please complete the vehicle inspection checklist before submitting.');
        return false;
      }
    });

    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('overlay');
      sidebar.classList.toggle('active');
      overlay.classList.toggle('active');
    }

    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('current-year').textContent = new Date().getFullYear();
    });
  </script>
</body>
</html>