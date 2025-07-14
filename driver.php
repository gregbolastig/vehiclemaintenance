<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'driver') {
    header("Location: index.php");
    exit();
}

// Database configuration - use consistent connection
require_once('dbconnection.php');

// Get driver information using the correct session variable
$driver_id = $_SESSION['user_id'];
$driver_query = "SELECT * FROM Driver WHERE DriverID = ?";
$driver_stmt = $conn->prepare($driver_query);
$driver_stmt->bind_param("i", $driver_id);
$driver_stmt->execute();
$driver_result = $driver_stmt->get_result();
$driver = $driver_result->fetch_assoc();

if (!$driver) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Get current/in-progress routes
$current_routes_query = "
    SELECT r.*, v.PlateNumber, v.Model 
    FROM Route r
    JOIN Vehicle v ON r.VehicleID = v.VehicleID
    WHERE r.DriverID = ? AND r.EndDateTime IS NULL
    ORDER BY r.StartDateTime DESC
";
$current_routes_stmt = $conn->prepare($current_routes_query);
$current_routes_stmt->bind_param("i", $driver_id);
$current_routes_stmt->execute();
$current_routes_result = $current_routes_stmt->get_result();
$current_routes = $current_routes_result->fetch_all(MYSQLI_ASSOC);

// Get completed routes (recent 5)
$completed_routes_query = "
    SELECT r.*, v.PlateNumber, v.Model 
    FROM Route r
    JOIN Vehicle v ON r.VehicleID = v.VehicleID
    WHERE r.DriverID = ? AND r.EndDateTime IS NOT NULL
    ORDER BY r.EndDateTime DESC
    LIMIT 5
";
$completed_routes_stmt = $conn->prepare($completed_routes_query);
$completed_routes_stmt->bind_param("i", $driver_id);
$completed_routes_stmt->execute();
$completed_routes_result = $completed_routes_stmt->get_result();
$completed_routes = $completed_routes_result->fetch_all(MYSQLI_ASSOC);

// Function to get driver initials
function getDriverInitials($firstName, $lastName) {
    return strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
}

// Function to format date/time
function formatDateTime($datetime) {
    return date('g:i A', strtotime($datetime));
}

function formatDate($datetime) {
    return date('m/d/Y', strtotime($datetime));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Driver Dashboard</title>
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

    .buttons {
      display: flex;
      gap: 10px;
      margin: 15px 0;
      justify-content: center;
    }

    .btn {
      flex: 1;
      padding: 10px;
      border-radius: 6px;
      font-weight: 600;
      color: #fff;
      border: none;
      cursor: pointer;
      font-family: 'Inter', sans-serif;
    }

    .btn.new-route {
      background-color: #2f9e44;
    }

    .btn.report-problem {
      background-color: #a8432f;
    }

    .section {
      background: #f2f2f2;
      border-radius: 10px;
      padding: 10px;
      margin-bottom: 10px;
    }

    .section-title {
      text-align: center;
      font-weight: 600;
      color: #2f9e44;
      margin-bottom: 10px;
    }

    .card {
      background-color: #ffa726;
      color: #000;
      padding: 10px;
      border-radius: 10px;
      margin-bottom: 5px;
    }

    .card.green {
      background-color: #8ef58e;
    }

    .card-header {
      display: flex;
      justify-content: space-between;
      font-weight: 600;
    }

    .card-details {
      font-size: 13px;
      margin-top: 6px;
      font-weight: 400;
    }

    .completed {
      color: green;
      font-weight: 600;
      float: right;
      font-size: 12px;
    }

    .no-routes {
      text-align: center;
      color: #666;
      font-style: italic;
      padding: 20px;
    }

    hr {
      border: none;
      border-top: 1px solid #ddd;
      margin: 20px 0;
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
      background-color:rgba(180, 185, 181, 0.28);
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

    button:active {
      transform: scale(0.98);
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
  <a href="report_system_issue.php"><i class="fas fa-info-circle"></i> Report System Issue</a>

  <div class="sidebar-footer">
    All rights reserved © <span id="current-year"></span>
  </div>
</div>

<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<div class="container">
  <div class="header">
    <i class="fas fa-bars" onclick="toggleSidebar()"></i>
    DRIVER DASHBOARD
  </div>

  <div class="profile-card">
    <div class="profile-circle">
      <?php echo getDriverInitials($driver['FirstName'], $driver['LastName']); ?>
    </div>
    <div class="profile-name">
      <?php echo htmlspecialchars($driver['FirstName'] . ' ' . 
                                  ($driver['MiddleName'] ? $driver['MiddleName'] . ' ' : '') . 
                                  $driver['LastName']); ?>
    </div>
    <div class="profile-info">Employee ID: <?php echo htmlspecialchars($driver['EmployeeNo']); ?></div>
    <div class="profile-info">Email: <?php echo htmlspecialchars($driver['EmailAddress']); ?></div>
  </div>

  <div class="buttons">
    <button class="btn new-route" onclick="window.location.href='new_route.php'">
      <i class="fas fa-plus"></i> New Route
    </button>
    <button class="btn report-problem" onclick="window.location.href='report_vehicle_issue.php'">
      <i class="fas fa-exclamation-triangle"></i> Report Problem
    </button>
  </div>

  <div class="section">
    <div class="section-title">IN PROGRESS</div>
    <?php if (count($current_routes) > 0): ?>
      <?php foreach ($current_routes as $route): ?>
        <div class="card">
          <div class="card-header">
            <span><?php echo htmlspecialchars($route['Model']); ?></span>
            <span>Time: <?php echo formatDateTime($route['StartDateTime']); ?></span>
          </div>
          <div class="card-details">
            Plate Number: <?php echo htmlspecialchars($route['PlateNumber']); ?><br />
            Date: <?php echo formatDate($route['StartDateTime']); ?><br />
            Start odometer: <?php echo number_format($route['StartOdometer'], 0); ?><br />
            End odometer: -
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="no-routes">No active routes</div>
    <?php endif; ?>
  </div>

  <hr />

  <div class="section">
    <div class="section-title">ROUTE HISTORY</div>
    <?php if (count($completed_routes) > 0): ?>
      <?php foreach ($completed_routes as $route): ?>
        <div class="card green">
          <div class="card-header">
            <span><?php echo htmlspecialchars($route['Model']); ?></span>
            <span>Time: <?php echo formatDateTime($route['StartDateTime']); ?></span>
          </div>
          <div class="card-details">
            <span class="completed">Completed</span>
            Plate Number: <?php echo htmlspecialchars($route['PlateNumber']); ?><br />
            Date: <?php echo formatDate($route['StartDateTime']); ?><br />
            Start odometer: <?php echo number_format($route['StartOdometer'], 0); ?><br />
            End odometer: <?php echo number_format($route['EndOdometer'], 0); ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="no-routes">No completed routes</div>
    <?php endif; ?>
  </div>
</div>

<script>
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