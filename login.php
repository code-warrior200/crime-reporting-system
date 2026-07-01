<?php
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];
            header('Location: dashboard.php');
            exit;
        }
    }

    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Login</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="login-shell">
        <div class="login-panel card">
            <div class="panel-header">
                <p class="eyebrow">Officer access</p>
                <h1>Secure Login</h1>
                <p>Access case records, update investigations, and review incident statistics.</p>
            </div>
            <?php if ($error): ?>
                <div class="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form action="login.php" method="post" class="login-form">
                <label>
                    Username
                    <input type="text" name="username" required>
                </label>
                <label>
                    Password
                    <input type="password" name="password" required>
                </label>
                <button type="submit" class="button full-width">Login</button>
            </form>
            <p class="login-help"><a href="index.php">Back to public reporting</a></p>
        </div>
    </div>
</body>
</html>
