<?php
require_once __DIR__ . '/../config/db.php';

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
            if ($user['account_status'] === 'Suspended') {
                $error = 'Your account has been suspended. Please contact your supervisor for assistance.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];
                // Store officer identifier for assignment checks
                $_SESSION['username'] = $user['username'];
                header('Location: dashboard.php');
                exit;
            }
        }
    }

    if ($error === '') {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zaria Area Command HQ | Officer Login</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <div class="login-shell">
        <div class="login-panel card">
            <div class="panel-header">
                <p class="eyebrow">Zaria Area Command HQ · Case study</p>
                <h1>Officer Portal Login</h1>
                <p>Access case records, update investigations, and review local incident statistics.</p>
            </div>
            <?php if ($error): ?>
                <div class="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form action="login.php" method="post" class="login-form">
                <label>
                    Officer ID
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
