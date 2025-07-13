<?php
// Database connection
require_once ('dbconnection.php');

// Get supervisor's location (you'll need to get this from session or login)
// Assuming you have supervisor ID from session
session_start();
$supervisorID = $_SESSION['supervisor_id'] ?? 1; // Replace with actual session variable

// Get supervisor's location
$supervisor_query = "SELECT SupervisorLocation FROM Supervisor WHERE SupervisorID = ?";
$stmt = $conn->prepare($supervisor_query);
$stmt->bind_param("i", $supervisorID);
$stmt->execute();
$supervisor_result = $stmt->get_result();
$supervisor_location = $supervisor_result->fetch_assoc()['SupervisorLocation'] ?? 'Boracay';

// Fetch dashboard statistics with location filtering
$stats = [];

// Total Vehicles (filtered by location)
$result = $conn->prepare("SELECT COUNT(*) as total FROM Vehicle WHERE VehicleLocation = ?");
$result->bind_param("s", $supervisor_location);
$result->execute();
$stats['total_vehicles'] = $result->get_result()->fetch_assoc()['total'];

// Total Drivers (no change needed)
$result = $conn->query("SELECT COUNT(*) as total FROM Driver");
$stats['total_drivers'] = $result->fetch_assoc()['total'];

// Reported Problems (filtered by location)
$result = $conn->prepare("SELECT COUNT(*) as total FROM VehicleReportProblem vrp 
                          JOIN Vehicle v ON vrp.VehicleID = v.VehicleID 
                          WHERE v.VehicleLocation = ?");
$result->bind_param("s", $supervisor_location);
$result->execute();
$stats['reported_problems'] = $result->get_result()->fetch_assoc()['total'];

// Vehicles with recent maintenance (filtered by location)
$result = $conn->prepare("SELECT COUNT(DISTINCT v.VehicleID) as total FROM MaintenanceHistory mh 
                          JOIN Vehicle v ON mh.VehicleID = v.VehicleID 
                          WHERE mh.InstalledDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                          AND v.VehicleLocation = ?");
$result->bind_param("s", $supervisor_location);
$result->execute();
$stats['recent_maintenance'] = $result->get_result()->fetch_assoc()['total'];

// Fetch route history with driver and vehicle details (filtered by location)
$route_query = "
    SELECT 
        r.RouteID,
        r.StartDateTime,
        r.EndDateTime,
        r.StartOdometer,
        r.EndOdometer,
        CONCAT(d.FirstName, ' ', d.LastName) as DriverName,
        v.PlateNumber,
        v.Model,
        v.VehicleLocation,
        CASE 
            WHEN r.EndDateTime IS NULL THEN 'In Progress'
            ELSE 'Completed'
        END as Status,
        CASE 
            WHEN r.EndOdometer IS NOT NULL AND r.StartOdometer IS NOT NULL 
            THEN ROUND(r.EndOdometer - r.StartOdometer, 2)
            ELSE 0
        END as Distance
    FROM Route r
    LEFT JOIN Driver d ON r.DriverID = d.DriverID
    LEFT JOIN Vehicle v ON r.VehicleID = v.VehicleID
    WHERE v.VehicleLocation = ?
    ORDER BY r.StartDateTime DESC
    LIMIT 10
";

$route_stmt = $conn->prepare($route_query);
$route_stmt->bind_param("s", $supervisor_location);
$route_stmt->execute();
$route_result = $route_stmt->get_result();
$routes = [];
while ($row = $route_result->fetch_assoc()) {
    $routes[] = $row;
}

// Fetch recent vehicle problems (filtered by location)
$problems_query = "
    SELECT 
        vrp.Title,
        vrp.Description,
        vrp.OdometerReading,
        vrp.ReportDateTime,
        v.PlateNumber,
        v.Model,
        v.VehicleLocation
    FROM VehicleReportProblem vrp
    LEFT JOIN Vehicle v ON vrp.VehicleID = v.VehicleID
    WHERE v.VehicleLocation = ?
    ORDER BY vrp.ReportDateTime DESC
    LIMIT 5
";

$problems_stmt = $conn->prepare($problems_query);
$problems_stmt->bind_param("s", $supervisor_location);
$problems_stmt->execute();
$problems_result = $problems_stmt->get_result();
$problems = [];
while ($row = $problems_result->fetch_assoc()) {
    $problems[] = $row;
}

// Fetch recent maintenance history (filtered by location)
$maintenance_query = "
    SELECT 
        mh.InstalledParts,
        mh.InstalledDate,
        mh.PartsSpecifications,
        v.PlateNumber,
        v.Model,
        v.VehicleLocation
    FROM MaintenanceHistory mh
    LEFT JOIN Vehicle v ON mh.VehicleID = v.VehicleID
    WHERE v.VehicleLocation = ?
    ORDER BY mh.InstalledDate DESC
    LIMIT 5
";

$maintenance_stmt = $conn->prepare($maintenance_query);
$maintenance_stmt->bind_param("s", $supervisor_location);
$maintenance_stmt->execute();
$maintenance_result = $maintenance_stmt->get_result();
$maintenance_history = [];
while ($row = $maintenance_result->fetch_assoc()) {
    $maintenance_history[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Management System - Supervisor Dashboard</title>
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
            font-size: 1.5rem;
            color: white;
        }

        .widget-icon.blue {
            background: linear-gradient(135deg, #3b82f6, #1e40af);
        }

        .widget-icon.green {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .widget-icon.red {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .widget-icon.yellow {
            background: linear-gradient(135deg, #f59e0b, #d97706);
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
                    <p class="sidebar-subtitle">Vehicle Management System</p>
                </div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="#" class="nav-item active">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
            <a href="#" class="nav-item" onclick="window.location.href='supervisor-vehicle-list'" >
                <i class="fas fa-car"></i>
                Vehicle Management
            </a>
            <a href="#" class="nav-item" onclick="window.location.href='supervisor-driver-list'">
                <i class="fas fa-users"></i>
                Driver Management
            </a>
            <a href="supervisor-maintenance-alerts.php" class="nav-item">
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
                <h1 class="page-title">Dashboard</h1>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600; font-size: 0.875rem;">Supervisor</div>
                        <div style="font-size: 0.75rem; color: #64748b;">Admin</div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="content">
            <!-- Dashboard Widgets -->
            <div class="dashboard-grid">
                <!-- Total Vehicles -->
                <div class="widget">
                    <div class="widget-header">
                        <div class="widget-title">Total Vehicles</div>
                        <div class="widget-icon blue">
                            <i class="fas fa-car"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['total_vehicles']; ?></div>
                    <div class="widget-label">Registered vehicles</div>
                </div>

                <!-- Total Drivers -->
                <div class="widget">
                    <div class="widget-header">
                        <div class="widget-title">Total Drivers</div>
                        <div class="widget-icon green">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['total_drivers']; ?></div>
                    <div class="widget-label">Active drivers</div>
                </div>

                <!-- Reported Problems -->
                <div class="widget">
                    <div class="widget-header">
                        <div class="widget-title">Reported Problems</div>
                        <div class="widget-icon red">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['reported_problems']; ?></div>
                    <div class="widget-label">Active issues</div>
                </div>

                <!-- Recent Maintenance -->
                <div class="widget">
                    <div class="widget-header">
                        <div class="widget-title">Recent Maintenance</div>
                        <div class="widget-icon yellow">
                            <i class="fas fa-wrench"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['recent_maintenance']; ?></div>
                    <div class="widget-label">Last 30 days</div>
                </div>
            </div>

            <!-- Data Tables with Tabs -->
            <div class="table-container">
                <div class="table-header">
                    <h2 class="table-title">System Overview</h2>
                    <p class="table-subtitle">Monitor vehicle routes, problems, and maintenance activities</p>
                    
                    <div class="tabs">
                        <button class="tab active" onclick="showTab(event, 'routes')">Route History</button>
                        <button class="tab" onclick="showTab(event, 'problems')">Vehicle Problems</button>
                        <button class="tab" onclick="showTab(event, 'maintenance')">Maintenance History</button>
                    </div>
                </div>

                <!-- Route History Tab -->
                <div id="routes" class="tab-content active">
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Route ID</th>
                                    <th>Date</th>
                                    <th>Driver</th>
                                    <th>Vehicle</th>
                                    <th>Model</th>
                                    <th>Location</th>
                                    <th>Distance (km)</th>
                                    <th>Current Odometer</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($routes as $route): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($route['RouteID']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($route['StartDateTime'])); ?></td>
                                    <td class="driver-name"><?php echo htmlspecialchars($route['DriverName']); ?></td>
                                    <td class="vehicle-plate"><?php echo htmlspecialchars($route['PlateNumber']); ?></td>
                                    <td><?php echo htmlspecialchars($route['Model']); ?></td>
                                    <td><?php echo htmlspecialchars($route['VehicleLocation']); ?></td>
                                    <td><?php echo $route['Distance']; ?></td>
                                    <td><?php echo $route['EndOdometer'] ?? $route['StartOdometer']; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $route['Status'] == 'Completed' ? 'status-completed' : 'status-in-progress'; ?>">
                                            <?php echo $route['Status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($routes)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 2rem; color: #64748b;">
                                       No route data available for your location
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Vehicle Problems Tab -->
                <div id="problems" class="tab-content">
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Report Date</th>
                                    <th>Vehicle</th>
                                    <th>Model</th>
                                    <th>Location</th>
                                    <th>Problem Title</th>
                                    <th>Description</th>
                                    <th>Odometer Reading</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($problems as $problem): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d', strtotime($problem['ReportDateTime'])); ?></td>
                                    <td class="vehicle-plate"><?php echo htmlspecialchars($problem['PlateNumber']); ?></td>
                                    <td><?php echo htmlspecialchars($problem['Model']); ?></td>
                                    <td><?php echo htmlspecialchars($problem['VehicleLocation']); ?></td>
                                    <td><?php echo htmlspecialchars($problem['Title']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($problem['Description'], 0, 50)) . '...'; ?></td>
                                    <td><?php echo $problem['OdometerReading']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($problems)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 2rem; color: #64748b;">
                                        No vehicle problems reported
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Maintenance History Tab -->
                <div id="maintenance" class="tab-content">
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Vehicle</th>
                                    <th>Model</th>
                                    <th>Location</th>
                                    <th>Installed Parts</th>
                                    <th>Specifications</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($maintenance_history as $maintenance): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d', strtotime($maintenance['InstalledDate'])); ?></td>
                                    <td class="vehicle-plate"><?php echo htmlspecialchars($maintenance['PlateNumber']); ?></td>
                                    <td><?php echo htmlspecialchars($maintenance['Model']); ?></td>
                                    <td><?php echo htmlspecialchars($maintenance['VehicleLocation']); ?></td>
                                    <td><?php echo htmlspecialchars($maintenance['InstalledParts']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($maintenance['PartsSpecifications'] ?? 'N/A', 0, 50)) . '...'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($maintenance_history)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem; color: #64748b;">
                                        No maintenance history available
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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

        // Navigation functionality
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                navItems.forEach(nav => nav.classList.remove('active'));
                item.classList.add('active');
                
                // Close sidebar on mobile after selection
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                }
            });
        });

        // Tab functionality
        function showTab(event, tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });

            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        // Auto-refresh data every 5 minutes
        setInterval(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>