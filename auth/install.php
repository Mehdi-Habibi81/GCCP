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
    <title>Install Authentication System</title>
</head>

<body>

<h1>Install Authentication System</h1>

<?php if ($message): ?>

    <p>
        <?php echo htmlspecialchars($message); ?>
    </p>

<?php endif; ?>

<?php if (!$success): ?>

<form method="POST">

    <label>Database Host:</label>
    <br>
    <input
        type="text"
        name="host"
        value="localhost"
        required
    >

    <br><br>

    <label>Database Name:</label>
    <br>
    <input
        type="text"
        name="dbname"
        placeholder="auth_db"
        required
    >

    <br><br>

    <label>Database Username:</label>
    <br>
    <input
        type="text"
        name="username"
        value="root"
        required
    >

    <br><br>

    <label>Database Password:</label>
    <br>
    <input
        type="password"
        name="password"
    >

    <br><br>

    <button type="submit">Install</button>

</form>

<?php else: ?>

    <p>
        Your database and users table have been created.
    </p>

    <p>
        <a href="register.php">Go to registration</a>
    </p>

<?php endif; ?>

</body>
</html>
