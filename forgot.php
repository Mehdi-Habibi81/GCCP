<?php
require 'config.php';
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);

    $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires= ? WHERE email= ?");
    $stmt->execute([$token, $expires, $email]);

    echo "If that email is registered a reset link has been sent.";
    echo "Test link:<a href='http://localhost/auth/reset.php?token=$token'>reset here</a>";
}
?>

<form method="post">
    <input type="email" name="email" placeholder="Email" required>

    <button type="submit">Send reset link</button>
</form>