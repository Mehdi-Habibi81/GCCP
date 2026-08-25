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

<!DOCTYPE html>
<html>

<head>

    <title>Forgot Password</title>

    <link rel="stylesheet" href="style.css">

</head>

<body>

<div class="auth-container">

    <div class="auth-card">

        <div class="logo">
            ?
        </div>

        <h1>Forgot Password?</h1>

        <p class="subtitle">
            Enter your email and we'll help you reset your password.
        </p>

        <form method="post">

            <div class="form-group">

                <label>Email</label>

                <input
                    type="email"
                    name="email"
                    placeholder="Enter your email"
                    required
                >

            </div>

            <button type="submit">
                Send reset link
            </button>

        </form>

        <div class="auth-links">

            <p>
                Remember your password?
                <a href="login.php">
                    Log in
                </a>
            </p>

        </div>

    </div>

</div>

</body>
</html>
