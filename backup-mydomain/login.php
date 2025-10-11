<?php
session_start();

// Load config to get the password
$configFile = 'config.json';
$config = [];

if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
}

// If no admin password is set, create a default one
if (!isset($config['admin_password'])) {
    // Default password is "admin123" - user should change this immediately
    $config['admin_password'] = password_hash('admin123', PASSWORD_DEFAULT);
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $isDefaultPassword = true;
} else {
    $isDefaultPassword = false;
}

// Handle login attempt
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    
    if (password_verify($password, $config['admin_password'])) {
        $_SESSION['authenticated'] = true;
        $_SESSION['last_activity'] = time();
        header('Location: index.php');
        exit();
    } else {
        $error = 'Invalid password. Please try again.';
    }
}

// Check for timeout message
$timeoutMessage = isset($_GET['timeout']) ? 'Your session has expired. Please login again.' : '';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Server Backup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bs-body-bg: #fdfaf6;
            --bs-primary-rgb: 88, 129, 87;
            --bs-body-font-family: 'Nunito Sans', sans-serif;
        }
        
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        
        .login-container {
            max-width: 420px;
            width: 100%;
        }
        
        .card-header {
            background-color: rgb(var(--bs-primary-rgb));
            color: white;
        }
        
        .btn-primary {
            --bs-btn-bg: rgb(var(--bs-primary-rgb));
            --bs-btn-border-color: rgb(var(--bs-primary-rgb));
            --bs-btn-hover-bg: #4a6e49;
            --bs-btn-hover-border-color: #4a6e49;
            font-weight: 600;
        }
        
        .logo-img {
            max-height: 60px;
        }
    </style>
</head>
<body>
    <div class="login-container px-3">
        <div class="card shadow-sm border-0">
            <div class="card-header text-center py-4">
                <img src="logo.png" alt="Logo" class="logo-img mb-2">
                <h1 class="h3 mb-0">Server Backup Login</h1>
            </div>
            <div class="card-body p-4">
                <?php if ($timeoutMessage): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($timeoutMessage); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($isDefaultPassword): ?>
                    <div class="alert alert-warning" role="alert">
                        <strong>⚠️ Default Password Active!</strong><br>
                        Please login with password: <code>admin123</code><br>
                        <small>Change this immediately in the settings!</small>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">Password</label>
                        <input type="password" class="form-control form-control-lg" id="password" name="password" required autofocus>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Login</button>
                    </div>
                </form>
            </div>
        </div>
        <footer class="text-center text-muted mt-3">
            <small>Server Backup System - Secure Access</small>
        </footer>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
