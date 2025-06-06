<?php
session_start();

// Store user info for the goodbye message before destroying session
$username = $_SESSION['username'] ?? 'User';
$department = $_SESSION['department'] ?? '';

// Clear and destroy session
session_unset();
session_destroy();

// Auto-redirect after 10 seconds
$redirect_delay = 10;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Portal - Logout</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <meta http-equiv="refresh" content="<?php echo $redirect_delay; ?>;url=login.php">
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

        .logout-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 0;
            width: 100%;
            max-width: 450px;
            overflow: hidden;
            animation: slideIn 0.8s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
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
            color: #fff;
            animation: checkmark 1s ease-in-out;
        }

        @keyframes checkmark {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .content-container {
            padding: 40px 30px;
            text-align: center;
        }

        .success-message {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
            animation: fadeInUp 0.6s ease-out 0.3s both;
        }

        .success-message i {
            font-size: 1.5rem;
            margin-bottom: 8px;
            display: block;
        }

        .user-info {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            animation: fadeInUp 0.6s ease-out 0.5s both;
        }

        .user-info h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .user-details {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .redirect-info {
            background: #3498db;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: fadeInUp 0.6s ease-out 0.7s both;
        }

        .countdown {
            font-weight: 600;
            font-size: 1.1rem;
            margin-top: 8px;
        }

        .countdown-number {
            display: inline-block;
            min-width: 20px;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
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
            text-decoration: none;
            display: inline-block;
            margin-bottom: 15px;
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
            padding: 12px;
            font-size: 0.9rem;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-1px);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading-bar {
            width: 100%;
            height: 4px;
            background: #ecf0f1;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 15px;
        }

        @keyframes loading {
            from { width: 100%; }
            to { width: 0%; }
        }

        .loading-progress {
            height: 100%;
            background: linear-gradient(90deg, #3498db, #2ecc71);
            border-radius: 2px;
            animation: loading 10s linear;
        }

        .footer-text {
            text-align: center;
            margin-top: 20px;
            font-size: 0.85rem;
            color: #7f8c8d;
            animation: fadeInUp 0.6s ease-out 0.9s both;
        }

        @media (max-width: 480px) {
            .logout-container {
                margin: 10px;
                max-width: none;
            }
            
            .content-container {
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
    <div class="logout-container">
        <div class="header">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1 class="title">Logout Successful</h1>
                <p class="subtitle">Session Terminated Securely</p>
            </div>
        </div>

        <div class="content-container">
            <div class="success-message">
                <i class="fas fa-sign-out-alt"></i>
                <strong>You have been successfully logged out!</strong>
                <br>Your session has been securely terminated.
            </div>

            <?php if ($username !== 'User'): ?>
            <div class="user-info">
                <h3><i class="fas fa-user"></i> Goodbye, <?php echo htmlspecialchars($username); ?>!</h3>
                <div class="user-details">
                    <?php if ($department): ?>
                        Department: <?php echo htmlspecialchars($department); ?><br>
                    <?php endif; ?>
                    Thank you for using the Faculty Portal
                </div>
            </div>
            <?php endif; ?>

            <div class="redirect-info">
                <i class="fas fa-info-circle"></i>
                <strong>Redirecting to Login Page</strong>
                <div class="countdown">
                    You will be redirected in <span class="countdown-number" id="countdown"><?php echo $redirect_delay; ?></span> seconds
                </div>
                <div class="loading-bar">
                    <div class="loading-progress"></div>
                </div>
            </div>

            <a href="login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Login Again
            </a>

            <div class="footer-text">
                <i class="fas fa-shield-alt"></i>
                Your data is safe and secure
            </div>
        </div>
    </div>

    <script>
        // Countdown timer
        let timeLeft = <?php echo $redirect_delay; ?>;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            timeLeft--;
            countdownElement.textContent = timeLeft;
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                window.location.href = 'login.php';
            }
        }, 1000);

        // Add click handler to stop auto-redirect if user interacts
        document.addEventListener('click', (e) => {
            if (e.target.tagName === 'A') {
                clearInterval(timer);
            }
        });
    </script>
</body>
</html>