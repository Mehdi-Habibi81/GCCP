<?php
require 'config.php';

$error = '';
$success = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validate inputs
    if (empty($username)) {
        $error = "Username is required!";
    } else if (empty($email)) {
        $error = "Email is required!";
    } else if (empty($password)) {
        $error = "Password is required!";
    } else if (empty($password_confirm)) {
        $error = "Please confirm your password!";
    } else if (strlen($username) < 3) {
        $error = "Username must be at least 3 characters long!";
    } else if (strlen($username) > 50) {
        $error = "Username must not exceed 50 characters!";
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } else if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } else if ($password !== $password_confirm) {
        $error = "Passwords do not match!";
    } else {
        try {
            // Check if username already exists (using prepared statements)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $existing_user = $stmt->fetch();

            if ($existing_user) {
                $error = "Username already taken! Please choose another username.";
            } else {
                // Check if email already exists (using prepared statements)
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $existing_email = $stmt->fetch();

                if ($existing_email) {
                    $error = "Email already registered! Please use a different email.";
                } else {
                    // Hash password with PASSWORD_DEFAULT
                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    // Insert new user (using prepared statements)
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$username, $email, $hash]);

                    $success = "User registered successfully! Redirecting to login...";
                    $username = '';
                    $email = '';
                    
                    // Redirect after 2 seconds
                    header("refresh:2;url=login.php");
                }
            }
        } catch (PDOException $e) {
            $error = "An error occurred during registration. Please try again later.";
            error_log("Registration error: " . $e->getMessage());
        }
    }
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

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>

            <div class="form-group">

                <label>Username</label>

                <input
                    type="text"
                    name="username"
                    placeholder="Choose a username (3-50 characters)"
                    value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                    minlength="3"
                    maxlength="50"
                    required
                >

            </div>

            <div class="form-group">

                <label>Email</label>

                <input
                    type="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>Password</label>

                <input
                    type="password"
                    name="password"
                    placeholder="Create a strong password (min 6 characters)"
                    minlength="6"
                    required
                >

            </div>

            <div class="form-group">

                <label>Confirm Password</label>

                <input
                    type="password"
                    name="password_confirm"
                    placeholder="Confirm your password"
                    minlength="6"
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
