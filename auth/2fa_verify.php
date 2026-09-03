<?php
require 'config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PragmaRX\Google2FA\Google2FA;

// User must have passed the password step first
if (!isset($_SESSION['2fa_user_id'])) {
    header('Location: login.php');
    exit();
}

$error = '';

$userId = $_SESSION['2fa_user_id'];

$stmt = $pdo->prepare(
    "SELECT id, username, two_factor_secret, two_factor_enabled
     FROM users
     WHERE id = ?
     LIMIT 1"
);

$stmt->execute([$userId]);

$user = $stmt->fetch();

if (!$user || (int)$user['two_factor_enabled'] !== 1) {

    unset(
        $_SESSION['2fa_user_id'],
        $_SESSION['2fa_username']
    );

    header('Location: login.php');
    exit();
}

// Create Google2FA instance and QR URL so the QR can be displayed on the verify page
$google2fa = new Google2FA();
$companyName = 'My Website';
$qrUrl = $google2fa->getQRCodeUrl(
    $companyName,
    $user['username'],
    $user['two_factor_secret']
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $code = trim($_POST['code'] ?? '');

    if (!preg_match('/^[0-9]{6}$/', $code)) {

        $error = "Please enter the 6-digit authentication code.";

    } else {

        $google2fa = new Google2FA();

        $valid = $google2fa->verifyKey(
            $user['two_factor_secret'],
            $code
        );

        if ($valid) {

            /*
             * Both password AND 2FA are correct.
             * Now create the real authenticated session.
             */
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            $_SESSION['flash_message'] =
                "Welcome back, " . $user['username'] . "!";

            // Remove temporary 2FA login information
            unset(
                $_SESSION['2fa_user_id'],
                $_SESSION['2fa_username']
            );

            header("Location: ../dashboard.php");
            exit();

        } else {

            $error = "Invalid authentication code. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>

    <title>Two-Factor Authentication</title>

    <link rel="stylesheet" href="style.css">

    <style>

        .twofa-icon {
            font-size: 42px;
            margin-bottom: 10px;
        }

        .code-input {
            text-align: center;
            font-size: 24px;
            letter-spacing: 8px;
            font-weight: bold;
        }

        .twofa-help {
            font-size: 14px;
            color: #777;
            margin-top: 15px;
        }

        .qr-code {
            width: 220px;
            height: 220px;
            margin: 10px auto;
            display: block;
        }

        .qr-note {
            font-size: 13px;
            color: #666;
            text-align: center;
            margin-top: 6px;
        }

    </style>

</head>

<body>

<div class="auth-container">

    <div class="auth-card">

        <div class="logo">
            🔐
        </div>

        <h1>Two-Factor Authentication</h1>

        <p class="subtitle">
            Enter the 6-digit code from Google Authenticator.
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

        <!-- Show QR code to allow users to re-add the account to their authenticator app -->
        <div class="twofa-box">
            <p style="text-align:center;">If you need to re-add this account to your authenticator app, scan this QR code:</p>
            <img class="qr-code" src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?php echo urlencode($qrUrl); ?>" alt="Google Authenticator QR Code">
            <div class="qr-note">Or open your authenticator and add account using the secret: <strong><?php echo htmlspecialchars($user['two_factor_secret'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
        </div>

        <form method="post">

            <div class="form-group">

                <label>Authentication Code</label>

                <input
                    class="code-input"
                    type="text"
                    name="code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    placeholder="000000"
                    required
                    autofocus
                >

            </div>

            <button type="submit">
                Verify and Log In
            </button>

        </form>

        <p class="twofa-help">
            Open Google Authenticator on your phone
            and enter the current 6-digit code.
        </p>

        <div class="auth-links">

            <p>
                <a href="login.php">
                    Cancel
                </a>
            </p>

        </div>

    </div>

</div>

</body>

</html>
