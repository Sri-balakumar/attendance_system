<?php
session_start();
require 'db.php';

$error_message = '';

// Handle department reset
if (isset($_POST['reset_department'])) {
    unset($_SESSION['selected_department']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle login steps
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step'])) {
    if ($_POST['step'] == '1') {
        // Step 1: Save department
        $selected_department = $_POST['department'] ?? '';
        if ($selected_department) {
            $_SESSION['selected_department'] = $selected_department;
        } else {
            $error_message = "Please select a department.";
        }
    } elseif ($_POST['step'] == '2') {
        // Step 2: Validate login
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $department = $_SESSION['selected_department'] ?? '';

        if ($username && $password && $department) {
            $sql = "SELECT * FROM teachers WHERE username=? AND department=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $username, $department);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                // Check password
                if ($password === $row['password']) { // Use password_verify() if hashed
                    $_SESSION['username'] = $username;
                    $_SESSION['department'] = $department;
                    header("Location: attendance.php");
                    exit();
                } else {
                    $error_message = "Invalid password.";
                }
            } else {
                $error_message = "Teacher not found in this department.";
            }
        } else {
            $error_message = "Please fill in all fields.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Portal - Teacher Login</title>
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
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
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

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 0;
            width: 100%;
            max-width: 450px;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            text-align: center;
            padding: 30px 20px;
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
            font-size: 3rem;
            margin-bottom: 10px;
            color: #3498db;
        }

        .title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .subtitle {
            font-size: 0.9rem;
            opacity: 0.8;
            font-weight: 300;
        }

        .form-container {
            padding: 40px 30px;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .step.active {
            background: #3498db;
            color: white;
        }

        .step.inactive {
            background: #ecf0f1;
            color: #7f8c8d;
        }

        .step-line {
            width: 50px;
            height: 2px;
            background: #ecf0f1;
            margin: 14px 0;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95rem;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 15px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #3498db;
            background: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }

        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
            margin-top: 15px;
            padding: 12px;
            font-size: 0.9rem;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-1px);
        }

        .department-badge {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            text-align: center;
            margin-bottom: 25px;
            font-weight: 600;
            font-size: 1rem;
        }

        .error-message {
            background: #e74c3c;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        .input-icon .form-input {
            padding-left: 50px;
        }

        .footer-text {
            text-align: center;
            margin-top: 30px;
            font-size: 0.85rem;
            color: #7f8c8d;
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 10px;
                max-width: none;
            }
            
            .form-container {
                padding: 30px 20px;
            }
            
            .header {
                padding: 25px 15px;
            }
            
            .title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="header">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h1 class="title">Faculty Portal</h1>
                <p class="subtitle">Academic Management System</p>
            </div>
        </div>

        <div class="form-container">
            <div class="step-indicator">
                <div class="step <?php echo !isset($_SESSION['selected_department']) ? 'active' : 'inactive'; ?>">1</div>
                <div class="step-line"></div>
                <div class="step <?php echo isset($_SESSION['selected_department']) ? 'active' : 'inactive'; ?>">2</div>
            </div>

            <?php if ($error_message): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!isset($_SESSION['selected_department'])): ?>
                <!-- Step 1: Select Department -->
                <form method="POST" action="">
                    <input type="hidden" name="step" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-building"></i> Select Your Department
                        </label>
                        <select name="department" class="form-select" required>
                            <option value="">Choose Department</option>
                            <option value="CSE">Computer Science & Engineering</option>
                            <option value="IT">Information Technology</option>
                            <option value="ECE">Electronics & Communication</option>
                            <option value="MECH">Mechanical Engineering</option>
                            <option value="CIVIL">Civil Engineering</option>
                            <option value="EEE">Electrical & Electronics</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> Continue
                    </button>
                </form>

            <?php else: ?>
                <!-- Step 2: Enter Credentials -->
                <div class="department-badge">
                    <i class="fas fa-check-circle"></i>
                    Department: <?php echo htmlspecialchars($_SESSION['selected_department']); ?>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="step" value="2">

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i> Username
                        </label>
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" name="username" class="form-input" placeholder="Enter your username" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" class="form-input" placeholder="Enter your password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </form>

                <!-- Back Button -->
                <form method="POST" action="">
                    <button type="submit" name="reset_department" value="1" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Change Department
                    </button>
                </form>
            <?php endif; ?>

            <div class="footer-text">
                <i class="fas fa-shield-alt"></i>
                Secure Faculty Access Portal
            </div>
        </div>
    </div>
</body>
</html>