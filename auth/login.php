<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        echo "Welcome back, ". htmlspecialchars($user['username'])."!";
    } else {
        echo "Wrong username or password";
    }
}
?>

<!DOCTYPE html>
<html>

<head>

    <title>Login</title>

    <link rel="stylesheet" href="style.css">

</head>

<body>

<div class="auth-container">

    <div class="auth-card">

        <div class="logo">
            A
        </div>

        <h1>Welcome Back</h1>

        <p class="subtitle">
            Log in to your account
        </p>

        <form method="post">

            <div class="form-group">

                <label>Username</label>

                <input
                    type="text"
                    name="username"
                    placeholder="Enter your username"
                    required
                >

            </div>

            <div class="form-group">

                <label>Password</label>

                <input
                    type="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >

            </div>

            <button type="submit">
                Log in
            </button>

        </form>

        <div class="auth-links">

            <p>
                <a href="forgot.php">
                    Forgot password?
                </a>
            </p>

            <p>
                Don't have an account?
                <a href="register.php">
                    Create account
                </a>
            </p>

        </div>

    </div>

</div>

</body>
</html>
    
