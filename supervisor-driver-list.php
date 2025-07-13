<?php
// Start session first
session_start();

// Database connection
$host = 'localhost';
$dbname = 'VehicleManagementSystem';
$username = 'root';
$password = '';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('Database connection error: ' . $e->getMessage());
    die('Database connection failed. Please try again later.');
}

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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'delete_driver') {
        $driverId = intval($_POST['driver_id']);
        
        try {
            $stmt = $conn->prepare("DELETE FROM Driver WHERE DriverID = ?");
            $stmt->bind_param("i", $driverId);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Driver deleted successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error deleting driver: ' . $e->getMessage()]);
        }
        exit;
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$page = intval($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$whereConditions = [];
$params = [];
$types = '';

if ($searchQuery) {
    $whereConditions[] = "(FirstName LIKE ? OR LastName LIKE ? OR EmployeeNo LIKE ? OR DriverLicenseNumber LIKE ? OR ContactNumber LIKE ? OR EmailAddress LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
    $types .= 'ssssss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM Driver $whereClause";
$countStmt = $conn->prepare($countQuery);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalDrivers = $countStmt->get_result()->fetch_assoc()['total'];

// Get drivers data
$query = "SELECT DriverID, FirstName, MiddleName, LastName, EmployeeNo, EmailAddress, 
                 ContactNumber, DriverLicenseNumber, DriverLicenseExpiration, 
                 DateOfBirth, CreatedAt 
          FROM Driver $whereClause 
          ORDER BY CreatedAt DESC 
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$drivers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$statsQuery = "SELECT 
    COUNT(*) as total_drivers,
    SUM(CASE WHEN DriverLicenseExpiration > NOW() THEN 1 ELSE 0 END) as active_drivers,
    SUM(CASE WHEN DriverLicenseExpiration <= NOW() THEN 1 ELSE 0 END) as expired_licenses,
    SUM(CASE WHEN DriverLicenseExpiration BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_soon
FROM Driver";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Calculate pagination
$totalPages = ceil($totalDrivers / $limit);
$startEntry = $offset + 1;
$endEntry = min($offset + $limit, $totalDrivers);

// Helper function to get driver status
function getDriverStatus($expirationDate) {
    $currentDate = new DateTime();
    $expDate = new DateTime($expirationDate);
    $daysDiff = $currentDate->diff($expDate)->days;
    
    if ($expDate < $currentDate) {
        return ['status' => 'expired', 'class' => 'status-inactive', 'text' => 'Expired'];
    } elseif ($daysDiff <= 30) {
        return ['status' => 'expiring', 'class' => 'status-on-leave', 'text' => 'Expiring Soon'];
    } else {
        return ['status' => 'active', 'class' => 'status-active', 'text' => 'Active'];
    }
}

// Helper function to get initials
function getInitials($firstName, $lastName) {
    return strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Maintenance System - Driver List</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        .widget-change {
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            display: inline-block;
        }

        .widget-change.positive {
            color: #059669;
            background: #ecfdf5;
        }

        .widget-change.negative {
            color: #dc2626;
            background: #fef2f2;
        }

        .widget-change.neutral {
            color: #64748b;
            background: #f1f5f9;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-title-section {
            flex: 1;
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

        .add-driver-btn {
            background: linear-gradient(135deg, #3b82f6, #1e40af);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .add-driver-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.25);
        }

        .table-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            padding: 0 2rem;
        }

        .filter-select {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            background: white;
            color: #374151;
        }

        .search-input {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            flex: 1;
            min-width: 200px;
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

        .status-active {
            background: #ecfdf5;
            color: #059669;
        }

        .status-inactive {
            background: #fef2f2;
            color: #dc2626;
        }

        .status-on-leave {
            background: #fef3c7;
            color: #d97706;
        }

        .driver-name {
            font-weight: 600;
            color: #1e40af;
        }

        .driver-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #1e40af);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.75rem;
            margin-right: 0.75rem;
        }

        .driver-info {
            display: flex;
            align-items: center;
        }

        .action-btn {
            background: none;
            border: none;
            padding: 0.5rem;
            margin: 0 0.25rem;
            border-radius: 6px;
            cursor: pointer;
            color: #64748b;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: #f1f5f9;
            color: #1e40af;
        }

        .action-btn.danger:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        .pagination {
            display: flex;
            justify-content: space-between;
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

        .license-number {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #059669;
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
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
                flex-direction: column;
                align-items: stretch;
            }

            .table-filters {
                flex-direction: column;
                padding: 0 1rem;
            }

            .data-table {
                font-size: 0.75rem;
            }

            .data-table th,
            .data-table td {
                padding: 0.5rem;
            }

            .driver-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }

            .driver-avatar {
                margin-right: 0;
                margin-bottom: 0.25rem;
            }
        }
    </style>
</head>
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
            <a href="supervisor-vehicle-list.php" class="nav-item">
                <i class="fas fa-car"></i>
                Vehicle Management
            </a>
            <a href="#" class="nav-item active">
                <i class="fas fa-users"></i>
                Driver Management
            </a>
            <a href="supervisor-alerts.php" class="nav-item">
                <i class="fas fa-bell"></i>
                Maintenance Alerts
            </a>
            <a href="supervisor-maintenance-history.php" class="nav-item">
                <i class="fas fa-wrench"></i>
                Maintenance History
            </a>
            <a href="supervisor-reports.php" class="nav-item">
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
                <h1 class="page-title">Driver List</h1>
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
            <!-- Driver Statistics -->
            <div class="dashboard-grid">
                <div class="widget">
                    <div class="widget-header">
                        <div class="widget-title">Total Drivers</div>
                        <div class="widget-icon blue">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['total_drivers']; ?></div>
                    <div class="widget-label">Registered Drivers</div>
                    <div class="widget-change neutral">All time</div>
                </div>

                <div class="widget">
                    <div class="widget-header">
                        <div class="widget-title">Active Drivers</div>
                        <div class="widget-icon green">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['active_drivers']; ?></div>
                    <div class="widget-label">Valid Licenses</div>
                    <div class="widget-change positive">Currently Active</div>
                </div>

                <div class="widget">
                    <div class="widget-header">
                        <div class="widget-title">Expiring Soon</div>
                        <div class="widget-icon yellow">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['expiring_soon']; ?></div>
                    <div class="widget-label">Within 30 days</div>
                    <div class="widget-change negative">Requires attention</div>
                </div>

                <div class="widget">
                    <div class="widget-header">
                        <div class="widget-title">Expired Licenses</div>
                        <div class="widget-icon red">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['expired_licenses']; ?></div>
                    <div class="widget-label">Need Renewal</div>
                    <div class="widget-change negative">Action Required</div>
                </div>
            </div>

            <!-- Driver List Table -->
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title-section">
                        <h2 class="table-title">Driver List</h2>
                        <p class="table-subtitle">Complete record of all registered drivers</p>
                    </div>
                    <button class="add-driver-btn" onclick="addDriver()">
                        <i class="fas fa-plus"></i>
                        Add Driver
                    </button>
                </div>

                <div class="table-filters">
                    <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; width: 100%;">
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Drivers</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="expiring" <?php echo $statusFilter === 'expiring' ? 'selected' : ''; ?>>Expiring Soon</option>
                            <option value="expired" <?php echo $statusFilter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>
                        <input type="text" name="search" class="search-input" placeholder="Search by name, employee no, license number..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                        <button type="submit" style="display: none;"></button>
                    </form>
                </div>

                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Driver</th>
                                <th>Employee No</th>
                                <th>License Number</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Expiry Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($drivers)): ?>
                                <tr>
                                    <td colspan="8" class="no-data">
                                        <i class="fas fa-users"></i>
                                        <div>No drivers found</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($drivers as $driver): ?>
                                    <?php $status = getDriverStatus($driver['DriverLicenseExpiration']); ?>
                                    <tr>
                                        <td>
                                            <div class="driver-info">
                                                <div class="driver-avatar">
                                                    <?php echo getInitials($driver['FirstName'], $driver['LastName']); ?>
                                                </div>
                                                <div>
                                                    <div class="driver-name">
                                                        <?php echo htmlspecialchars($driver['FirstName'] . ' ' . $driver['LastName']); ?>
                                                    </div>
                                                    <?php if ($driver['MiddleName']): ?>
                                                        <div style="font-size: 0.75rem; color: #64748b;">
                                                            <?php echo htmlspecialchars($driver['MiddleName']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($driver['EmployeeNo']); ?></td>
                                        <td class="license-number"><?php echo htmlspecialchars($driver['DriverLicenseNumber']); ?></td>
                                        <td><?php echo htmlspecialchars($driver['ContactNumber']); ?></td>
                                        <td><?php echo htmlspecialchars($driver['EmailAddress']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $status['class']; ?>">
                                                <?php echo $status['text']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($driver['DriverLicenseExpiration'])); ?></td>
                                        <td>
                                         <button class="action-btn" onclick="editDriver(<?php echo $driver['DriverID']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="action-btn danger" onclick="deleteDriver(<?php echo $driver['DriverID']; ?>)">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <?php echo $startEntry; ?> to <?php echo $endEntry; ?> of <?php echo $totalDrivers; ?> entries
                    </div>
                    <div class="pagination-controls">
                        <a href="?page=<?php echo max(1, $page - 1); ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($searchQuery); ?>" class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            Previous
                        </a>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($searchQuery); ?>" class="pagination-btn <?php echo $page === $i ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        <a href="?page=<?php echo min($totalPages, $page + 1); ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($searchQuery); ?>" class="pagination-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            Next
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Toggle sidebar
document.getElementById('menuToggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('overlay').classList.toggle('active');
    document.querySelector('.main-content').style.marginLeft = document.getElementById('sidebar').classList.contains('active') ? '280px' : '0';
});

// Close sidebar when clicking on overlay
document.getElementById('overlay').addEventListener('click', function() {
    document.getElementById('sidebar').classList.remove('active');
    document.getElementById('overlay').classList.remove('active');
    document.querySelector('.main-content').style.marginLeft = '0';
});

// Close sidebar on window resize if screen is large
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        document.getElementById('sidebar').classList.remove('active');
        document.getElementById('overlay').classList.remove('active');
        document.querySelector('.main-content').style.marginLeft = '0';
    }
});

// Auto-submit search form on input (with debounce)
let searchTimeout;
document.querySelector('input[name="search"]').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        this.form.submit();
    }, 500); // 500ms delay
});

// Function to handle driver deletion with better UX
function deleteDriver(driverId) {
    // Get driver name from the row for better confirmation message
    const row = event.target.closest('tr');
    const driverName = row.querySelector('.driver-name').textContent;
    
    if (confirm(`Are you sure you want to delete driver "${driverName}"? This action cannot be undone.`)) {
        // Show loading state
        const deleteBtn = event.target.closest('.action-btn');
        const originalContent = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        deleteBtn.disabled = true;
        
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_driver&driver_id=${driverId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showToast(data.message, 'success');
                
                // Remove row with animation
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(-100%)';
                
                setTimeout(() => {
                    window.location.reload();
                }, 300);
            } else {
                showToast(data.message, 'error');
                // Restore button state
                deleteBtn.innerHTML = originalContent;
                deleteBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred while deleting the driver.', 'error');
            // Restore button state
            deleteBtn.innerHTML = originalContent;
            deleteBtn.disabled = false;
        });
    }
}

// Function to handle adding a new driver
function addDriver() {
    window.location.href = 'add-driver.php';
}

// Function to handle editing a driver
function editDriver(driverId) {
    window.location.href = `edit-driver.php?id=${driverId}`;
}

// Toast notification function
function showToast(message, type = 'info') {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.toast');
    existingToasts.forEach(toast => toast.remove());
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 9999;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    // Set background color based on type
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#3b82f6'
    };
    toast.style.backgroundColor = colors[type] || colors.info;
    
    toast.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 5000);
    
    // Remove on click
    toast.addEventListener('click', () => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    });
}

// Table row hover effects and interactions
document.addEventListener('DOMContentLoaded', function() {
    // Add keyboard navigation for table rows
    const tableRows = document.querySelectorAll('.data-table tbody tr');
    tableRows.forEach((row, index) => {
        row.setAttribute('tabindex', '0');
        row.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const editBtn = row.querySelector('.action-btn:not(.danger)');
                if (editBtn) {
                    editBtn.click();
                }
            }
        });
    });
    
    // Add loading states to pagination links
    const paginationLinks = document.querySelectorAll('.pagination-btn:not(.disabled)');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!this.classList.contains('disabled') && !this.classList.contains('active')) {
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }
        });
    });
    
    // Add tooltips to action buttons
    const actionBtns = document.querySelectorAll('.action-btn');
    actionBtns.forEach(btn => {
        const icon = btn.querySelector('i');
        if (icon.classList.contains('fa-edit')) {
            btn.setAttribute('title', 'Edit Driver');
        } else if (icon.classList.contains('fa-trash-alt')) {
            btn.setAttribute('title', 'Delete Driver');
        }
    });
});

// Prevent form submission on Enter key in search input (let the debounce handle it)
document.querySelector('input[name="search"]').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
    }
});

// Add confirmation before leaving page if there are unsaved changes
window.addEventListener('beforeunload', function(e) {
    const hasUnsavedChanges = document.querySelector('.action-btn[disabled]');
    if (hasUnsavedChanges) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// Refresh page data every 5 minutes (optional)
setInterval(function() {
    // Only refresh if user is not actively interacting
    if (document.hidden === false && !document.querySelector('.action-btn[disabled]')) {
        const currentUrl = new URL(window.location.href);
        const params = new URLSearchParams(currentUrl.search);
        
        // Add a refresh parameter to prevent caching
        params.set('refresh', Date.now());
        
        // Silently refresh the page
        window.location.href = currentUrl.pathname + '?' + params.toString();
    }
}, 300000); // 5 minutes
    </script>
</body>
</html>
