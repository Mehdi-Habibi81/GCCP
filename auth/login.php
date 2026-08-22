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

<form method="post">
    <input type="text" name="username" placeholder="Username" required>

    <input type="password" name="password" placeholder="Password" required>

    <button type="submit">Log in</button>
</form>
<a href="forgot.php">Forgot password?</a>
<br>
<a href="register.php">No account? Sign in</a>
    
