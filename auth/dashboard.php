<?php
// Start session (required for all pages)
session_start();

// If user is not logged in, redirect to login
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

// Get flash message if it exists, then delete it
$flash_message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

$username = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="auth/style.css">
    <style>
        body { background: #f1f5f9; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: Arial, sans-serif; }
        .dashboard-card { background: white; padding: 40px; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); max-width: 500px; width: 100%; text-align: center; }
        .dashboard-card h1 { margin-top: 0; }
        .logout-btn { display: inline-block; margin-top: 20px; padding: 10px 30px; background: #dc2626; color: white; text-decoration: none; border-radius: 10px; }
        .logout-btn:hover { background: #b91c1c; }
    </style>
</head>
<body>
    <div class="dashboard-card">
        <?php if ($flash_message): ?>
            <div class="success-message" style="margin-bottom:20px;">
                <?= $flash_message ?>
            </div>
        <?php endif; ?>

        <h1>Dashboard</h1>
        <p>Hello, <strong><?= $username ?></strong>! You are now logged in.</p>
        <p>This is your secure dashboard area.</p>

        <a href="auth/logout.php" class="logout-btn">Logout</a>
    </div>
</body>
</html>