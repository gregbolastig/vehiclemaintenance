<?php
session_start();

require_once ('dbconnection.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error_message = "Please fill in all fields.";
    } else {
        // First, check if user is a driver
        $stmt = $conn->prepare("SELECT DriverID, FirstName, LastName, Password FROM Driver WHERE EmailAddress = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $driver = $result->fetch_assoc();
        
        if ($driver && password_verify($password, $driver['Password'])) {
            // Driver login successful
            $_SESSION['user_id'] = $driver['DriverID'];
            $_SESSION['user_type'] = 'driver';
            $_SESSION['user_name'] = $driver['FirstName'] . ' ' . $driver['LastName'];
            $_SESSION['user_email'] = $email;
            
            header("Location: driver");
            exit();
        } else {
            // Check if user is a supervisor
            $stmt = $conn->prepare("SELECT SupervisorID, FirstName, LastName, Password FROM Supervisor WHERE Email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $supervisor = $result->fetch_assoc();
            
            if ($supervisor && password_verify($password, $supervisor['Password'])) {
                // Supervisor login successful
                $_SESSION['user_id'] = $supervisor['SupervisorID'];
                $_SESSION['user_type'] = 'supervisor';
                $_SESSION['user_name'] = $supervisor['FirstName'] . ' ' . $supervisor['LastName'];
                $_SESSION['user_email'] = $email;
                
                header("Location: supervisor-dashboard");
                exit();
            } else {
                // Invalid credentials
                $error_message = "Invalid email or password.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IslandStar Express - Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #E5E5E5;
            min-height: 90vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 390px;
            padding: 40px 30px;
            border: 1px solid #E0E0E0;
        }
        
        .logo-container {
            text-align: center;
        }

        .logo img{
            width: 120px;
            max-width: 80%;
            height: auto;
            border-radius: 8px;
        }
        
        .portal-title {
            font-size: 16px;
            font-weight: 600;
            color: #2E7D32;
            text-align: center;
            letter-spacing: 0.5px;
            margin-bottom: 30px;
        }
        
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #ffcdd2;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #424242;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            height: 48px;
            border: 2px solid #BDBDBD;
            border-radius: 6px;
            padding: 0 16px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            background: white;
            transition: border-color 0.2s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #2E7D32;
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.1);
        }
        
        .form-input::placeholder {
            color: #9E9E9E;
        }
        
        .login-button {
            width: 100%;
            height: 48px;
            background: #2E7D32;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: background-color 0.2s ease;
            margin-top: 30px;
            letter-spacing: 0.5px;
        }
        
        .login-button:hover {
            background: #1B5E20;
        }
        
        .login-button:active {
            transform: translateY(1px);
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }
        
        .forgot-password a {
            color: #2E7D32;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon::before {
            content: '';
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            background-size: contain;
            background-repeat: no-repeat;
            z-index: 1;
        }
        
        .user-icon::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23757575' viewBox='0 0 24 24'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3E");
        }
        
        .lock-icon::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23757575' viewBox='0 0 24 24'%3E%3Cpath d='M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm9 14H6V10h12v10zm-6-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z'/%3E%3C/svg%3E");
        }
        
        .input-icon .form-input {
            padding-left: 45px;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            cursor: pointer;
            z-index: 2;
            background-size: contain;
            background-repeat: no-repeat;
            opacity: 0.6;
            transition: opacity 0.2s ease;
        }
        
        .password-toggle:hover {
            opacity: 1;
        }
        
        .password-toggle.show {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23757575' viewBox='0 0 24 24'%3E%3Cpath d='M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z'/%3E%3C/svg%3E");
        }
        
        .password-toggle.hide {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23757575' viewBox='0 0 24 24'%3E%3Cpath d='M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z'/%3E%3C/svg%3E");
        }
        
        .password-input-container {
            position: relative;
        }
        
        .password-input-container .form-input {
            padding-right: 45px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <div class="logo">
                <img src="img/logo1.png" alt="IslandStar Express Logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="logo-fallback" style="display: none;">
                    IslandStar<br>Express
                </div>
            </div>
        </div>
        
        <h1 class="portal-title">ISE-VMS PORTAL</h1>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <div class="input-icon user-icon">
                    <input type="email" id="email" name="email" class="form-input" placeholder="" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-icon lock-icon password-input-container">
                    <input type="password" id="password" name="password" class="form-input" placeholder="" required>
                    <div class="password-toggle hide" onclick="togglePassword()"></div>
                </div>
                <button type="submit" class="login-button">LOGIN</button>
            </div>
            
            <div class="forgot-password">
                <a href="#">forgot password</a>
            </div>
        </form>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('hide');
                toggleIcon.classList.add('show');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('show');
                toggleIcon.classList.add('hide');
            }
        }
    </script>
</body>
</html>