<?php
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

// Handle holiday submission
$notification = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['holiday_date']) && isset($_POST['reason'])) {
    $holiday_date = $_POST['holiday_date'];
    $reason = trim($_POST['reason']);
    
    if (empty($holiday_date) || empty($reason)) {
        $notification = '<div class="notification error"><i class="fas fa-times-circle"></i> Please provide both date and reason.</div>';
    } else {
        // Check if date is already a holiday
        $check_sql = "SELECT id FROM holidays WHERE holiday_date = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param('s', $holiday_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing holiday
            $update_sql = "UPDATE holidays SET reason = ? WHERE holiday_date = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param('ss', $reason, $holiday_date);
            if ($stmt->execute()) {
                $notification = '<div class="notification success"><i class="fas fa-check-circle"></i> Holiday updated successfully.</div>';
            } else {
                $notification = '<div class="notification error"><i class="fas fa-times-circle"></i> Error updating holiday: ' . $conn->error . '</div>';
            }
        } else {
            // Insert new holiday
            $insert_sql = "INSERT INTO holidays (holiday_date, reason) VALUES (?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param('ss', $holiday_date, $reason);
            if ($stmt->execute()) {
                $notification = '<div class="notification success"><i class="fas fa-check-circle"></i> Holiday added successfully.</div>';
            } else {
                $notification = '<div class="notification error"><i class="fas fa-times-circle"></i> Error adding holiday: ' . $conn->error . '</div>';
            }
        }
    }
}

// Handle holiday deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $delete_date = $_GET['delete'];
    $delete_sql = "DELETE FROM holidays WHERE holiday_date = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param('s', $delete_date);
    if ($stmt->execute()) {
        $notification = '<div class="notification success"><i class="fas fa-check-circle"></i> Holiday removed successfully.</div>';
    } else {
        $notification = '<div class="notification error"><i class="fas fa-times-circle"></i> Error removing holiday: ' . $conn->error . '</div>';
    }
}

// Fetch existing holidays
$holidays_sql = "SELECT holiday_date, reason FROM holidays ORDER BY holiday_date DESC";
$holidays_result = $conn->query($holidays_sql);
$holidays = [];
if ($holidays_result && $holidays_result->num_rows > 0) {
    while ($row = $holidays_result->fetch_assoc()) {
        $holidays[$row['holiday_date']] = $row['reason'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Common Holidays</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            margin: 0;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            max-width: 100%;
        }

        .form-group {
            margin-bottom: 20px;
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
            color: #e67e22;
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230, 126, 34, 0.1);
        }

        .form-textarea {
            height: 100px;
            resize: vertical;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
            color: white;
        }

        .btn-primary:hover {
            box-shadow: 0 8px 20px rgba(230, 126, 34, 0.4);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .btn-danger:hover {
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.4);
            transform: translateY(-2px);
        }

        .notification {
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notification.success {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.3);
        }

        .notification.error {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        .holiday-list {
            margin-top: 20px;
            max-height: 200px;
            overflow-y: auto;
        }

        .holiday-item {
            padding: 10px;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .holiday-item:last-child {
            border-bottom: none;
        }

        .holiday-date {
            font-weight: 600;
            color: #2c3e50;
        }

        .holiday-reason {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="font-size: 1.5rem; color: #2c3e50; margin-bottom: 20px;">
            <i class="fas fa-calendar-times"></i> Add Common Holiday
        </h2>
        
        <?php if ($notification): ?>
            <?= $notification ?>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-calendar-alt"></i>Holiday Date
                </label>
                <input type="date" name="holiday_date" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-comment"></i>Reason
                </label>
                <textarea name="reason" class="form-textarea" placeholder="Enter reason for holiday (e.g., National Holiday, Festival)" required></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>Save Holiday
            </button>
        </form>

        <div class="holiday-list">
            <h3 style="font-size: 1.2rem; color: #2c3e50; margin: 20px 0;">
                <i class="fas fa-list"></i> Existing Holidays
            </h3>
            <?php if (empty($holidays)): ?>
                <p style="color: #7f8c8d;">No holidays defined.</p>
            <?php else: ?>
                <?php foreach ($holidays as $date => $reason): ?>
                    <div class="holiday-item">
                        <div>
                            <span class="holiday-date"><?= date('d-M-Y', strtotime($date)) ?></span>
                            <div class="holiday-reason"><?= htmlspecialchars($reason) ?></div>
                        </div>
                        <a href="?delete=<?= urlencode($date) ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to remove this holiday?')">
                            <i class="fas fa-trash"></i>Delete
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>