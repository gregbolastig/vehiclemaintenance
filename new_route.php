<?php
session_start();

// Include database connection
require_once('dbconnection.php');

// Check if user is logged in as driver
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'driver') {
    header("Location: login.php");
    exit();
}

$driver_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get driver information
try {
    $stmt = $conn->prepare("SELECT * FROM Driver WHERE DriverID = ?");
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $driver = $result->fetch_assoc();
    
    if (!$driver) {
        header("Location: login.php");
        exit();
    }
    
    // Create initials for profile
    $initials = strtoupper(substr($driver['FirstName'], 0, 1) . substr($driver['LastName'], 0, 1));
    $full_name = $driver['FirstName'] . ' ' . $driver['LastName'];
} catch(Exception $e) {
    $error_message = "Error fetching driver information: " . $e->getMessage();
}

// Get all vehicles for suggestions
$vehicles = [];
try {
    $stmt = $conn->prepare("SELECT PlateNumber, Model, ChassisNumber FROM Vehicle ORDER BY PlateNumber");
    $stmt->execute();
    $result = $stmt->get_result();
    $vehicles = $result->fetch_all(MYSQLI_ASSOC);
} catch(Exception $e) {
    $error_message = "Error fetching vehicles: " . $e->getMessage();
}

// Handle AJAX request for vehicle data
if (isset($_GET['action']) && $_GET['action'] === 'get_vehicle' && isset($_GET['plate_number'])) {
    $plate_number = trim($_GET['plate_number']);
    
    try {
        $stmt = $conn->prepare("SELECT Model, ChassisNumber FROM Vehicle WHERE PlateNumber = ?");
        $stmt->bind_param("s", $plate_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $vehicle = $result->fetch_assoc();
        
        if ($vehicle) {
            echo json_encode(['success' => true, 'data' => $vehicle]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
        }
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $plate_number = trim($_POST['plate_number']);
        $model = trim($_POST['model']);
        $chassis_number = trim($_POST['chassis_number']);
        $start_odometer = floatval($_POST['start_odometer']);
        $remarks = trim($_POST['remarks'] ?? '');
        
        // Get inspection checklist data
        $inspection_battery = isset($_POST['battery']) ? 1 : 0;
        $inspection_lights = isset($_POST['lights']) ? 1 : 0;
        $inspection_oil = isset($_POST['oil']) ? 1 : 0;
        $inspection_water = isset($_POST['water']) ? 1 : 0;
        $inspection_brakes = isset($_POST['brakes']) ? 1 : 0;
        $inspection_air = isset($_POST['air']) ? 1 : 0;
        $inspection_gas = isset($_POST['gas']) ? 1 : 0;
        $inspection_engine = isset($_POST['engine']) ? 1 : 0;
        $inspection_tires = isset($_POST['tires']) ? 1 : 0;
        $inspection_self = isset($_POST['self']) ? 1 : 0;
        
        // Validate required fields
        if (empty($plate_number) || empty($model) || empty($chassis_number) || $start_odometer <= 0) {
            throw new Exception("All required fields must be filled.");
        }
        
        // Check if all inspection items are checked
        $inspection_items = [
            $inspection_battery, $inspection_lights, $inspection_oil, $inspection_water,
            $inspection_brakes, $inspection_air, $inspection_gas, $inspection_engine,
            $inspection_tires, $inspection_self
        ];
        
        if (array_sum($inspection_items) < 10) {
            throw new Exception("Please complete the full vehicle inspection checklist.");
        }
        
        // Start transaction
        $conn->autocommit(false);
        
        // Check if vehicle exists, if not create it
        $stmt = $conn->prepare("SELECT VehicleID FROM Vehicle WHERE PlateNumber = ?");
        $stmt->bind_param("s", $plate_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $vehicle = $result->fetch_assoc();
        
        if (!$vehicle) {
            // Create new vehicle
            $stmt = $conn->prepare("INSERT INTO Vehicle (PlateNumber, Model, ChassisNumber) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $plate_number, $model, $chassis_number);
            $stmt->execute();
            $vehicle_id = $conn->insert_id;
        } else {
            $vehicle_id = $vehicle['VehicleID'];
            
            // Update vehicle information if needed
            $stmt = $conn->prepare("UPDATE Vehicle SET Model = ?, ChassisNumber = ? WHERE VehicleID = ?");
            $stmt->bind_param("ssi", $model, $chassis_number, $vehicle_id);
            $stmt->execute();
        }
        
        // Check if driver already has an active route
        $stmt = $conn->prepare("SELECT RouteID FROM Route WHERE DriverID = ? AND EndDateTime IS NULL");
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $active_route = $result->fetch_assoc();
        
if ($active_route) {
    // Display an alert and redirect
    echo "<script>
        alert('You already have an active route. Please end your current route before starting a new one.');
        window.location.href = 'driver'; // Redirect to a relevant page
    </script>";
    exit();
}
        
        // Insert new route
        $stmt = $conn->prepare("
            INSERT INTO Route (
                DriverID, VehicleID, StartOdometer, StartDateTime,
                InspectionBattery, InspectionLights, InspectionOil, InspectionWater,
                InspectionBrakes, InspectionAir, InspectionGas, InspectionEngine,
                InspectionTires, InspectionSelf, InspectionRemarks
            ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("iidiiiiiiiiiis", 
            $driver_id, $vehicle_id, $start_odometer,
            $inspection_battery, $inspection_lights, $inspection_oil, $inspection_water,
            $inspection_brakes, $inspection_air, $inspection_gas, $inspection_engine,
            $inspection_tires, $inspection_self, $remarks
        );
        $stmt->execute();
        
        $route_id = $conn->insert_id;
        
        // Insert odometer reading
        $stmt = $conn->prepare("INSERT INTO OdometerReadings (VehicleID, OdometerReading) VALUES (?, ?)");
        $stmt->bind_param("id", $vehicle_id, $start_odometer);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        $conn->autocommit(true);
        
        $success_message = "Route started successfully! Route ID: " . $route_id;
               echo "<script>
            alert('" . addslashes($success_message) . "');
            window.location.href = 'driver.php';
        </script>";
        exit();
        // Store route ID in session for later use
        $_SESSION['active_route_id'] = $route_id;
        
    } catch(Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $conn->autocommit(true);
        $error_message = $e->getMessage();
    }
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

<form method="POST" id="routeForm">

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
          <label>Chassis no. *</label>
          <input type="text" name="chassis_number" id="chassis_number" placeholder="// enter chassis no." required />
        </div>
        
        <div class="form-group">
          <label>Start Odometer Reading (km) *</label>
          <input type="number" name="start_odometer" step="0.01" placeholder="// follow this format: 10293" required />
        </div>
        
        <div class="form-group">
          <label>Time and Date (automatic)</label>
          <input type="text" id="datetime" style="color: black;" placeholder="// Automatic (uneditable)" disabled />
        </div>
<br>
<div class="checklist-title">Vehicle Inspection Checklist</div>
        <div class="checklist-container">
          <div class="checklist">
            <div class="checkbox-item">
              <input type="checkbox" name="battery" id="battery" required/>
              <label for="battery"><i class="fas fa-battery-full"></i> Battery</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="lights" id="lights" required/>
              <label for="lights"><i class="fas fa-lightbulb"></i> Lights</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="oil" id="oil" required/>
              <label for="oil"><i class="fas fa-oil-can"></i> Oil</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="water" id="water" required/>
              <label for="water"><i class="fas fa-tint"></i> Water</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="brakes" id="brakes" required/>
              <label for="brakes"><i class="fas fa-hand-paper"></i> Brakes</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="air" id="air" required/>
              <label for="air"><i class="fas fa-wind"></i> Air</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="gas" id="gas" required/>
              <label for="gas"><i class="fas fa-gas-pump"></i> Gas</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="engine" id="engine" required/>
              <label for="engine"><i class="fas fa-gears"></i> Engine</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="tires" id="tires" required/>
              <label for="tires"><i class="fas fa-compact-disc"></i> Tires</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="self" id="self" required/>
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
    
    // Get references to the input fields (define them globally)
    const plateInput = document.getElementById('plate_number');
    const modelInput = document.getElementById('model');
    const chassisInput = document.getElementById('chassis_number');
    const suggestions = document.getElementById('plateSuggestions');
    
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

    // Debounce function to prevent too many API calls
    function debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }

    // Function to fetch vehicle data from the server
    function fetchVehicleData(plateNumber) {
      console.log('Fetching data for plate:', plateNumber);
      
      fetch(`new_route.php?action=get_vehicle&plate_number=${encodeURIComponent(plateNumber)}`)
        .then(response => {
          console.log('Response status:', response.status);
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .then(data => {
          console.log('Received data:', data);
          
          if (data.success && data.data) {
            // Auto-populate the fields
            modelInput.value = data.data.Model || '';
            chassisInput.value = data.data.ChassisNumber || '';
            
            // Visual feedback
            if (data.data.Model) {
              modelInput.classList.add('auto-filled');
              setTimeout(() => modelInput.classList.remove('auto-filled'), 3000);
            }
            
            if (data.data.ChassisNumber) {
              chassisInput.classList.add('auto-filled');
              setTimeout(() => chassisInput.classList.remove('auto-filled'), 3000);
            }
            
            console.log('Fields populated - Model:', modelInput.value, 'Chassis:', chassisInput.value);
          } else {
            console.log('Vehicle not found or invalid response:', data.message || 'Unknown error');
            // Clear fields if vehicle not found
            modelInput.value = '';
            chassisInput.value = '';
          }
        })
        .catch(error => {
          console.error('Error fetching vehicle data:', error);
        });
    }

    // Debounced version of fetchVehicleData
    const debouncedFetchVehicleData = debounce(fetchVehicleData, 500);

    // Auto-suggestions for plate number
    plateInput.addEventListener('input', function() {
      const value = this.value.toUpperCase();
      this.value = value; // Convert to uppercase as user types
      
      suggestions.innerHTML = '';
      
      // Clear model and chassis if plate is empty
      if (value.length === 0) {
        modelInput.value = '';
        chassisInput.value = '';
        suggestions.style.display = 'none';
        return;
      }
      
      // Show suggestions
      if (value.length > 0) {
        const filtered = vehicles.filter(vehicle => 
          vehicle.PlateNumber.includes(value)
        );
        
        if (filtered.length > 0) {
          suggestions.style.display = 'block';
          filtered.forEach(vehicle => {
            const div = document.createElement('div');
            div.className = 'suggestion-item';
            div.innerHTML = `
              <strong>${vehicle.PlateNumber}</strong> - ${vehicle.Model}
              <br><small style="color: #666;">Chassis: ${vehicle.ChassisNumber || 'Not available'}</small>
            `;
            div.addEventListener('click', function() {
              plateInput.value = vehicle.PlateNumber;
              modelInput.value = vehicle.Model;
              chassisInput.value = vehicle.ChassisNumber || '';
              suggestions.style.display = 'none';
            });
            suggestions.appendChild(div);
          });
        } else {
          suggestions.style.display = 'none';
        }
      }
      
      // Fetch vehicle data if plate number is long enough
      if (value.length >= 3) {
        debouncedFetchVehicleData(value);
      }
    });

    // Also check on blur (when user leaves the field)
    plateInput.addEventListener('blur', function() {
      const plateNumber = this.value.trim();
      if (plateNumber.length > 0) {
        fetchVehicleData(plateNumber);
      }
    });

    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.vehicle-suggestions')) {
        suggestions.style.display = 'none';
      }
    });

    // Form validation
document.querySelector('form').addEventListener('submit', function(e) {
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