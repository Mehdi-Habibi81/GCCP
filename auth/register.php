<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->execute([$username, $email, $hash]);

    echo "User registered!";
}
?>

<!DOCTYPE html>
<html>

<head>

    <title>Create Account</title>

    <link rel="stylesheet" href="style.css">

</head>

<body>

<div class="auth-container">

    <div class="auth-card">

        <div class="logo">
            A
        </div>

        <h1>Create Account</h1>

        <p class="subtitle">
            Create your new account
        </p>

        <form method="post">

            <div class="form-group">

                <label>Username</label>

                <input
                    type="text"
                    name="username"
                    placeholder="Choose a username"
                    required
                >

            </div>

            <div class="form-group">

                <label>Email</label>

                <input
                    type="email"
                    name="email"
                    placeholder="Enter your email"
                    required
                >

            </div>

            <div class="form-group">

                <label>Password</label>

                <input
                    type="password"
                    name="password"
                    placeholder="Create a password"
                    required
                >

            </div>

            <button type="submit">
                Create account
            </button>

        </form>

        <div class="auth-links">

            <p>
                Already have an account?
                <a href="login.php">
                    Log in
                </a>
            </p>

        </div>

    </div>

</div>

</body>
</html>
    
