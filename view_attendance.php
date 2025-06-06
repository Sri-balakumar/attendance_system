<?php
// Handle Excel download request
if (isset($_GET['download']) && $_GET['download'] === 'excel') {
    // Get filter parameters for download
    $filter_department = isset($_GET['department']) ? $_GET['department'] : '';
    $filter_date = isset($_GET['date']) ? $_GET['date'] : '';
    $filter_status = isset($_GET['status']) ? $_GET['status'] : '';
    $filter_register = isset($_GET['register_no']) ? $_GET['register_no'] : '';
    
    // Database connection for download
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "attendance_db;";
    $conn = new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Build WHERE clause for download
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    if (!empty($filter_department)) {
        $where_conditions[] = "ar.department = ?";
        $params[] = $filter_department;
        $param_types .= 's';
    }
    
    if (!empty($filter_date)) {
        $where_conditions[] = "ar.attendance_date = ?";
        $params[] = $filter_date;
        $param_types .= 's';
    }
    
    if (!empty($filter_status)) {
        $where_conditions[] = "ar.status = ?";
        $params[] = $filter_status;
        $param_types .= 's';
    }
    
    if (!empty($filter_register)) {
        $where_conditions[] = "ar.register_no LIKE ?";
        $params[] = '%' . $filter_register . '%';
        $param_types .= 's';
    }
    
    // Build SQL query for download
    $sql = "SELECT ar.id, ar.register_no, s.name, ar.attendance_date, ar.status, ar.department 
            FROM attendance_records ar 
            LEFT JOIN students s ON ar.register_no = s.register_no";
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY ar.attendance_date ASC,
              ar.department ASC, 
              CAST(SUBSTRING(ar.register_no, 3) AS UNSIGNED) ASC,
              ar.register_no ASC";
    
    // Execute query for download
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if (!empty($param_types) && !empty($params)) {
                $stmt->bind_param($param_types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            die("SQL Prepare Error: " . $conn->error);
        }
    } else {
        $result = $conn->query($sql);
        if (!$result) {
            die("SQL Error: " . $conn->error);
        }
    }
    
    // Generate filename with current timestamp and filters
    $filename = "attendance_records_" . date('Y-m-d_H-i-s');
    if (!empty($filter_department)) $filename .= "_" . str_replace(' ', '_', $filter_department);
    if (!empty($filter_date)) $filename .= "_" . $filter_date;
    if (!empty($filter_status)) $filename .= "_" . $filter_status;
    $filename .= ".xls";
    
    // Set headers for download
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add CSV headers
    fputcsv($output, array(
        'ID',
        'Register Number',
        'Student Name',
        'Department',
        'Date',
        'Status'
    ));
    
    // Add data rows
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Format date for Excel compatibility
            $formatted_date = date('m/d/Y', strtotime($row['attendance_date']));
            
            fputcsv($output, array(
                $row['id'],
                $row['register_no'],
                $row['name'] ?? 'N/A',
                $row['department'],
                $formatted_date,
                ucfirst($row['status'])
            ));
        }
    }
    
    fclose($output);
    $conn->close();
    exit();
}

// Database connection settings
$host = "localhost";
$username = "root";
$password = "";
$database = "attendance_db;";

$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

// Set default date to today if not set
$filter_date = isset($_GET['date']) && !empty($_GET['date']) ? date('Y-m-d', strtotime($_GET['date'])) : date('Y-m-d');
$filter_department = isset($_GET['department']) ? $_GET['department'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_register = isset($_GET['register_no']) ? $_GET['register_no'] : '';

// Get departments with attendance for the selected date
$dept_sql = "SELECT DISTINCT ar.department 
             FROM attendance_records ar 
             WHERE ar.attendance_date = ? 
             ORDER BY ar.department";
$stmt = $conn->prepare($dept_sql);
$stmt->bind_param('s', $filter_date);
$stmt->execute();
$dept_result = $stmt->get_result();
$departments = [];
if ($dept_result && $dept_result->num_rows > 0) {
    while ($row = $dept_result->fetch_assoc()) {
        $departments[] = $row['department'];
    }
}

// Get all departments and their completion status
$all_depts_sql = "SELECT DISTINCT department FROM students ORDER BY department";
$all_depts_result = $conn->query($all_depts_sql);
$all_departments = [];
if ($all_depts_result && $all_depts_result->num_rows > 0) {
    while ($row = $all_depts_result->fetch_assoc()) {
        $all_departments[] = $row['department'];
    }
}

// Determine completed and pending departments
$completed_depts = $departments;
$pending_depts = array_diff($all_departments, $completed_depts);

// Get valid dates (attendance records or holidays)
$valid_dates_sql = "SELECT DISTINCT attendance_date AS date FROM attendance_records 
                    UNION 
                    SELECT holiday_date AS date FROM holidays 
                    ORDER BY date";
$valid_dates_result = $conn->query($valid_dates_sql);
$valid_dates = [];
if ($valid_dates_result) {
    while ($row = $valid_dates_result->fetch_assoc()) {
        $valid_dates[] = $row['date'];
    }
}

// Build WHERE clause based on filters
$where_conditions = [];
$params = [];
$param_types = '';

$where_conditions[] = "ar.attendance_date = ?";
$params[] = $filter_date;
$param_types .= 's';

if (!empty($filter_department)) {
    $where_conditions[] = "ar.department = ?";
    $params[] = $filter_department;
    $param_types .= 's';
}

if (!empty($filter_status)) {
    $where_conditions[] = "ar.status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

if (!empty($filter_register)) {
    $where_conditions[] = "ar.register_no LIKE ?";
    $params[] = '%' . $filter_register . '%';
    $param_types .= 's';
}

// Build SQL query with proper ordering
$sql = "SELECT ar.id, ar.register_no, s.name, ar.attendance_date, ar.status, ar.department 
        FROM attendance_records ar 
        LEFT JOIN students s ON ar.register_no = s.register_no";

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY ar.attendance_date ASC,
          ar.department ASC, 
          CAST(SUBSTRING(ar.register_no, 3) AS UNSIGNED) ASC,
          ar.register_no ASC";

// Prepare and execute query
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($param_types) && !empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        die("❌ SQL Prepare Error: " . $conn->error);
    }
} else {
    $result = $conn->query($sql);
    if (!$result) {
        die("❌ SQL Error: " . $conn->error);
    }
}

// Get date range for filter
$date_sql = "SELECT MIN(date) as min_date, MAX(date) as max_date 
             FROM (
                 SELECT attendance_date AS date FROM attendance_records 
                 UNION 
                 SELECT holiday_date AS date FROM holidays
             ) dates";
$date_result = $conn->query($date_sql);
$date_range = ['min_date' => '', 'max_date' => ''];
if ($date_result && $date_result->num_rows > 0) {
    $date_range = $date_result->fetch_assoc();
}

// Function to build download URL with current filters
function buildDownloadUrl() {
    $params = $_GET;
    $params['download'] = 'excel';
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - Attendance Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(circle at 20% 30%, rgba(52, 152, 219, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(155, 89, 182, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 60% 20%, rgba(46, 204, 113, 0.06) 0%, transparent 50%),
                radial-gradient(circle at 30% 80%, rgba(241, 196, 15, 0.05) 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
            z-index: -1;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(-20px, -20px) rotate(1deg); }
            66% { transform: translate(20px, -10px) rotate(-1deg); }
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 0;
            width: 100%;
            max-width: 1400px;
            margin: 20px auto;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            text-align: center;
            padding: 40px 20px;
            position: relative;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .logo {
            font-size: 3.5rem;
            margin-bottom: 12px;
            color: #3498db;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 8px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .subtitle {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 300;
            letter-spacing: 1px;
        }

        .content {
            padding: 50px 40px;
        }

        .filter-section {
            background: rgba(52, 152, 219, 0.05);
            border: 1px solid rgba(52, 152, 219, 0.1);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 40px;
        }

        .filter-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-title i {
            color: #3498db;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95rem;
        }

        .form-label i {
            margin-right: 8px;
            color: #3498db;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 16px;
            border: 2px solid #ecf0f1;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(248, 249, 250, 0.8);
            backdrop-filter: blur(5px);
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #3498db;
            background: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            transform: translateY(-2px);
        }

        .form-select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 15px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 45px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .filter-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 16px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 140px;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }

        .btn-primary:hover {
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
        }

        .btn-secondary:hover {
            box-shadow: 0 10px 30px rgba(149, 165, 166, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
        }

        .btn-success:hover {
            box-shadow: 0 10px 30px rgba(46, 204, 113, 0.4);
        }

        .btn-holiday {
            background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
            color: white;
        }

        .btn-holiday:hover {
            box-shadow: 0 10px 30px rgba(230, 126, 34, 0.4);
        }

        .stats-section {
            background: rgba(46, 204, 113, 0.05);
            border: 1px solid rgba(46, 204, 113, 0.1);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 40px;
        }

        .stats-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-title i {
            color: #2ecc71;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 25px 15px;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #7f8c8d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .dept-list {
            font-size: 0.9rem;
            color: #2c3e50;
            line-height: 1.6;
            text-align: left;
            padding: 10px;
        }

        .table-section {
            background: rgba(155, 89, 182, 0.05);
            border: 1px solid rgba(155, 89, 182, 0.1);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 40px;
            overflow: hidden;
        }

        .table-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-title i {
            color: #9b59b6;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }

        th, td {
            padding: 18px 15px;
            text-align: center;
            border-bottom: 1px solid #ecf0f1;
        }

        th {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        tr:hover td {
            background: rgba(52, 152, 219, 0.05);
        }

        .status-present {
            color: #2ecc71;
            font-weight: 700;
        }

        .status-absent {
            color: #e74c3c;
            font-weight: 700;
        }

        .register-no {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #2c3e50;
            background: rgba(52, 152, 219, 0.1);
            padding: 6px 10px;
            border-radius: 6px;
        }

        .no-records {
            background: white;
            padding: 60px 40px;
            border-radius: 12px;
            text-align: center;
            font-size: 1.2rem;
            color: #7f8c8d;
            margin: 20px 0;
            border: 2px dashed #ecf0f1;
        }

        .no-records i {
            font-size: 3rem;
            color: #bdc3c7;
            margin-bottom: 20px;
            display: block;
        }

        .download-info {
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid rgba(52, 152, 219, 0.2);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
            color: #2c3e50;
            font-size: 0.95rem;
        }

        .download-info i {
            color: #3498db;
            margin-right: 8px;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        /* Enhance datetime-local input */
        input[type="datetime-local"] {
            cursor: pointer;
            position: relative;
        }

        /* Fix for browser-specific picker icons */
        input[type="datetime-local"]::-webkit-calendar-picker-indicator {
            filter: invert(0.5);
            cursor: pointer;
            padding-right: 10px;
        }

        /* Ensure placeholder text visibility */
        input[type="datetime-local"]::placeholder {
            color: #bdc3c7;
            opacity: 1;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.5rem;
            cursor: pointer;
            color: #7f8c8d;
        }

        .modal-close:hover {
            color: #e74c3c;
        }

        @media (max-width: 768px) {
            .content {
                padding: 30px 20px;
            }

            .filter-row {
                grid-template-columns: 1fr;
            }

            .stats-container {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .filter-buttons, .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 300px;
            }

            table {
                font-size: 0.85rem;
            }

            th, td {
                padding: 12px 8px;
            }
        }

        @media (max-width: 480px) {
            .container {
                margin: 10px;
                max-width: none;
            }

            .header {
                padding: 30px 15px;
            }

            .title {
                font-size: 1.8rem;
            }

            .logo {
                font-size: 2.5rem;
            }

            .modal-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h1 class="title">Admin Portal</h1>
                <p class="subtitle">Attendance Management System</p>
            </div>
        </div>

        <div class="content">
            <!-- Filters Section -->
            <div class="filter-section">
                <div class="filter-title">
                    <i class="fas fa-filter"></i>
                    Advanced Filters
                </div>
                
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-building"></i>Department
                            </label>
                            <select name="department" class="form-select">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>" <?= ($filter_department === $dept) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-calendar-alt"></i>Date
                            </label>
                            <input type="date" name="date" id="datePicker" class="form-input" 
                                value="<?= htmlspecialchars($filter_date ? date('Y-m-d', strtotime($filter_date)) : '') ?>" 
                                min="<?= htmlspecialchars($date_range['min_date'] ? date('Y-m-d', strtotime($date_range['min_date'])) : '') ?>" 
                                max="<?= htmlspecialchars($date_range['max_date'] ? date('Y-m-d', strtotime($date_range['max_date'])) : '') ?>" 
                                required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-check-circle"></i>Status
                            </label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="present" <?= ($filter_status === 'present') ? 'selected' : '' ?>>Present</option>
                                <option value="absent" <?= ($filter_status === 'absent') ? 'selected' : '' ?>>Absent</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-id-card"></i>Register Number
                            </label>
                            <input type="text" name="register_no" class="form-input" 
                                   placeholder="e.g., IT001" value="<?= htmlspecialchars($filter_register) ?>">
                        </div>
                    </div>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>Apply Filters
                        </button>
                        <a href="?" class="btn btn-secondary">
                            <i class="fas fa-undo"></i>Clear All
                        </a>
                        <button type="button" class="btn btn-holiday" onclick="openHolidayModal()">
                            <i class="fas fa-calendar-times"></i>Common Holidays
                        </button>
                    </div>
                </form>
            </div>

            <!-- Holiday Modal -->
            <div id="holidayModal" class="modal">
                <div class="modal-content">
                    <span class="modal-close" onclick="closeHolidayModal()">&times;</span>
                    <h2 style="font-size: 1.5rem; color: #2c3e50; margin-bottom: 20px;">
                        <i class="fas fa-calendar-times"></i> Manage Common Holidays
                    </h2>
                    <iframe src="manage_holidays.php" style="width: 100%; height: 400px; border: none;"></iframe>
                </div>
            </div>

            <?php if ($result->num_rows > 0): ?>
                <?php
                // Calculate stats
                $total_records = $result->num_rows;
                $present_count = 0;
                $absent_count = 0;
                $unique_students = [];
                $unique_dates = [];
                
                // Store results for display and counting
                $records = [];
                while ($row = $result->fetch_assoc()) {
                    $records[] = $row;
                    if ($row['status'] === 'present') $present_count++;
                    if ($row['status'] === 'absent') $absent_count++;
                    $unique_students[$row['register_no']] = true;
                    $unique_dates[$row['attendance_date']] = true;
                }
                ?>
                
                <!-- Statistics Section -->
                <div class="stats-section">
                    <div class="stats-title">
                        <i class="fas fa-chart-bar"></i>
                        Statistics Overview
                    </div>
                    
                    <div class="stats-container">
                        <div class="stat-item">
                            <div class="stat-number" style="color: #3498db;"><?= $total_records ?></div>
                            <div class="stat-label">Total Records</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" style="color: #2ecc71;"><?= $present_count ?></div>
                            <div class="stat-label">Present Count</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" style="color: #e74c3c;"><?= $absent_count ?></div>
                            <div class="stat-label">Absent Count</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" style="color: #9b59b6;"><?= count($unique_students) ?></div>
                            <div class="stat-label">Unique Students</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" style="color: #f39c12;"><?= count($unique_dates) ?></div>
                            <div class="stat-label">Unique Dates</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" style="color: #2ecc71;"><?= count($completed_depts) ?></div>
                            <div class="stat-label">Completed Depts</div>
                            <div class="dept-list">
                                <?= implode(', ', $completed_depts) ?: 'None' ?>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" style="color: #e74c3c;"><?= count($pending_depts) ?></div>
                            <div class="stat-label">Pending Depts</div>
                            <div class="dept-list">
                                <?= implode(', ', $pending_depts) ?: 'None' ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="<?= buildDownloadUrl() ?>" class="btn btn-success">
                        <i class="fas fa-download"></i>Download Excel
                    </a>
                </div>
                
                <!-- Download Info -->
                <div class="download-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Download Information:</strong> The Excel file will contain all <?= $total_records ?> filtered records 
                    <?php if (!empty($filter_department) || !empty($filter_date) || !empty($filter_status) || !empty($filter_register)): ?>
                        based on your current active filters
                    <?php endif; ?>
                    in CSV format (compatible with Excel, Google Sheets, LibreOffice Calc, etc.)
                </div>

                <!-- Records Table Section -->
                <div class="table-section">
                    <div class="table-title">
                        <i class="fas fa-table"></i>
                        Attendance Records
                    </div>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag"></i> ID</th>
                                    <th><i class="fas fa-id-card"></i> Register Number</th>
                                    <th><i class="fas fa-user"></i> Student Name</th>
                                    <th><i class="fas fa-building"></i> Department</th>
                                    <th><i class="fas fa-calendar"></i> Date</th>
                                    <th><i class="fas fa-check-circle"></i> Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($records as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['id']); ?></td>
                                    <td>
                                        <span class="register-no"><?= htmlspecialchars($row['register_no']); ?></span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['name'] ?? 'N/A'); ?></strong>
                                    </td>
                                    <td>
                                        <span style="background: rgba(52, 152, 219, 0.1); padding: 4px 8px; border-radius: 6px; font-size: 0.9rem; font-weight: 600; color: #2c3e50;">
                                            <?= htmlspecialchars($row['department']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-family: 'Courier New', monospace; font-weight: 600; color: #7f8c8d;">
                                            <?= date('d-M-Y', strtotime($row['attendance_date'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="<?= $row['status'] === 'present' ? 'status-present' : 'status-absent' ?>">
                                            <?php if ($row['status'] === 'present'): ?>
                                                <i class="fas fa-check-circle"></i> Present
                                            <?php else: ?>
                                                <i class="fas fa-times-circle"></i> Absent
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Additional Action Buttons -->
                <div class="action-buttons">
                    <a href="<?= buildDownloadUrl() ?>" class="btn btn-success">
                        <i class="fas fa-file-excel"></i>Export to Excel
                    </a>
                    <button onclick="window.print()" class="btn btn-secondary">
                        <i class="fas fa-print"></i>Print Records
                    </button>
                    <a href="?" class="btn btn-primary">
                        <i class="fas fa-refresh"></i>Refresh Data
                    </a>
                </div>

            <?php else: ?>
                <!-- No Records Found Section -->
                <div class="no-records">
                    <i class="fas fa-search-minus"></i>
                    <h3 style="margin: 20px 0 15px 0; color: #7f8c8d;">No Attendance Records Found</h3>
                    <p style="margin-bottom: 25px; line-height: 1.6;">
                        <?php
                        // Check if the selected date is a holiday
                        $holiday_check_sql = "SELECT reason FROM holidays WHERE holiday_date = ?";
                        $stmt = $conn->prepare($holiday_check_sql);
                        $stmt->bind_param('s', $filter_date);
                        $stmt->execute();
                        $holiday_result = $stmt->get_result();
                        if ($holiday_result->num_rows > 0) {
                            $holiday = $holiday_result->fetch_assoc();
                            echo "This date (" . date('d-M-Y', strtotime($filter_date)) . ") is marked as a holiday: " . htmlspecialchars($holiday['reason']);
                        } else {
                            echo "There are currently no attendance records in the system for " . date('d-M-Y', strtotime($filter_date)) . ". Records will appear here once attendance data is added.";
                        }
                        ?>
                    </p>
                    
                    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                        <?php if (!empty($filter_department) || !empty($filter_date) || !empty($filter_status) || !empty($filter_register)): ?>
                            <a href="?" class="btn btn-primary">
                                <i class="fas fa-times"></i>Clear All Filters
                            </a>
                        <?php endif; ?>
                        <button onclick="window.location.reload()" class="btn btn-secondary">
                            <i class="fas fa-refresh"></i>Refresh Page
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Footer Information -->
            <div style="margin-top: 50px; padding: 30px; background: rgba(52, 152, 219, 0.05); border-radius: 12px; border: 1px solid rgba(52, 152, 219, 0.1); text-align: center;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 30px; margin-bottom: 25px;">
                    <div>
                        <div style="font-size: 1.1rem; font-weight: 600; color: #2c3e50; margin-bottom: 8px;">
                            <i class="fas fa-clock" style="color: #3498db; margin-right: 8px;"></i>
                            Last Updated
                        </div>
                        <div style="color: #7f8c8d; font-size: 0.95rem;">
                            <?= date('d M Y, h:i A') ?>
                        </div>
                    </div>
                    
                    <div>
                        <div style="font-size: 1.1rem; font-weight: 600; color: #2c3e50; margin-bottom: 8px;">
                            <i class="fas fa-database" style="color: #2ecc71; margin-right: 8px;"></i>
                            Database Status
                        </div>
                        <div style="color: #2ecc71; font-size: 0.95rem; font-weight: 600;">
                            <i class="fas fa-check-circle"></i> Connected
                        </div>
                    </div>
                    
                    <div>
                        <div style="font-size: 1.1rem; font-weight: 600; color: #2c3e50; margin-bottom: 8px;">
                            <i class="fas fa-calendar-check" style="color: #9b59b6; margin-right: 8px;"></i>
                            Date Range
                        </div>
                        <div style="color: #7f8c8d; font-size: 0.95rem;">
                            <?php if (!empty($date_range['min_date']) && !empty($date_range['max_date'])): ?>
                                <?= date('d-M-Y', strtotime($date_range['min_date'])) ?> to <?= date('d-M-Y', strtotime($date_range['max_date'])) ?>
                            <?php else: ?>
                                No data available
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div style="border-top: 1px solid rgba(52, 152, 219, 0.2); padding-top: 20px; color: #7f8c8d; font-size: 0.9rem;">
                    <i class="fas fa-shield-alt" style="color: #3498db; margin-right: 5px;"></i>
                    Admin Portal • Attendance Management System • Secure Access
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for Enhanced Functionality -->
    <script>
        // Date picker validation and auto-submit
        document.getElementById('datePicker').addEventListener('change', function() {
            const selectedDate = this.value ? new Date(this.value).toISOString().split('T')[0] : '';
            const validDates = <?= json_encode($valid_dates) ?>;
            
            if (selectedDate && !validDates.includes(selectedDate)) {
                showNotification('Selected date has no attendance records or holidays. Please choose a valid date.', 'error');
                this.value = ''; // Reset invalid selection
                return;
            }
            
            // Auto-submit form
            this.form.submit();
        });
        
        // Auto-submit form functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading states for buttons
            const downloadBtn = document.querySelector('a[href*="download=excel"]');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function() {
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>Preparing Download...';
                    this.style.pointerEvents = 'none';
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.style.pointerEvents = 'auto';
                    }, 3000);
                });
            }

            // Auto-submit on date change
            const dateInput = document.querySelector('select[name="date"]');
            if (dateInput) {
                dateInput.addEventListener('change', function() {
                    this.form.submit();
                });
            }

            // Add smooth scrolling for better UX
            const filterInputs = document.querySelectorAll('select[name], input[name]');
            filterInputs.forEach(input => {
                if (input.name !== 'date') {
                    input.addEventListener('change', function() {
                        this.form.submit();
                    });
                }
            });

            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + R for refresh
                if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                    e.preventDefault();
                    window.location.href = '?';
                }
                
                // Ctrl/Cmd + D for download (if records exist)
                if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                    e.preventDefault();
                    const downloadBtn = document.querySelector('a[href*="download=excel"]');
                    if (downloadBtn) {
                        downloadBtn.click();
                    }
                }
                
                // Ctrl/Cmd + H for holiday modal
                if ((e.ctrlKey || e.metaKey) && e.key === 'h') {
                    e.preventDefault();
                    openHolidayModal();
                }
            });

            // Add tooltips for better user experience
            const statusElements = document.querySelectorAll('.status-present, .status-absent');
            statusElements.forEach(element => {
                const isPresent = element.classList.contains('status-present');
                element.title = isPresent ? 'Student was present on this date' : 'Student was absent on this date';
            });

            // Enhanced table interactions
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('click', function() {
                    // Remove previous highlights
                    tableRows.forEach(r => r.style.backgroundColor = '');
                    // Highlight clicked row
                    this.style.backgroundColor = 'rgba(52, 152, 219, 0.1)';
                    
                    // Remove highlight after 2 seconds
                    setTimeout(() => {
                        this.style.backgroundColor = '';
                    }, 2000);
                });
            });

            // Add print styles when printing
            window.addEventListener('beforeprint', function() {
                document.body.style.background = 'white';
                const container = document.querySelector('.container');
                if (container) {
                    container.style.boxShadow = 'none';
                    container.style.border = '1px solid #ddd';
                }
            });

            // Show loading indicator for form submissions
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>Filtering...';
                        submitBtn.disabled = true;
                        
                        // Re-enable after a delay (in case of errors)
                        setTimeout(() => {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }, 5000);
                    }
                });
            }
        });

        // Function to show notification messages
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                background: ${type === 'success' ? '#2ecc71' : type === 'error' ? '#e74c3c' : '#3498db'};
                color: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                z-index: 1000;
                font-weight: 600;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease;
            `;
            notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle"></i> ${message}`;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '1';
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Add confirmation for actions
        document.querySelectorAll('.btn-success').forEach(btn => {
            if (btn.textContent.includes('Download') || btn.textContent.includes('Export')) {
                btn.addEventListener('click', function(e) {
                    const recordCount = this.textContent.match(/\((\d+) records\)/);
                    if (recordCount && parseInt(recordCount[1]) > 1000) {
                        if (!confirm(`You are about to download ${recordCount[1]} records. This may take a moment. Continue?`)) {
                            e.preventDefault();
                        }
                    }
                });
            }
        });

        // Holiday modal functions
        function openHolidayModal() {
            document.getElementById('holidayModal').style.display = 'flex';
        }

        function closeHolidayModal() {
            document.getElementById('holidayModal').style.display = 'none';
        }
    </script>

    <!-- Print Styles -->
    <style media="print">
        body::before { display: none !important; }
        .container { box-shadow: none !important; background: white !important; }
        .filter-section, .action-buttons { display: none !important; }
        .header { background: #2c3e50 !important; }
        table { break-inside: avoid; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        .btn { display: none !important; }
        .download-info { display: none !important; }
        .modal { display: none !important; }
    </style>
</body>
</html>

<?php
$conn->close();
?>