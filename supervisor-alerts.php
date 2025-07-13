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

// 1. Unresolved Problems - Count of reported vehicle problems awaiting resolution
$unresolved_query = "
    SELECT COUNT(*) as count
    FROM VehicleReportProblem vrp
    JOIN Vehicle v ON vrp.VehicleID = v.VehicleID
    WHERE v.VehicleLocation = ?
    AND vrp.ReportID NOT IN (
        SELECT DISTINCT vrp2.ReportID 
        FROM VehicleReportProblem vrp2
        JOIN MaintenanceHistory mh ON vrp2.VehicleID = mh.VehicleID
        WHERE mh.InstalledDate >= DATE(vrp2.ReportDateTime)
    )
";
$unresolved_stmt = $conn->prepare($unresolved_query);
$unresolved_stmt->bind_param("s", $supervisor_location);
$unresolved_stmt->execute();
$unresolved_result = $unresolved_stmt->get_result();
$unresolved_count = $unresolved_result->fetch_assoc()['count'];
$unresolved_stmt->close();

// 2. Recurring Issues - Vehicles with repeated problem reports (2+ reports in last 90 days)
$recurring_query = "
    SELECT COUNT(DISTINCT v.VehicleID) as count
    FROM Vehicle v
    WHERE v.VehicleLocation = ?
    AND (
        SELECT COUNT(*) 
        FROM VehicleReportProblem vrp 
        WHERE vrp.VehicleID = v.VehicleID 
        AND vrp.ReportDateTime >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    ) >= 2
";
$recurring_stmt = $conn->prepare($recurring_query);
$recurring_stmt->bind_param("s", $supervisor_location);
$recurring_stmt->execute();
$recurring_result = $recurring_stmt->get_result();
$recurring_count = $recurring_result->fetch_assoc()['count'];
$recurring_stmt->close();

// 3. Odometer Anomalies - Vehicles with suspicious readings
$anomalies_query = "
    SELECT COUNT(DISTINCT v.VehicleID) as count
    FROM Vehicle v
    WHERE v.VehicleLocation = ?
    AND v.VehicleID IN (
        SELECT or1.VehicleID
        FROM OdometerReadings or1
        JOIN OdometerReadings or2 ON or1.VehicleID = or2.VehicleID
        WHERE or1.ReadingDateTime < or2.ReadingDateTime
        AND (
            -- Odometer went backwards (suspicious)
            or1.OdometerReading > or2.OdometerReading
            OR
            -- Huge jump in odometer (more than 500km in one day)
            (or2.OdometerReading - or1.OdometerReading > 500 
             AND DATEDIFF(or2.ReadingDateTime, or1.ReadingDateTime) <= 1)
            OR
            -- No odometer change for more than 30 days between readings
            (or2.OdometerReading = or1.OdometerReading 
             AND DATEDIFF(or2.ReadingDateTime, or1.ReadingDateTime) > 30)
        )
    )
";
$anomalies_stmt = $conn->prepare($anomalies_query);
$anomalies_stmt->bind_param("s", $supervisor_location);
$anomalies_stmt->execute();
$anomalies_result = $anomalies_stmt->get_result();
$anomalies_count = $anomalies_result->fetch_assoc()['count'];
$anomalies_stmt->close();

// 4. Total Active Issues (combination of all above)
$total_issues = $unresolved_count + $recurring_count + $anomalies_count;

// Get detailed data for tables
$alerts = [];

// Get unresolved problems details
$unresolved_details_query = "
    SELECT 
        vrp.ReportID,
        vrp.Title,
        vrp.Description,
        vrp.OdometerReading,
        vrp.ReportDateTime,
        v.VehicleID,
        v.PlateNumber,
        v.Model,
        v.ChassisNumber,
        v.VehicleLocation,
        DATEDIFF(CURDATE(), DATE(vrp.ReportDateTime)) as DaysOpen,
        'UNRESOLVED' as AlertType,
        CASE 
            WHEN DATEDIFF(CURDATE(), DATE(vrp.ReportDateTime)) > 30 THEN 'CRITICAL'
            WHEN DATEDIFF(CURDATE(), DATE(vrp.ReportDateTime)) > 14 THEN 'HIGH'
            ELSE 'MEDIUM'
        END as AlertLevel
    FROM VehicleReportProblem vrp
    JOIN Vehicle v ON vrp.VehicleID = v.VehicleID
    WHERE v.VehicleLocation = ?
    AND vrp.ReportID NOT IN (
        SELECT DISTINCT vrp2.ReportID 
        FROM VehicleReportProblem vrp2
        JOIN MaintenanceHistory mh ON vrp2.VehicleID = mh.VehicleID
        WHERE mh.InstalledDate >= DATE(vrp2.ReportDateTime)
    )
    ORDER BY vrp.ReportDateTime ASC
";
$unresolved_details_stmt = $conn->prepare($unresolved_details_query);
$unresolved_details_stmt->bind_param("s", $supervisor_location);
$unresolved_details_stmt->execute();
$unresolved_details_result = $unresolved_details_stmt->get_result();
while ($row = $unresolved_details_result->fetch_assoc()) {
    $alerts[] = $row;
}
$unresolved_details_stmt->close();

// Get recurring issues details
$recurring_details_query = "
    SELECT 
        v.VehicleID,
        v.PlateNumber,
        v.Model,
        v.ChassisNumber,
        v.VehicleLocation,
        COUNT(vrp.ReportID) as ProblemCount,
        GROUP_CONCAT(DISTINCT vrp.Title SEPARATOR ', ') as ProblemTitles,
        MAX(vrp.ReportDateTime) as LastReportDate,
        MIN(vrp.ReportDateTime) as FirstReportDate,
        'RECURRING' as AlertType,
        CASE 
            WHEN COUNT(vrp.ReportID) >= 5 THEN 'CRITICAL'
            WHEN COUNT(vrp.ReportID) >= 3 THEN 'HIGH'
            ELSE 'MEDIUM'
        END as AlertLevel
    FROM Vehicle v
    JOIN VehicleReportProblem vrp ON v.VehicleID = vrp.VehicleID
    WHERE v.VehicleLocation = ?
    AND vrp.ReportDateTime >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    GROUP BY v.VehicleID, v.PlateNumber, v.Model, v.ChassisNumber, v.VehicleLocation
    HAVING COUNT(vrp.ReportID) >= 2
    ORDER BY ProblemCount DESC, LastReportDate DESC
";
$recurring_details_stmt = $conn->prepare($recurring_details_query);
$recurring_details_stmt->bind_param("s", $supervisor_location);
$recurring_details_stmt->execute();
$recurring_details_result = $recurring_details_stmt->get_result();
while ($row = $recurring_details_result->fetch_assoc()) {
    $alerts[] = $row;
}
$recurring_details_stmt->close();

// Get odometer anomalies details
$anomalies_details_query = "
    SELECT DISTINCT
        v.VehicleID,
        v.PlateNumber,
        v.Model,
        v.ChassisNumber,
        v.VehicleLocation,
        MAX(or1.OdometerReading) as LastOdometerReading,
        MAX(or1.ReadingDateTime) as LastReadingDate,
        'ODOMETER_ANOMALY' as AlertType,
        'HIGH' as AlertLevel,
        CASE 
            WHEN EXISTS (
                SELECT 1 FROM OdometerReadings or_check1
                JOIN OdometerReadings or_check2 ON or_check1.VehicleID = or_check2.VehicleID
                WHERE or_check1.VehicleID = v.VehicleID
                AND or_check1.ReadingDateTime < or_check2.ReadingDateTime
                AND or_check1.OdometerReading > or_check2.OdometerReading
            ) THEN 'Odometer reading decreased'
            WHEN EXISTS (
                SELECT 1 FROM OdometerReadings or_check1
                JOIN OdometerReadings or_check2 ON or_check1.VehicleID = or_check2.VehicleID
                WHERE or_check1.VehicleID = v.VehicleID
                AND or_check1.ReadingDateTime < or_check2.ReadingDateTime
                AND (or_check2.OdometerReading - or_check1.OdometerReading > 500)
                AND DATEDIFF(or_check2.ReadingDateTime, or_check1.ReadingDateTime) <= 1
            ) THEN 'Suspicious odometer jump'
            ELSE 'No odometer change for extended period'
        END as AnomalyReason
    FROM Vehicle v
    JOIN OdometerReadings or1 ON v.VehicleID = or1.VehicleID
    WHERE v.VehicleLocation = ?
    AND v.VehicleID IN (
        SELECT or_sub1.VehicleID
        FROM OdometerReadings or_sub1
        JOIN OdometerReadings or_sub2 ON or_sub1.VehicleID = or_sub2.VehicleID
        WHERE or_sub1.ReadingDateTime < or_sub2.ReadingDateTime
        AND (
            or_sub1.OdometerReading > or_sub2.OdometerReading
            OR
            (or_sub2.OdometerReading - or_sub1.OdometerReading > 500 
             AND DATEDIFF(or_sub2.ReadingDateTime, or_sub1.ReadingDateTime) <= 1)
            OR
            (or_sub2.OdometerReading = or_sub1.OdometerReading 
             AND DATEDIFF(or_sub2.ReadingDateTime, or_sub1.ReadingDateTime) > 30)
        )
    )
    GROUP BY v.VehicleID, v.PlateNumber, v.Model, v.ChassisNumber, v.VehicleLocation
    ORDER BY LastReadingDate DESC
";
$anomalies_details_stmt = $conn->prepare($anomalies_details_query);
$anomalies_details_stmt->bind_param("s", $supervisor_location);
$anomalies_details_stmt->execute();
$anomalies_details_result = $anomalies_details_stmt->get_result();
while ($row = $anomalies_details_result->fetch_assoc()) {
    $alerts[] = $row;
}
$anomalies_details_stmt->close();

// Get recent problems (for the problems tab)
$problems_query = "
    SELECT 
        vrp.ReportID,
        vrp.Title,
        vrp.Description,
        vrp.OdometerReading,
        vrp.ReportDateTime,
        v.PlateNumber,
        v.Model,
        v.VehicleLocation,
        CASE 
            WHEN EXISTS (
                SELECT 1 FROM MaintenanceHistory mh 
                WHERE mh.VehicleID = v.VehicleID 
                AND mh.InstalledDate >= DATE(vrp.ReportDateTime)
            ) THEN 'Resolved'
            ELSE 'Pending'
        END as Status
    FROM VehicleReportProblem vrp
    JOIN Vehicle v ON vrp.VehicleID = v.VehicleID
    WHERE v.VehicleLocation = ? AND vrp.ReportDateTime >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY vrp.ReportDateTime DESC
";
$problems_stmt = $conn->prepare($problems_query);
$problems_stmt->bind_param("s", $supervisor_location);
$problems_stmt->execute();
$problems_result = $problems_stmt->get_result();
$recent_problems = [];
while ($row = $problems_result->fetch_assoc()) {
    $recent_problems[] = $row;
}
$problems_stmt->close();

// Calculate statistics
$stats = [];
$stats['unresolved_problems'] = $unresolved_count;
$stats['recurring_issues'] = $recurring_count;
$stats['odometer_anomalies'] = $anomalies_count;
$stats['total_issues'] = $total_issues;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Maintenance System - Maintenance Alerts</title>
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
            z-index: 1001;
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
        .alert-critical {
            background: #fef2f2;
            color: #dc2626;
        }

        .alert-high {
            background: #fef3c7;
            color: #d97706;
        }

        .alert-medium {
            background: #dbeafe;
            color: #2563eb;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .schedule-btn {
            background: #3b82f6;
            color: white;
        }

        .schedule-btn:hover {
            background: #2563eb;
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
            <a href="#" class="nav-item active">
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
                <h1 class="page-title">Maintenance Alerts</h1>
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
            <!-- Updated Alert Statistics -->
            <div class="dashboard-grid">
                <!-- Unresolved Problems -->
                <div class="widget">
                    <div class="widget-header">
                        <div class="widget-title">Unresolved Problems</div>
                        <div class="widget-icon red">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['unresolved_problems']; ?></div>
                    <div class="widget-label">Reported problems awaiting resolution</div>
                </div>

                <!-- Recurring Issues -->
                <div class="widget">
                    <div class="widget-header">
                        <div class="widget-title">Recurring Issues</div>
                        <div class="widget-icon yellow">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['recurring_issues']; ?></div>
                    <div class="widget-label">Vehicles with repeated problems</div>
                </div>

                <!-- Odometer Anomalies -->
                <div class="widget">
                    <div class="widget-header">
                        <div class="widget-title">Odometer Anomalies</div>
                        <div class="widget-icon blue">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['odometer_anomalies']; ?></div>
                    <div class="widget-label">Suspicious odometer readings</div>
                </div>

                <!-- Total Issues -->
                <div class="widget">
                    <div class="widget-header">
                        <div class="widget-title">Total Issues</div>
                        <div class="widget-icon green">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                    </div>
                    <div class="widget-value"><?php echo $stats['total_issues']; ?></div>
                    <div class="widget-label">All active issues</div>
                </div>
            </div>

            <!-- Updated Alerts Table -->
            <div class="table-container">
                <div class="table-header">
                    <h2 class="table-title">Vehicle Issues & Alerts</h2>
                    <p class="table-subtitle">All vehicle issues requiring attention in <?php echo htmlspecialchars($supervisor_location); ?></p>
                    
                    <div class="tabs">
                        <button class="tab active" onclick="showTab(event, 'alerts')">All Issues</button>
                        <button class="tab" onclick="showTab(event, 'problems')">Recent Problems</button>
                    </div>
                </div>

                <!-- All Issues Tab -->
                <div id="alerts" class="tab-content active">
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Priority</th>
                                    <th>Issue Type</th>
                                    <th>Vehicle</th>
                                    <th>Model</th>
                                    <th>Issue Details</th>
                                    <th>Date/Count</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alerts as $alert): ?>
                                <tr>
                                    <td>
                                        <span class="status-badge <?php 
                                            echo $alert['AlertLevel'] == 'CRITICAL' ? 'alert-critical' : 
                                                ($alert['AlertLevel'] == 'HIGH' ? 'alert-high' : 'alert-medium'); 
                                        ?>">
                                            <?php echo htmlspecialchars($alert['AlertLevel']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php 
                                            echo $alert['AlertType'] == 'UNRESOLVED' ? 'status-pending' : 
                                                ($alert['AlertType'] == 'RECURRING' ? 'alert-high' : 'alert-medium'); 
                                        ?>">
                                            <?php 
                                            echo $alert['AlertType'] == 'UNRESOLVED' ? 'Unresolved' : 
                                                ($alert['AlertType'] == 'RECURRING' ? 'Recurring' : 'Odometer Issue'); 
                                            ?>
                                        </span>
                                    </td>
                                    <td class="vehicle-plate"><?php echo htmlspecialchars($alert['PlateNumber']); ?></td>
                                    <td><?php echo htmlspecialchars($alert['Model']); ?></td>
                                    <td>
                                        <?php if ($alert['AlertType'] == 'UNRESOLVED'): ?>
                                            <strong><?php echo htmlspecialchars($alert['Title']); ?></strong><br>
                                            <small><?php echo htmlspecialchars(substr($alert['Description'], 0, 50)) . '...'; ?></small>
                                        <?php elseif ($alert['AlertType'] == 'RECURRING'): ?>
                                            <strong><?php echo $alert['ProblemCount']; ?> problems in 90 days</strong><br>
                                            <small><?php echo htmlspecialchars(substr($alert['ProblemTitles'], 0, 50)) . '...'; ?></small>
                                        <?php else: ?>
                                            <strong>Odometer Anomaly</strong><br>
                                            <small><?php echo htmlspecialchars($alert['AnomalyReason']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($alert['AlertType'] == 'UNRESOLVED'): ?>
                                            <?php echo $alert['DaysOpen']; ?> days ago<br>
                                            <small><?php echo date('Y-m-d', strtotime($alert['ReportDateTime'])); ?></small>
                                        <?php elseif ($alert['AlertType'] == 'RECURRING'): ?>
                                            Last: <?php echo date('Y-m-d', strtotime($alert['LastReportDate'])); ?><br>
                                            <small>First: <?php echo date('Y-m-d', strtotime($alert['FirstReportDate'])); ?></small>
                                        <?php else: ?>
                                            <?php echo date('Y-m-d', strtotime($alert['LastReadingDate'])); ?><br>
                                            <small><?php echo number_format($alert['LastOdometerReading'], 0); ?> km</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="action-btn schedule-btn" onclick="resolveIssue(<?php echo $alert['VehicleID']; ?>, '<?php echo $alert['AlertType']; ?>')">
                                            <i class="fas fa-wrench"></i>
                                            <?php echo $alert['AlertType'] == 'ODOMETER_ANOMALY' ? 'Investigate' : 'Resolve'; ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($alerts)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 2rem; color: #64748b;">
                                        <i class="fas fa-check-circle" style="font-size: 2rem; color: #10b981; margin-bottom: 1rem; display: block;"></i>
                                        No active issues for vehicles in <?php echo htmlspecialchars($supervisor_location); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Problems Tab -->
                <div id="problems" class="tab-content">
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Report Date</th>
                                    <th>Vehicle</th>
                                    <th>Model</th>
                                    <th>Problem Title</th>
                                    <th>Description</th>
                                    <th>Odometer</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_problems as $problem): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i', strtotime($problem['ReportDateTime'])); ?></td>
                                    <td class="vehicle-plate"><?php echo htmlspecialchars($problem['PlateNumber']); ?></td>
                                    <td><?php echo htmlspecialchars($problem['Model']); ?></td>
                                    <td><?php echo htmlspecialchars($problem['Title']); ?></td>
                                    <td title="<?php echo htmlspecialchars($problem['Description']); ?>">
                                        <?php echo htmlspecialchars(substr($problem['Description'], 0, 50)) . '...'; ?>
                                    </td>
                                    <td><?php echo number_format($problem['OdometerReading'], 0); ?> km</td>
                                    <td>
                                        <span class="status-badge <?php echo $problem['Status'] == 'Resolved' ? 'status-completed' : 'status-pending'; ?>">
                                            <?php echo $problem['Status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recent_problems)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 2rem; color: #64748b;">
                                        No recent problems reported
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
const body = document.body;

if (menuToggle && sidebar && overlay) {
    // Open sidebar
    menuToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleSidebar();
    });

    // Close sidebar when clicking overlay
    overlay.addEventListener('click', function() {
        closeSidebar();
    });

    // Close sidebar when clicking outside
    document.addEventListener('click', function(e) {
        if (sidebar.classList.contains('active') && 
            !sidebar.contains(e.target) && 
            !menuToggle.contains(e.target)) {
            closeSidebar();
        }
    });

    // Prevent sidebar from closing when clicking inside it
    sidebar.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            closeSidebar();
        }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
            closeSidebar();
        }
    });
}

function toggleSidebar() {
    if (sidebar.classList.contains('active')) {
        closeSidebar();
    } else {
        openSidebar();
    }
}

function openSidebar() {
    sidebar.classList.add('active');
    overlay.classList.add('active');
    body.classList.add('sidebar-open');
    
    // Add accessibility
    sidebar.setAttribute('aria-hidden', 'false');
    menuToggle.setAttribute('aria-expanded', 'true');
}

function closeSidebar() {
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
    body.classList.remove('sidebar-open');
    
    // Add accessibility
    sidebar.setAttribute('aria-hidden', 'true');
    menuToggle.setAttribute('aria-expanded', 'false');
}

// Navigation functionality
const navItems = document.querySelectorAll('.nav-item');
navItems.forEach(item => {
    if (!item.classList.contains('logout-link')) {
        item.addEventListener('click', (e) => {
            if (item.getAttribute('href') === '#') {
                e.preventDefault();
            }
            navItems.forEach(nav => nav.classList.remove('active'));
            item.classList.add('active');

            // Close sidebar on mobile after navigation
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    }
});

// Initialize accessibility attributes
document.addEventListener('DOMContentLoaded', function() {
    if (sidebar && menuToggle) {
        sidebar.setAttribute('aria-hidden', 'true');
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-controls', 'sidebar');
        menuToggle.setAttribute('aria-label', 'Toggle navigation menu');
    }
});

// Updated function for resolving issues
function resolveIssue(vehicleId, alertType) {
    let action = '';
    switch(alertType) {
        case 'UNRESOLVED':
            action = 'Schedule maintenance to resolve reported problem';
            break;
        case 'RECURRING':
            action = 'Investigate recurring issues and schedule comprehensive maintenance';
            break;
        case 'ODOMETER_ANOMALY':
            action = 'Investigate odometer anomaly and verify readings';
            break;
    }
    
    if (confirm(`${action} for vehicle ID: ${vehicleId}?`)) {
        // Redirect to appropriate action page
        window.location.href = `handle-issue.php?vehicle_id=${vehicleId}&type=${alertType}`;
    }
}

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

// Auto-refresh alerts every 10 minutes
setInterval(() => {
    location.reload();
}, 600000);

// Add notification for high priority issues
document.addEventListener('DOMContentLoaded', function() {
    const totalIssues = <?php echo $stats['total_issues']; ?>;
    const unresolvedProblems = <?php echo $stats['unresolved_problems']; ?>;
    
    if (unresolvedProblems > 0) {
        console.log(`${unresolvedProblems} unresolved problems require attention!`);
    }
    
    if (totalIssues > 10) {
        console.log(`High volume of issues detected: ${totalIssues} total issues`);
    }
});
    </script>
</body>
</html>