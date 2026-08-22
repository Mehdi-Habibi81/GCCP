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

<form method="post">
    <input type="text" name="username" placeholder="Username" required>

    <input type="email" name="email" placeholder="Email" required>

    <input type="password" name="password" placeholder="password" required>

    <button type="submit">Register</button>
</form>
    