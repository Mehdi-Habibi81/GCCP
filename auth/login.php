<?php
require 'config.php';

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate inputs
    if (empty($username)) {
        $error = "Username is required!";
    } else if (empty($password)) {
        $error = "Password is required!";
    } else {
        try {
            // Check user with prepared statement (prevent SQL injection)
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    
    // Set a welcome message for the next page
    $_SESSION['flash_message'] = "Welcome back, " . htmlspecialchars($user['username']) . "!";
    
    header("Location: ../dashboard.php");
    exit();
}
                
                header("Location: ../index.php");
                exit();
            } else {
                $error = "Invalid username or password!";
            }
        } catch (PDOException $e) {
            $error = "An error occurred. Please try again later.";
            error_log("Login error: " . $e->getMessage());
        }
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

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>

            <div class="form-group">

                <label>Username</label>

                <input
                    type="text"
                    name="username"
                    placeholder="Enter your username"
                    value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
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
