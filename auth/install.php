<?php

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $host = trim($_POST['host']);
    $dbname = trim($_POST['dbname']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    try {

        // Connect to MySQL
        $pdo = new PDO(
            "mysql:host=$host;charset=utf8mb4",
            $username,
            $password
        );
        
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->exec(
            "CREATE DATABASE IF NOT EXISTS `$dbname` 
             CHARACTER SET utf8mb4 
             COLLATE utf8mb4_unicode_ci"
        );
        
        $pdo->exec("USE `$dbname`");

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create users table
        $sql = "
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                reset_token VARCHAR(255) DEFAULT NULL,
                reset_expires DATETIME DEFAULT NULL
            )
        ";

        $pdo->exec($sql);

        // Create config.php
        $config = "<?php

\$host = " . var_export($host, true) . ";
\$dbname = " . var_export($dbname, true) . ";
\$user = " . var_export($username, true) . ";
\$pass = " . var_export($password, true) . ";

try {
    \$pdo = new PDO(
        \"mysql:host=\$host;dbname=\$dbname;charset=utf8mb4\",
        \$user,
        \$pass
    );

    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException \$e) {
    die(\"Connection failed: \" . \$e->getMessage());
}
?>";

        if (file_put_contents('config.php', $config) === false) {
            throw new Exception('Could not create config.php. Check folder permissions.');
        }

        $success = true;
        $message = 'Installation successful!';

    } catch (PDOException $e) {

        $message = 'Database connection failed: ' . $e->getMessage();

    } catch (Exception $e) {

        $message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="style.css">
    <title>Install Authentication System</title>
</head>

<body>

<div class="auth-container">

    <div class="auth-card">

        <div class="logo">
            A
        </div>

        <h1>Install Authentication System</h1>

        <p class="subtitle">
            Configure your database to get started.
        </p>

        <?php if ($message): ?>

            <div class="message <?= $success ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <?php if (!$success): ?>

            <form method="POST">

                <div class="form-group">
                    <label for="host">Database Host</label>

                    <input
                        id="host"
                        type="text"
                        name="host"
                        value="localhost"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="dbname">Database Name</label>

                    <input
                        id="dbname"
                        type="text"
                        name="dbname"
                        placeholder="auth_db"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="username">Database Username</label>

                    <input
                        id="username"
                        type="text"
                        name="username"
                        value="root"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Database Password</label>

                    <input
                        id="password"
                        type="password"
                        name="password"
                    >
                </div>

                <button type="submit">
                    Install
                </button>

            </form>

        <?php else: ?>

            <div class="auth-links">

                <p>
                    Your database and users table have been created.
                </p>

                <p>
                    <a href="register.php">
                        Go to registration
                    </a>
                </p>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>

</html>
