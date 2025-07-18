<?php
// Include database connection
require_once 'dbconnection.php';

// Start session to get supervisor information
session_start();

// Check if supervisor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'supervisor') {
    header("Location: index.php");
    exit();
}

// Get supervisor location from database
$supervisor_id = $_SESSION['user_id'];
$supervisor_query = "SELECT SupervisorLocation, FirstName, LastName FROM Supervisor WHERE SupervisorID = ?";
$supervisor_stmt = $conn->prepare($supervisor_query);
$supervisor_stmt->bind_param("i", $supervisor_id);
$supervisor_stmt->execute();
$supervisor_result = $supervisor_stmt->get_result();
$supervisor_data = $supervisor_result->fetch_assoc();

if (!$supervisor_data) {
    header("Location: index.php");
    exit();
}

$supervisor_location = $supervisor_data['SupervisorLocation'];
$supervisor_name = $supervisor_data['FirstName'] . ' ' . $supervisor_data['LastName'];
$supervisor_stmt->close();

// Initialize variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Build the WHERE clause for filtering
$where_conditions = [];
$params = [];
$types = '';

// Always filter by supervisor's location
$where_conditions[] = "v.VehicleLocation = ?";
$params[] = $supervisor_location;
$types .= 's';

if (!empty($search)) {
    $where_conditions[] = "(v.PlateNumber LIKE ? OR v.Model LIKE ? OR v.ChassisNumber LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

// Apply status filter
if ($status_filter !== 'all') {
    if ($status_filter === 'available') {
        $where_conditions[] = "active_routes.RouteID IS NULL";
    } elseif ($status_filter === 'in_use') {
        $where_conditions[] = "active_routes.RouteID IS NOT NULL";
    }
}

// Build the main query
$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM Vehicle v 
    LEFT JOIN (
        SELECT VehicleID, RouteID FROM Route WHERE EndDateTime IS NULL
    ) active_routes ON v.VehicleID = active_routes.VehicleID
    $where_clause
";

$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

$query = "
    SELECT 
        v.VehicleID,
        v.PlateNumber,
        v.Model,
        v.ChassisNumber,
        v.VehicleLocation,
        v.RegistrationExpiration,
        v.CreatedAt,
        COALESCE(latest_odometer.OdometerReading, 0) as LatestOdometer,
        CASE 
            WHEN active_routes.RouteID IS NOT NULL THEN 'In Use'
            ELSE 'Available'
        END as Status,
        active_routes.DriverID,
        CONCAT(COALESCE(d.FirstName, ''), ' ', COALESCE(d.LastName, '')) as CurrentDriver
    FROM Vehicle v
    LEFT JOIN (
        SELECT 
            VehicleID, 
            OdometerReading,
            ROW_NUMBER() OVER (PARTITION BY VehicleID ORDER BY ReadingDateTime DESC) as rn
        FROM OdometerReadings
    ) latest_odometer ON v.VehicleID = latest_odometer.VehicleID AND latest_odometer.rn = 1
    LEFT JOIN (
        SELECT VehicleID, RouteID, DriverID FROM Route 
        WHERE EndDateTime IS NULL
    ) active_routes ON v.VehicleID = active_routes.VehicleID
    LEFT JOIN Driver d ON active_routes.DriverID = d.DriverID
    $where_clause
    ORDER BY v.PlateNumber ASC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
$limit_params = $params;
$limit_params[] = $records_per_page;
$limit_params[] = $offset;
$limit_types = $types . 'ii';

if (!empty($limit_params)) {
    $stmt->bind_param($limit_types, ...$limit_params);
}
$stmt->execute();
$vehicles = $stmt->get_result();

// Get vehicle statistics (filtered by location)
$stats_query = "
    SELECT 
        COUNT(*) as total_vehicles,
        SUM(CASE WHEN active_routes.RouteID IS NOT NULL THEN 1 ELSE 0 END) as in_use,
        SUM(CASE WHEN active_routes.RouteID IS NULL THEN 1 ELSE 0 END) as available
    FROM Vehicle v
    LEFT JOIN (
        SELECT VehicleID, RouteID FROM Route WHERE EndDateTime IS NULL
    ) active_routes ON v.VehicleID = active_routes.VehicleID
    WHERE v.VehicleLocation = ?
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("s", $supervisor_location);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Get recent maintenance count (filtered by location)
$maintenance_query = "
    SELECT COUNT(*) as recent_maintenance 
    FROM MaintenanceHistory mh
    JOIN Vehicle v ON mh.VehicleID = v.VehicleID
    WHERE mh.MaintenanceDateTime >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND v.VehicleLocation = ?
";
$maintenance_stmt = $conn->prepare($maintenance_query);
$maintenance_stmt->bind_param("s", $supervisor_location);
$maintenance_stmt->execute();
$maintenance_result = $maintenance_stmt->get_result();
$maintenance_stats = $maintenance_result->fetch_assoc();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_vehicle'])) {
    $plate_number = trim($_POST['plate_number']);
    $model = trim($_POST['model']);
    $chassis_number = trim($_POST['chassis_number']);
    $registration_expiration = trim($_POST['registration_expiration']);
    $initial_odometer = floatval($_POST['initial_odometer']);
    
    // Validate input
    if (empty($plate_number) || empty($model) || empty($chassis_number) || empty($registration_expiration)) {
        $error_message = "All required fields must be filled.";
    } else {
        // Check if plate number or chassis number already exists
        $check_query = "SELECT VehicleID FROM Vehicle WHERE PlateNumber = ? OR ChassisNumber = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ss", $plate_number, $chassis_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Vehicle with this plate number or chassis number already exists.";
        } else {
            // Insert new vehicle WITH registration expiration
            $insert_query = "INSERT INTO Vehicle (PlateNumber, Model, ChassisNumber, VehicleLocation, RegistrationExpiration) VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sssss", $plate_number, $model, $chassis_number, $supervisor_location, $registration_expiration);
            
            if ($insert_stmt->execute()) {
                $new_vehicle_id = $conn->insert_id;
                
                // Insert initial odometer reading if provided
                if ($initial_odometer > 0) {
                    $odometer_query = "INSERT INTO OdometerReadings (VehicleID, OdometerReading) VALUES (?, ?)";
                    $odometer_stmt = $conn->prepare($odometer_query);
                    $odometer_stmt->bind_param("id", $new_vehicle_id, $initial_odometer);
                    $odometer_stmt->execute();
                    $odometer_stmt->close();
                }
                
                $success_message = "Vehicle registered successfully!";
                // Refresh the page to show the new vehicle
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $error_message = "Error registering vehicle. Please try again.";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Close prepared statements
$count_stmt->close();
$stmt->close();
$stats_stmt->close();
$maintenance_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Management System - Vehicle List</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
</head>
<style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #334155;
            line-height: 1.6;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            padding: 0;
            transition: left 0.3s ease-in-out;
            z-index: 1000;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
            overflow-y: auto;
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            background: rgba(255,255,255,0.1);
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1rem;
        }

        .sidebar-logo i {
            font-size: 2rem;
            color: #fbbf24;
        }

        .sidebar-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
        }

        .sidebar-subtitle {
            font-size: 0.875rem;
            opacity: 0.8;
            margin: 0;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            display: block;
            color: white;
            padding: 0.875rem 1.5rem;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            border-left-color: #fbbf24;
        }

        .nav-item.active {
            background: rgba(255,255,255,0.15);
            border-left-color: #fbbf24;
        }

        .nav-item i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }

        .overlay.active {
            display: block;
        }

        .main-content {
            margin-left: 0;
            transition: margin-left 0.3s ease-in-out;
        }

        .header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .menu-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #64748b;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .menu-toggle:hover {
            background: #f1f5f9;
            color: #1e40af;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3b82f6, #1e40af);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .content {
            padding: 2rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .widget {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .widget:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .widget-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .widget-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .widget-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #059669;
        }

        .widget-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .widget-label {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.75rem;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem 2rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .table-subtitle {
            font-size: 0.875rem;
            color: #64748b;
        }

        .table-filters {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .search-form {
            display: flex;
            gap: 1rem;
            flex: 1;
            min-width: 200px;
        }

        .search-input {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            flex: 1;
        }

        .search-btn {
            padding: 0.5rem 1rem;
            background: #1e40af;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            transition: background 0.3s ease;
        }

        .search-btn:hover {
            background: #1e3a8a;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: #f8fafc;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.875rem;
        }

        .data-table tr:hover {
            background: #f8fafc;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-available {
            background: #ecfdf5;
            color: #059669;
        }

        .status-in-use {
            background: #fef3c7;
            color: #d97706;
        }

        .vehicle-plate {
            font-weight: 600;
            color: #1e40af;
        }

        .driver-name {
            font-weight: 500;
            color: #1e293b;
        }

        .pagination {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 1rem 2rem;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }

        .pagination-info {
            font-size: 0.875rem;
            color: #64748b;
        }

        .pagination-controls {
            display: flex;
            gap: 0.5rem;
        }

        .pagination-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            background: white;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #374151;
        }

        .pagination-btn:hover {
            background: #f3f4f6;
        }

        .pagination-btn.active {
            background: #1e40af;
            color: white;
            border-color: #1e40af;
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .action-btn {
            padding: 0.25rem 0.5rem;
            border: 1px solid #d1d5db;
            background: white;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            margin-right: 0.25rem;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: #f3f4f6;
        }

        .action-btn.edit {
            color: #059669;
            border-color: #059669;
        }

        .action-btn.delete {
            color: #dc2626;
            border-color: #dc2626;
        }
        .table-actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 1rem;
        }

        .add-vehicle-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .add-vehicle-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 999;
            left: 0;    
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.72);
        }

        .modal-content {
            background-color: white;
            margin: 1% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .modal-header h3 {
            margin: 0;
            color: #1e293b;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .close {
            color: #64748b;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #dc2626;
        }

        .vehicle-form {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-group input:disabled {
            background-color: #f3f4f6;
            color: #6b7280;
        }

        .form-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .cancel-btn {
            padding: 0.75rem 1.5rem;
            border: 1px solid #d1d5db;
            background: white;
            color: #374151;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .cancel-btn:hover {
            background: #f3f4f6;
        }

        .submit-btn {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #3b82f6, #1e40af);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        @media (max-width: 768px) {
            .header {
                padding: 1rem;
            }

            .content {
                padding: 1rem;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .widget {
                padding: 1rem;
            }

            .widget-value {
                font-size: 2rem;
            }

            .table-header {
                padding: 1rem;
            }

            .table-filters {
                flex-direction: column;
            }

            .data-table {
                font-size: 0.75rem;
            }

            .data-table th,
            .data-table td {
                padding: 0.5rem;
            }
                .modal-content {
                width: 95%;
                margin: 10% auto;
            }
            
            .vehicle-form {
                padding: 1.5rem;
            }
            
            .form-buttons {
                flex-direction: column;
            }
            
            .cancel-btn, .submit-btn {
                width: 100%;
            }   
        }
    </style>    
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-tools"></i>
                <div>
                    <h3 class="sidebar-title">VMS</h3>
                    <p class="sidebar-subtitle">Vehicle Maintenance System</p>
                </div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="supervisor-dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
            <a href="#" class="nav-item active">
                <i class="fas fa-car"></i>
                Vehicle Management
            </a>
            <a href="supervisor-driver-list.php" class="nav-item">
                <i class="fas fa-users"></i>
                Driver Management
            </a>
            <a href="supervisor-alerts.php" class="nav-item">
                <i class="fas fa-bell"></i>
                Maintenance Alerts
            </a>
            <a href="supervisor-maintenance-history.PHP" class="nav-item" >
                <i class="fas fa-wrench"></i>
                Maintenance History
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-chart-line"></i>
                Reports
            </a>
            <a href="logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
            
        </nav>
    </div>

    <!-- Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Vehicle List - <?php echo htmlspecialchars($supervisor_location); ?></h1>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600; font-size: 0.875rem;"><?php echo htmlspecialchars($supervisor_name); ?></div>
                        <div style="font-size: 0.75rem; color: #64748b;"><?php echo htmlspecialchars($supervisor_location); ?></div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="content">
            <!-- Statistics Dashboard -->
            <div class="dashboard-grid">
                <div class="widget">
                    <div class="widget-header">
                        <span class="widget-title">Total Vehicles</span>
                        <div class="widget-icon blue">
                            <i class="fas fa-car"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['total_vehicles']; ?></div>
                    <div class="widget-label">Total in <?php echo htmlspecialchars($supervisor_location); ?></div>
                </div>

                <div class="widget">
                    <div class="widget-header">
                        <span class="widget-title">Available</span>
                        <div class="widget-icon green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['available']; ?></div>
                    <div class="widget-label">Ready for deployment</div>
                </div>

                <div class="widget">
                    <div class="widget-header">
                        <span class="widget-title">In Use</span>
                        <div class="widget-icon yellow">
                            <i class="fas fa-route"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['in_use']; ?></div>
                    <div class="widget-label">Currently on routes</div>
                </div>

                <div class="widget">
                    <div class="widget-header">
                        <span class="widget-title">Recent Maintenance</span>
                        <div class="widget-icon red">
                            <i class="fas fa-wrench"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $maintenance_stats['recent_maintenance']; ?></div>
                    <div class="widget-label">Last 30 days</div>
                </div>
            </div>

            <!-- Vehicle List Table -->
            <div class="table-container">
                <div class="table-header">
                    <h2 class="table-title">Vehicle List - <?php echo htmlspecialchars($supervisor_location); ?></h2>
                    <p class="table-subtitle">Complete record of all vehicles in your assigned location</p>

                    <div class="table-actions">
                        <button class="add-vehicle-btn" onclick="openVehicleModal()">
                            <i class="fas fa-plus"></i> Register New Vehicle
                        </button>
                    </div>

                    <div class="table-filters">
                        <form class="search-form" method="GET">
                            <input type="text" name="search" class="search-input" placeholder="Search by plate number, model, or chassis number..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </form>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="data-table">
                    <thead>
                        <tr>
                            <th>Plate Number</th>
                            <th>Model</th>
                            <th>Chassis Number</th>
                            <th>Latest Odometer</th>
                            <th>Registration Expiration</th>
                            <th>Status</th>
                            <th>Current Driver</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                        <tbody>
                            <?php if ($vehicles->num_rows > 0): ?>
                                <?php while ($vehicle = $vehicles->fetch_assoc()): ?>
                                    <tr>
                                        <td class="vehicle-plate"><?php echo htmlspecialchars($vehicle['PlateNumber']); ?></td>
                                        <td><?php echo htmlspecialchars($vehicle['Model']); ?></td>
                                        <td><?php echo htmlspecialchars($vehicle['ChassisNumber']); ?></td>
                                        <td><?php echo number_format($vehicle['LatestOdometer']); ?> km</td>
                                        <td>
                                            <?php 
                                            $exp_date = new DateTime($vehicle['RegistrationExpiration']);
                                            $today = new DateTime();
                                            $days_diff = $today->diff($exp_date)->days;
                                            $is_expired = $today > $exp_date;
                                            $is_expiring_soon = $days_diff <= 30 && !$is_expired;
                                            ?>
                                            <span class="registration-date <?php echo $is_expired ? 'expired' : ($is_expiring_soon ? 'expiring-soon' : ''); ?>">
                                                <?php echo $exp_date->format('M d, Y'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $vehicle['Status'] === 'Available' ? 'status-available' : 'status-in-use'; ?>">
                                                <?php echo $vehicle['Status']; ?>
                                            </span>
                                        </td>
                                        <td class="driver-name">
                                            <?php echo $vehicle['CurrentDriver'] ? htmlspecialchars($vehicle['CurrentDriver']) : 'N/A'; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($vehicle['CreatedAt'])); ?></td>
                                        <td>
                                            <button class="action-btn edit" onclick="editVehicle(<?php echo $vehicle['VehicleID']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="action-btn delete" onclick="deleteVehicle(<?php echo $vehicle['VehicleID']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 2rem; color: #64748b;">
                                        <?php echo !empty($search) ? 'No vehicles found matching your search in ' . htmlspecialchars($supervisor_location) . '.' : 'No vehicles registered in ' . htmlspecialchars($supervisor_location) . ' yet.'; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> entries
                    </div>
                    <div class="pagination-controls">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="pagination-btn">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                               class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page + 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="pagination-btn">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    

    <script>
        // Sidebar toggle functionality
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });

        // Close sidebar on mobile after navigation
        if (window.innerWidth <= 768) {
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', () => {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                });
            });
        }

        // Action functions
        function editVehicle(vehicleId) {
            // Redirect to edit page or show modal
            alert('Edit vehicle ID: ' + vehicleId);
            // window.location.href = 'edit-vehicle.php?id=' + vehicleId;
        }

        function deleteVehicle(vehicleId) {
            if (confirm('Are you sure you want to delete this vehicle?')) {
                // Send delete request
                alert('Delete vehicle ID: ' + vehicleId);
                // window.location.href = 'delete-vehicle.php?id=' + vehicleId;
            }
        }

        // Auto-refresh every 30 seconds to update real-time data
        setInterval(() => {
            // Only refresh if no search is active to avoid disrupting user
            if (!document.querySelector('.search-input').value) {
                location.reload();
            }
        }, 30000);
        
        function openVehicleModal() {
            document.getElementById('vehicleModal').style.display = 'block';
        }

        function closeVehicleModal() {
            document.getElementById('vehicleModal').style.display = 'none';
            // Reset form
            document.querySelector('.vehicle-form').reset();
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('vehicleModal');
            if (event.target === modal) {
                closeVehicleModal();
            }
        }
        // Show success/error messages
        <?php if (isset($success_message)): ?>
            alert('<?php echo addslashes($success_message); ?>');
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            alert('<?php echo addslashes($error_message); ?>');
        <?php endif; ?>
    </script>

    <style>
        .location-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            background-color: #f1f5f9;
            color: #475569;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .location-badge i {
            font-size: 0.75rem;
        }
    </style>

        <!-- Vehicle Registration Modal -->
    <div id="vehicleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Register New Vehicle</h3>
                <span class="close" onclick="closeVehicleModal()">&times;</span>
            </div>
            <form method="POST" class="vehicle-form">
                <div class="form-group">
                    <label for="plate_number">Plate Number *</label>
                    <input type="text" id="plate_number" name="plate_number" required maxlength="15" placeholder="Ex. ABG-6556 (no spacing)">
                </div>
                <div class="form-group">
                    <label for="model">Vehicle Model *</label>
                    <input type="text" id="model" name="model" required maxlength="50">
                </div>
                <div class="form-group">
                    <label for="chassis_number">Chassis Number *</label>
                    <input type="text" id="chassis_number" name="chassis_number" required maxlength="50">
                </div>
                <div class="form-group">
                    <label for="registration_expiration">Registration Expiration Date *</label>
                    <input type="date" id="registration_expiration" name="registration_expiration" required>
                </div>
                <div class="form-group">
                    <label for="initial_odometer">Initial Odometer Reading (km)</label>
                    <input type="number" id="initial_odometer" name="initial_odometer" step="0.01" min="0" placeholder="0">
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" value="<?php echo htmlspecialchars($supervisor_location); ?>" disabled>
                </div>
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="closeVehicleModal()">Cancel</button>
                    <button type="submit" name="register_vehicle" class="submit-btn">Register Vehicle</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>