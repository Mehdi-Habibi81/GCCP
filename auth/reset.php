<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $password = $_POST['password'];
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $upd->execute([$hash, $user['id']]);
        echo "Password updated! <a href='login.php'>Log in</a>";
    } else {
        echo "Invalid or expired token";
    }
} elseif (isset($_GET['token'])) {
    $token = $_GET['token'];
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Reset Password</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <div class="auth-container">
            <div class="auth-card">
                <div class="logo">✓</div>
                <h1>Reset Password</h1>
                <p class="subtitle">Enter your new password below.</p>
                <form method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="password" placeholder="Enter your new password" required>
                    </div>
                    <button type="submit">Set new password</button>
                </form>
                <div class="auth-links">
                    <p><a href="login.php">Back to login</a></p>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
} else {
    // Handle case where no token is provided
    echo "No reset token provided. <a href='forgot_password.php'>Request a new one</a>";
}
?>
