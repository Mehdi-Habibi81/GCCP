<?php
require 'config.php';

$error = '';
$username = '';

const MAX_FAILED_ATTEMPTS = 5;
const LOCKOUT_MINUTES = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username)) {

        $error = "Username is required!";

    } elseif (empty($password)) {

        $error = "Password is required!";

    } else {

        try {

            $stmt = $pdo->prepare(
                "SELECT id, username, password, two_factor_enabled,
                        failed_login_attempts, locked_until
                 FROM users
                 WHERE username = ?
                 LIMIT 1"
            );

            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // Is this account currently locked out?
            $isLocked = $user
                && $user['locked_until'] !== null
                && strtotime($user['locked_until']) > time();

            if ($isLocked) {

                $error = "Too many failed attempts. Please try again later.";

            } elseif ($user && password_verify($password, $user['password'])) {

                /*
                 * Password is correct. Reset the failed-attempt counter.
                 */
                $stmt = $pdo->prepare(
                    "UPDATE users
                     SET failed_login_attempts = 0, locked_until = NULL
                     WHERE id = ?"
                );
                $stmt->execute([$user['id']]);

                /*
                 * If 2FA is enabled, DON'T log the user in yet.
                 */
                if ((int)$user['two_factor_enabled'] === 1) {

                    // Store temporary authentication information
                    $_SESSION['2fa_user_id'] = $user['id'];
                    $_SESSION['2fa_username'] = $user['username'];

                    // Redirect to 2FA verification
                    header("Location: 2fa_verify.php");
                    exit();

                } else {

                    /*
                     * 2FA isn't enabled.
                     * Log the user in normally.
                     */
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];

                    $_SESSION['flash_message'] =
                        "Welcome back, " . $user['username'] . "!";

                    header("Location: ../dashboard.php");
                    exit();
                }

            } else {

                // Wrong password (or no such user) - only increment a
                // counter if the account actually exists, so guessing
                // usernames can't be used to lock out real accounts
                // (that itself would be a denial-of-service vector).
                if ($user) {

                    $attempts = (int)$user['failed_login_attempts'] + 1;

                    if ($attempts >= MAX_FAILED_ATTEMPTS) {

                        $stmt = $pdo->prepare(
                            "UPDATE users
                             SET failed_login_attempts = ?,
                                 locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE)
                             WHERE id = ?"
                        );
                        $stmt->execute([$attempts, LOCKOUT_MINUTES, $user['id']]);

                    } else {

                        $stmt = $pdo->prepare(
                            "UPDATE users
                             SET failed_login_attempts = ?
                             WHERE id = ?"
                        );
                        $stmt->execute([$attempts, $user['id']]);
                    }
                }

                $error = "Invalid username or password!";
            }

        } catch (PDOException $e) {

            $error = "An error occurred. Please try again later.";

            error_log(
                "Login error: " . $e->getMessage()
            );
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

        <h1>Login</h1>

        <p class="subtitle">
            Log in to your account
        </p>

        <?php if (!empty($error)): ?>

            <div class="error-message">
                <?php
                echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </div>

        <?php endif; ?>

        <form method="post" novalidate>

            <div class="form-group">

                <label>Username</label>

                <input
                    type="text"
                    name="username"
                    placeholder="Enter your username"
                    value="<?php
                        echo htmlspecialchars(
                            $username,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
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
