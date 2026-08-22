<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token= $_POST['token'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $password = $_POST['password'];
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE users SET password = ?, reset_token= Null, reset_expires= NULL WHERE id = ?");
        $upd->execute([$hash, $user['id']]);
        echo "Password updated! <a href='login.php'>Log in</a>";
    } else {
        echo "Invalid or expired token";
    }
} elseif (isset($_GET['token'])) {
    $token = $_GET['token'];
    ?>
    <form method="POST">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

        <input type="password" name="password" placeholder="New password" required>

        <button type="submit">Set new password</button>
</form>
<?php
} else {echo "No token provided.";} 
?>
