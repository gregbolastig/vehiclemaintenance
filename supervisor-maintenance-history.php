<?php
// Database connection
require_once ('dbconnection.php');

// Start session
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

// Pagination settings
$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Search and filter parameters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$vehicle_filter = isset($_GET['vehicle']) ? trim($_GET['vehicle']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build WHERE clause for filtering
$where_conditions = ["v.VehicleLocation = ?"];
$params = [$supervisor_location];
$param_types = "s";

if (!empty($search_query)) {
    $where_conditions[] = "(mh.InstalledParts LIKE ? OR mh.PartsSpecifications LIKE ? OR v.PlateNumber LIKE ? OR v.Model LIKE ?)";
    $search_param = "%$search_query%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= "ssss";
}

if (!empty($vehicle_filter)) {
    $where_conditions[] = "v.VehicleID = ?";
    $params[] = $vehicle_filter;
    $param_types .= "i";
}

if (!empty($date_from)) {
    $where_conditions[] = "mh.InstalledDate >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $where_conditions[] = "mh.InstalledDate <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM MaintenanceHistory mh 
                LEFT JOIN Vehicle v ON mh.VehicleID = v.VehicleID 
                WHERE $where_clause";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param($param_types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch maintenance history with pagination
$maintenance_query = "
    SELECT 
        mh.MaintenanceID,
        mh.InstalledParts,
        mh.InstalledDate,
        mh.PartsSpecifications,
        mh.MaintenanceDateTime,
        v.PlateNumber,
        v.Model,
        v.VehicleLocation,
        v.VehicleID
    FROM MaintenanceHistory mh
    LEFT JOIN Vehicle v ON mh.VehicleID = v.VehicleID
    WHERE $where_clause
    ORDER BY mh.InstalledDate DESC, mh.MaintenanceDateTime DESC
    LIMIT ? OFFSET ?
";

$maintenance_stmt = $conn->prepare($maintenance_query);
$params[] = $records_per_page;
$params[] = $offset;
$param_types .= "ii";
$maintenance_stmt->bind_param($param_types, ...$params);
$maintenance_stmt->execute();
$maintenance_result = $maintenance_stmt->get_result();
$maintenance_history = [];
while ($row = $maintenance_result->fetch_assoc()) {
    $maintenance_history[] = $row;
}

// Get vehicles for filter dropdown
$vehicles_query = "SELECT VehicleID, PlateNumber, Model FROM Vehicle WHERE VehicleLocation = ? ORDER BY PlateNumber";
$vehicles_stmt = $conn->prepare($vehicles_query);
$vehicles_stmt->bind_param("s", $supervisor_location);
$vehicles_stmt->execute();
$vehicles_result = $vehicles_stmt->get_result();
$vehicles = [];
while ($row = $vehicles_result->fetch_assoc()) {
    $vehicles[] = $row;
}

// Get maintenance statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_maintenance,
        COUNT(DISTINCT v.VehicleID) as vehicles_maintained,
        COUNT(CASE WHEN mh.InstalledDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as recent_maintenance,
        COUNT(CASE WHEN mh.InstalledDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as this_week
    FROM MaintenanceHistory mh
    LEFT JOIN Vehicle v ON mh.VehicleID = v.VehicleID
    WHERE v.VehicleLocation = ?
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("s", $supervisor_location);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance History - Vehicle Management System</title>
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

        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 2rem;
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

        .status-completed {
            background: #ecfdf5;
            color: #059669;
        }

        .status-in-progress {
            background: #fef3c7;
            color: #d97706;
        }

        .status-pending {
            background: #fef2f2;
            color: #dc2626;
        }

        .vehicle-plate {
            font-weight: 600;
            color: #1e40af;
        }

        .driver-name {
            font-weight: 500;
            color: #1e293b;
        }

        .tabs {
            display: flex;
            margin-bottom: 1rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            font-size: 0.875rem;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .tab.active {
            color: #1e40af;
            border-bottom-color: #1e40af;
        }

        .tab:hover {
            color: #1e40af;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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

            .data-table {
                font-size: 0.75rem;
            }

            .data-table th,
            .data-table td {
                padding: 0.5rem;
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
            <a href="supervisor-driver-list.php" class="nav-item">
                <i class="fas fa-users"></i>
                Driver Management
            </a>
            <a href="supervisor-alerts.php" class="nav-item">
                <i class="fas fa-bell"></i>
                Maintenance Alerts
            </a>
            <a href="#" class="nav-item active">
                <i class="fas fa-wrench"></i>
                Maintenance History
            </a>
            <a href="supervisor-reports.php" class="nav-item">
                <i class="fas fa-chart-line"></i>
                Reports
            </a>
            <a href="logout.php" class="nav-item logout-link">
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
                <h1 class="page-title">Maintenance History</h1>
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
            <!-- Statistics Cards -->
            <div class="dashboard-grid">
                <div class="widget">
                    <div class="widget-header">
                        <div class="widget-title">Total Maintenance</div>
                        <div class="widget-icon blue">
                            <i class="fas fa-wrench"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['total_maintenance']; ?></div>
                    <div class="widget-label">All maintenance records</div>
                </div>

                <div class="widget">
                    <div class="widget-header">
                        <div class="widget-title">Vehicles Maintained</div>
                        <div class="widget-icon green">
                            <i class="fas fa-car"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['vehicles_maintained']; ?></div>
                    <div class="widget-label">Unique vehicles</div>
                </div>

                <div class="widget">
                    <div class="widget-header">
                        <div class="widget-title">Recent Maintenance</div>
                        <div class="widget-icon yellow">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['recent_maintenance']; ?></div>
                    <div class="widget-label">Last 30 days</div>
                </div>

                <div class="widget">
                    <div class="widget-header">
                        <div class="widget-title">This Week</div>
                        <div class="widget-icon red">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['this_week']; ?></div>
                    <div class="widget-label">Past 7 days</div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="table-container">
                <div class="table-header">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <div>
                            <h2 class="table-title">Maintenance History</h2>
                            <p class="table-subtitle">Track all maintenance activities for your vehicles</p>
                        </div>
                        <div style="display: flex; gap: 1rem; align-items: center;">
                            <span style="font-size: 0.875rem; color: #64748b;">
                                Showing <?php echo min($offset + 1, $total_records); ?>-<?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records
                            </span>
                        </div>
                    </div>
                    
                    <!-- Search and Filter Form -->
                    <form method="GET" class="filter-form" style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap: 1rem; align-items: end; margin-bottom: 1rem;">
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: #374151;">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" 
                                   placeholder="Search parts, specifications, or vehicle..."
                                   style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem;">
                        </div>
                        
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: #374151;">Vehicle</label>
                            <select name="vehicle" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem;">
                                <option value="">All Vehicles</option>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <option value="<?php echo $vehicle['VehicleID']; ?>" 
                                            <?php echo $vehicle_filter == $vehicle['VehicleID'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($vehicle['PlateNumber'] . ' - ' . $vehicle['Model']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: #374151;">Date From</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                                   style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem;">
                        </div>
                        
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: #374151;">Date To</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                                   style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem;">
                        </div>
                        
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="submit" style="padding: 0.5rem 1rem; background: #3b82f6; color: white; border: none; border-radius: 6px; font-size: 0.875rem; cursor: pointer;">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="supervisor-maintenance-history.php" style="padding: 0.5rem 1rem; background: #6b7280; color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem;">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Maintenance History Table -->
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Maintenance ID</th>
                                <th>Date</th>
                                <th>Vehicle</th>
                                <th>Model</th>
                                <th>Installed Parts</th>
                                <th>Specifications</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($maintenance_history as $maintenance): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($maintenance['MaintenanceID']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($maintenance['InstalledDate'])); ?></td>
                                <td class="vehicle-plate"><?php echo htmlspecialchars($maintenance['PlateNumber']); ?></td>
                                <td><?php echo htmlspecialchars($maintenance['Model']); ?></td>
                                <td><?php echo htmlspecialchars($maintenance['InstalledParts']); ?></td>
                                <td>
                                    <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" 
                                         title="<?php echo htmlspecialchars($maintenance['PartsSpecifications'] ?? 'No specifications'); ?>">
                                        <?php echo htmlspecialchars($maintenance['PartsSpecifications'] ?? 'No specifications'); ?>
                                    </div>
                                </td>
                                <td>
                                    <button onclick="viewDetails(<?php echo $maintenance['MaintenanceID']; ?>)" 
                                            style="padding: 0.25rem 0.5rem; background: #3b82f6; color: white; border: none; border-radius: 4px; font-size: 0.75rem; cursor: pointer;">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($maintenance_history)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem; color: #64748b;">
                                    No maintenance history found for the selected criteria
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div style="padding: 1rem 2rem; display: flex; justify-content: between; align-items: center; border-top: 1px solid #e2e8f0;">
                    <div style="flex: 1;">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?php echo $current_page - 1; ?>&<?php echo http_build_query($_GET); ?>" 
                               style="padding: 0.5rem 1rem; background: #f3f4f6; color: #374151; text-decoration: none; border-radius: 6px; font-size: 0.875rem;">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem;">
                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>" 
                               style="padding: 0.5rem 0.75rem; <?php echo $i == $current_page ? 'background: #3b82f6; color: white;' : 'background: #f3f4f6; color: #374151;'; ?> text-decoration: none; border-radius: 6px; font-size: 0.875rem;">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    
                    <div style="flex: 1; text-align: right;">
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo $current_page + 1; ?>&<?php echo http_build_query($_GET); ?>" 
                               style="padding: 0.5rem 1rem; background: #f3f4f6; color: #374151; text-decoration: none; border-radius: 6px; font-size: 0.875rem;">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal for Maintenance Details -->
    <div id="detailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1001;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700;">Maintenance Details</h3>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="modalContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
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

        // Navigation functionality
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            if (!item.classList.contains('logout-link')) {
                item.addEventListener('click', (e) => {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('active');
                        overlay.classList.remove('active');
                    }
                });
            }
        });

        // Modal functionality
        function viewDetails(maintenanceId) {
            // In a real application, you would fetch details via AJAX
            const modalContent = document.getElementById('modalContent');
            modalContent.innerHTML = '<p>Loading maintenance details...</p>';
            document.getElementById('detailsModal').style.display = 'block';
            
            // Simulate loading details - replace with actual AJAX call
            setTimeout(() => {
                modalContent.innerHTML = `
                    <div style="space-y: 1rem;">
                        <div>
                            <label style="font-weight: 600; color: #374151;">Maintenance ID:</label>
                            <p style="margin: 0.5rem 0;">${maintenanceId}</p>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #374151;">Full Specifications:</label>
                            <p style="margin: 0.5rem 0;">Complete maintenance specifications would be displayed here...</p>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #374151;">Maintenance Notes:</label>
                            <p style="margin: 0.5rem 0;">Additional notes and details about the maintenance work...</p>
                        </div>
                    </div>
                `;
            }, 500);
        }

        function closeModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('detailsModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                closeModal();
            }
        });
    </script>
</body>
</html>