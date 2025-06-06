<?php
session_start();
require 'db.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['department'])) {
    header("Location: login.php");
    exit();
}

$department = $_SESSION['department'];
$error_message = '';
$success_message = '';
$total_students = 0;
$total_present = 0;
$total_absent = 0;
$attendance_exists = false;
$is_holiday = false;
$holiday_reason = '';

// Get today's date for max date restriction
$today = date('Y-m-d');

// Handle date - check both POST and GET for date changes
$date = isset($_POST['attendance_date']) ? $_POST['attendance_date'] : (isset($_GET['date']) ? $_GET['date'] : $today);

// Validate date is not in future
if ($date > $today) {
    $date = $today;
    $error_message = "Future dates are not allowed. Date reset to today.";
}

// Function to check if attendance exists for a date
function checkAttendanceExists($conn, $date, $department) {
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM attendance_records WHERE attendance_date = ? AND department = ?");
    if ($stmt_check === false) {
        error_log("MySQL prepare error: " . $conn->error);
        return false;
    }
    $stmt_check->bind_param("ss", $date, $department);
    $stmt_check->execute();
    $stmt_check->bind_result($existing_count);
    $stmt_check->fetch();
    $stmt_check->close();
    return $existing_count > 0;
}

// Function to check if date is a holiday
function checkHoliday($conn, $date) {
    $stmt_holiday = $conn->prepare("SELECT reason FROM holidays WHERE holiday_date = ?");
    if ($stmt_holiday === false) {
        error_log("MySQL prepare error: " . $conn->error);
        return false;
    }
    $stmt_holiday->bind_param("s", $date);
    $stmt_holiday->execute();
    $stmt_holiday->bind_result($reason);
    $result = $stmt_holiday->fetch();
    $stmt_holiday->close();
    return $result ? ['reason' => $reason] : false;
}

// Check if selected date is a holiday
$holiday_info = checkHoliday($conn, $date);
if ($holiday_info) {
    $is_holiday = true;
    $holiday_reason = $holiday_info['reason'];
}

// Check if attendance exists for the selected date
$attendance_exists = checkAttendanceExists($conn, $date, $department);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save']) || isset($_POST['update']))) {
    if ($is_holiday) {
        $error_message = "Cannot mark attendance on a holiday: " . htmlspecialchars($holiday_reason);
    } elseif (!empty($_POST['attendance']) && !empty($_POST['attendance_date'])) {
        $attendanceData = $_POST['attendance'];
        $date = $_POST['attendance_date'];

        if ($date > $today) {
            $error_message = "Cannot save attendance for future dates.";
        } else {
            $current_attendance_exists = checkAttendanceExists($conn, $date, $department);
            
            if (isset($_POST['update']) && $current_attendance_exists) {
                $stmt_update = $conn->prepare("UPDATE attendance_records SET status = ? WHERE register_no = ? AND attendance_date = ? AND department = ?");
                if ($stmt_update === false) {
                    $error_message = "Database error: " . $conn->error;
                } else {
                    foreach ($attendanceData as $register_no => $status) {
                        $stmt_update->bind_param("ssss", $status, $register_no, $date, $department);
                        $stmt_update->execute();
                        $total_students++;
                        if ($status === 'present') $total_present++;
                        elseif ($status === 'absent') $total_absent++;
                    }
                    $stmt_update->close();
                    $success_message = "Attendance updated successfully for $date.";
                }
            } elseif (isset($_POST['update']) && !$current_attendance_exists) {
                $stmt = $conn->prepare("INSERT INTO attendance_records (register_no, attendance_date, status, department) VALUES (?, ?, ?, ?)");
                if ($stmt === false) {
                    $error_message = "Database error: " . $conn->error;
                } else {
                    foreach ($attendanceData as $register_no => $status) {
                        $stmt->bind_param("ssss", $register_no, $date, $status, $department);
                        $stmt->execute();
                        $total_students++;
                        if ($status === 'present') $total_present++;
                        elseif ($status === 'absent') $total_absent++;
                    }
                    $stmt->close();
                    $success_message = "Attendance saved successfully for $date.";
                }
            } else {
                if ($current_attendance_exists) {
                    $error_message = "Attendance already exists for $date. Please use update.";
                } else {
                    $stmt = $conn->prepare("INSERT INTO attendance_records (register_no, attendance_date, status, department) VALUES (?, ?, ?, ?)");
                    if ($stmt === false) {
                        $error_message = "Database error: " . $conn->error;
                    } else {
                        foreach ($attendanceData as $register_no => $status) {
                            $stmt->bind_param("ssss", $register_no, $date, $status, $department);
                            $stmt->execute();
                            $total_students++;
                            if ($status === 'present') $total_present++;
                            elseif ($status === 'absent') $total_absent++;
                        }
                        $stmt->close();
                        $success_message = "Attendance saved successfully for $date.";
                    }
                }
            }
            
            $attendance_exists = checkAttendanceExists($conn, $date, $department);
        }
    } else {
        $error_message = "Please select a date and mark attendance.";
    }
}

// Handle Excel Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    if (!checkAttendanceExists($conn, $date, $department)) {
        header("Location: attendance.php?date=$date&error=no_data");
        exit();
    }
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="attendance_' . $date . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<table border="1">';
    echo '<tr><th colspan="3">Attendance Report</th></tr>';
    echo '<tr><td><strong>Department:</strong></td><td colspan="2">' . htmlspecialchars($department) . '</td></tr>';
    echo '<tr><td><strong>Date:</strong></td><td colspan="2">' . htmlspecialchars($date) . '</td></tr>';
    echo '<tr><td></td><td></td><td></td></tr>';
    echo '<tr><th>Register No</th><th>Name</th><th>Status</th></tr>';
    
    $stmt_excel = $conn->prepare("
        SELECT s.register_no, s.name, ar.status 
        FROM students s 
        LEFT JOIN attendance_records ar ON s.register_no = ar.register_no 
        AND ar.attendance_date = ? AND ar.department = ? 
        WHERE s.department = ? 
        ORDER BY s.register_no
    ");
    
    if ($stmt_excel !== false) {
        $stmt_excel->bind_param("sss", $date, $department, $department);
        $stmt_excel->execute();
        $result_excel = $stmt_excel->get_result();
        
        while ($row = $result_excel->fetch_assoc()) {
            $status = $row['status'] ? ucfirst($row['status']) : 'Not Marked';
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['register_no']) . '</td>';
            echo '<td>' . htmlspecialchars($row['name']) . '</td>';
            echo '<td>' . $status . '</td>';
            echo '</tr>';
        }
        $stmt_excel->close();
    }
    
    echo '</table>';
    exit();
}

// Handle export error
if (isset($_GET['error']) && $_GET['error'] === 'no_data') {
    $error_message = "No attendance data found for export. Please save attendance first.";
}

// Get total students count
$sql_count = "SELECT COUNT(*) FROM students WHERE department = ?";
$stmt_count = $conn->prepare($sql_count);

if ($stmt_count === false) {
    $error_message = "Database error: " . $conn->error;
    $total_students_db = 0;
} else {
    $stmt_count->bind_param("s", $department);
    $stmt_count->execute();
    $stmt_count->bind_result($total_students_db);
    $stmt_count->fetch();
    $stmt_count->close();
}

// Get attendance summary for the selected date if exists
$present_count = 0;
$absent_count = 0;
if ($attendance_exists) {
    $stmt_summary = $conn->prepare("SELECT status, COUNT(*) as count FROM attendance_records WHERE attendance_date = ? AND department = ? GROUP BY status");
    if ($stmt_summary !== false) {
        $stmt_summary->bind_param("ss", $date, $department);
        $stmt_summary->execute();
        $result_summary = $stmt_summary->get_result();
        
        while ($row = $result_summary->fetch_assoc()) {
            if ($row['status'] === 'present') {
                $present_count = $row['count'];
            } elseif ($row['status'] === 'absent') {
                $absent_count = $row['count'];
            }
        }
        $stmt_summary->close();
    }
}

// Fetch students with their details
$sql = "SELECT register_no, name FROM students WHERE department = ? ORDER BY register_no";
$stmt = $conn->prepare($sql);

$students = null;
if ($stmt === false) {
    $error_message = "Database error: " . $conn->error;
} else {
    $stmt->bind_param("s", $department);
    $stmt->execute();
    $students = $stmt->get_result();
}

// Fetch existing attendance data
$existing_attendance = [];
if ($attendance_exists) {
    $stmt_existing = $conn->prepare("SELECT register_no, status FROM attendance_records WHERE attendance_date = ? AND department = ?");
    if ($stmt_existing !== false) {
        $stmt_existing->bind_param("ss", $date, $department);
        $stmt_existing->execute();
        $result_existing = $stmt_existing->get_result();
        
        while ($row = $result_existing->fetch_assoc()) {
            $existing_attendance[$row['register_no']] = $row['status'];
        }
        $stmt_existing->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Portal - Attendance Management</title>
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
            padding: 20px;
            position: relative;
            overflow-x: auto;
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
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 0;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .header-content {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 30px;
            position: relative;
        }

        .header-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
        }

        .header-info {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            font-size: 2.5rem;
            color: #3498db;
        }

        .title-section h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .title-section p {
            font-size: 0.9rem;
            opacity: 0.8;
            font-weight: 300;
        }

        .user-info {
            text-align: right;
            background: rgba(255, 255, 255, 0.1);
            padding: 15px 20px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .user-info .department {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .user-info .username {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .stats-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            border: 2px solid #ecf0f1;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--card-color);
            transition: height 0.3s ease;
        }

        .stat-card:hover::before {
            height: 100%;
            opacity: 0.05;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-color: var(--card-color);
        }

        .stat-card.total { --card-color: #3498db; }
        .stat-card.present { --card-color: #27ae60; }
        .stat-card.absent { --card-color: #e74c3c; }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--card-color);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #7f8c8d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .main-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }

        .date-selector {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            border: 2px solid #dee2e6;
        }

        .date-form {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .date-form label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1rem;
        }

        .date-input {
            padding: 12px 15px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
            transition: all 0.3s ease;
            min-width: 150px;
        }

        .date-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: linear-gradient(135deg, #f8d7da 0%, #f1aeb5 100%);
            color: #721c24;
            border: 1px solid #f1aeb5;
        }

        .message.holiday {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
            color: #856404;
            border: 1px solid #ffeeba;
            font-weight: 600;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }

        .btn:disabled {
            background: #bdc3c7 !important;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .attendance-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #dee2e6;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        .table th {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.85rem;
        }

        .table th:last-child,
        .table th:nth-last-child(2) {
            text-align: center;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            transition: background-color 0.2s ease;
        }

        .table tr:hover td {
            background-color: #f8f9fa;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .radio-cell {
            text-align: center;
            padding: 15px;
        }

        .radio-input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            transform: scale(1.2);
        }

        .register-no {
            font-weight: 600;
            color: #2c3e50;
        }

        .student-name {
            color: #34495e;
        }

        .bottom-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .export-section {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .logout-section {
            margin-left: auto;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .header-info {
                flex-direction: 10px;
            }
            .header-content {
                flex-direction: column;
                text-align: center;
            }

            .user-info {
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
            }

            .attendance-table {
                overflow-x: auto;
            }

            .table {
                min-width: 600px;
            }

            .bottom-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .logout-section {
                margin-left: 0;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 20px 15px;
            }

            .date-form {
                flex-direction: column;
                align-items: stretch;
            }

            .date-input {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="header-info">
                    <div class="logo-section">
                        <div class="logo">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="title-section">
                            <h1>Attendance Management</h1>
                            <p>Faculty Portal - Academic System</p>
                        </div>
                    </div>
                    <div class="user-info">
                        <div class="department">
                            <i class="fas fa-building"></i>
                            <?php echo htmlspecialchars($department); ?> Department
                        </div>
                        <div class="username">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-container">
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo isset($total_students_db) ? $total_students_db : 0; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                
                <div class="stat-card present">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-number">
                        <?php echo $attendance_exists && !$success_message ? $present_count : ($success_message ? $total_present : 0); ?>
                    </div>
                    <div class="stat-label">Present</div>
                </div>
                
                <div class="stat-card absent">
                    <div class="stat-icon">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div class="stat-number">
                        <?php echo $attendance_exists && !$success_message ? $absent_count : ($success_message ? $total_absent : 0); ?>
                    </div>
                    <div class="stat-label">Absent</div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Date Selection -->
            <div class="date-selector">
                <form method="GET" action="attendance.php" id="dateForm" class="date-form">
                    <label for="attendance_date">
                        <i class="fas fa-calendar-alt"></i>
                        Select Date:
                    </label>
                    <input type="date" 
                           id="attendance_date" 
                           name="date" 
                           class="date-input"
                           required 
                           value="<?php echo htmlspecialchars($date); ?>" 
                           max="<?php echo $today; ?>">
                </form>
            </div>

            <!-- Messages -->
            <?php if ($is_holiday): ?>
                <div class="message holiday">
                    <i class="fas fa-calendar-times"></i>
                    This date (<?php echo date('d-M-Y', strtotime($date)); ?>) is a holiday: <?php echo htmlspecialchars($holiday_reason); ?>. Attendance marking is disabled.
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($students !== null): ?>
                <!-- Attendance Form -->
                <form method="POST" action="attendance.php" id="attendanceForm">
                    <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($date); ?>">
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="button" class="btn btn-success" onclick="selectAll('present')" <?php echo $is_holiday ? 'disabled' : ''; ?>>
                            <i class="fas fa-check-double"></i>
                            Mark All Present
                        </button>
                        <button type="button" class="btn btn-danger" onclick="selectAll('absent')" <?php echo $is_holiday ? 'disabled' : ''; ?>>
                            <i class="fas fa-times-circle"></i>
                            Mark All Absent
                        </button>
                        <button type="button" class="btn btn-warning" onclick="clearAll()" <?php echo $is_holiday ? 'disabled' : ''; ?>>
                            <i class="fas fa-eraser"></i>
                            Clear All
                        </button>
                        <?php if ($attendance_exists): ?>
                            <button type="submit" name="update" class="btn btn-primary" <?php echo $is_holiday ? 'disabled' : ''; ?>>
                                <i class="fas fa-sync-alt"></i>
                                Update Attendance
                            </button>
                        <?php else: ?>
                            <button type="submit" name="save" class="btn btn-primary" <?php echo $is_holiday ? 'disabled' : ''; ?>>
                                <i class="fas fa-save"></i>
                                Save Attendance
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Attendance Table -->
                    <div class="attendance-table">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-id-card"></i> Register No</th>
                                    <th><i class="fas fa-user"></i> Student Name</th>
                                    <th><i class="fas fa-check-circle"></i> Present</th>
                                    <th><i class="fas fa-times-circle"></i> Absent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($student = $students->fetch_assoc()) : ?>
                                    <tr>
                                        <td class="register-no"><?php echo htmlspecialchars($student['register_no']); ?></td>
                                        <td class="student-name"><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td class="radio-cell">
                                            <input type="radio" 
                                                   name="attendance[<?php echo htmlspecialchars($student['register_no']); ?>]" 
                                                   value="present" 
                                                   class="radio-input present-radio"
                                                   <?php 
                                                   if ($attendance_exists && isset($existing_attendance[$student['register_no']]) && $existing_attendance[$student['register_no']] === 'present') {
                                                       echo 'checked';
                                                   }
                                                   ?>
                                                   <?php echo $is_holiday ? 'disabled' : ''; ?>>
                                        </td>
                                        <td class="radio-cell">
                                            <input type="radio" 
                                                   name="attendance[<?php echo htmlspecialchars($student['register_no']); ?>]" 
                                                   value="absent" 
                                                   class="radio-input absent-radio"
                                                   <?php 
                                                   if ($attendance_exists && isset($existing_attendance[$student['register_no']]) && $existing_attendance[$student['register_no']] === 'absent') {
                                                       echo 'checked';
                                                   }
                                                   ?>
                                                   <?php echo $is_holiday ? 'disabled' : ''; ?>>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php else: ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    Unable to load student data. Please try again later.
                </div>
            <?php endif; ?>

            <!-- Bottom Actions -->
            <div class="bottom-actions">
                <div class="export-section">
                    <?php if ($attendance_exists): ?>
                        <a href="attendance.php?export=excel&date=<?php echo $date; ?>" class="btn btn-success">
                            <i class="fas fa-file-excel"></i>
                            Download Excel
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary" disabled title="Save attendance first to enable downloads">
                            <i class="fas fa-file-excel"></i>
                            Download Excel
                        </button>
                    <?php endif; ?>
                </div>
                
                <div class="logout-section">
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fetch holiday dates
        let holidayDates = [];
        fetch('check_holiday.php')
            .then(response => response.json())
            .then(data => {
                holidayDates = data;
            })
            .catch(error => console.error('Error fetching holiday dates:', error));

        function selectAll(status) {
            if (status === 'present') {
                document.querySelectorAll('.present-radio').forEach(el => el.checked = true);
            } else if (status === 'absent') {
                document.querySelectorAll('.absent-radio').forEach(el => el.checked = true);
            }
            updateStats();
        }
        
        function clearAll() {
            document.querySelectorAll('input[type="radio"]').forEach(el => el.checked = false);
            updateStats();
        }

        // Prevent future date and holiday selection
        document.getElementById('attendance_date').addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate > today) {
                alert('Future dates are not allowed!');
                this.value = '<?php echo $today; ?>';
                document.getElementById('dateForm').submit();
                return;
            }

            if (holidayDates.includes(this.value)) {
                alert('This date is a holiday. Please select a non-holiday date.');
                this.value = '<?php echo $today; ?>';
                document.getElementById('dateForm').submit();
                return;
            }

            document.getElementById('dateForm').submit();
        });

        // Add confirmation for bulk actions
        document.querySelectorAll('.btn-success, .btn-danger').forEach(button => {
            if (button.textContent.includes('Mark All')) {
                button.addEventListener('click', function(e) {
                    const action = this.textContent.includes('Present') ? 'present' : 'absent';
                    const confirmMessage = `Are you sure you want to mark all students as ${action}?`;
                    if (confirm(confirmMessage)) {
                        selectAll(action);
                    }
                });
            }
        });

        // Add form validation
        document.getElementById('attendanceForm').addEventListener('submit', function(e) {
            const checkedRadios = document.querySelectorAll('input[type="radio"]:checked');
            if (checkedRadios.length === 0) {
                e.preventDefault();
                alert('Please mark attendance for at least one student before saving.');
                return false;
            }
            
            // Show loading state
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
            
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });

        // Add hover effects for table rows
        document.querySelectorAll('.table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8f9fa';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });

        // Add click-to-select
        document.querySelectorAll('.table tbody tr').forEach(row => {
            row.addEventListener('click', function(e) {
                if (e.target.type !== 'radio' && !<?php echo json_encode($is_holiday); ?>) {
                    const presentRadio = this.querySelector('.present-radio');
                    const absentRadio = this.querySelector('.absent-radio');
                    
                    if (presentRadio.checked) {
                        absentRadio.checked = true;
                        presentRadio.checked = false;
                    } else if (absentRadio.checked) {
                        absentRadio.checked = false;
                    } else {
                        presentRadio.checked = true;
                    }
                    updateStats();
                }
            });
        });

        // Update statistics
        function updateStats() {
            const presentCount = document.querySelectorAll('.present-radio:checked').length;
            const absentCount = document.querySelectorAll('.absent-radio:checked').length;
            
            const presentStatElement = document.querySelector('.stat-card.present .stat-number');
            const absentStatElement = document.querySelector('.stat-card.absent .stat-number');
            
            if (presentStatElement && absentStatElement) {
                presentStatElement.textContent = presentCount;
                absentStatElement.textContent = absentCount;
            }
        }

        // Add event listeners for real-time stats
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', updateStats);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (<?php echo json_encode($is_holiday); ?>) return;
            
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                const submitBtn = document.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.click();
                }
            }
            
            if (e.ctrlKey && e.key === 'a' && !e.shiftKey) {
                e.preventDefault();
                selectAll('present');
                updateStats();
            }
            
            if (e.ctrlKey && e.shiftKey && e.key === 'A') {
                e.preventDefault();
                selectAll('absent');
                updateStats();
            }
            
            if (e.key === 'Escape') {
                clearAll();
                updateStats();
            }
        });

        console.log('Keyboard Shortcuts:');
        console.log('Ctrl+S: Save/Update attendance');
        console.log('Ctrl+A: Mark all present');
        console.log('Ctrl+Shift+A: Mark all absent');
        console.log('Escape: Clear all selections');
        console.log('Click on row: Toggle attendance status');
    </script>
</body>
</html>