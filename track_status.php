<?php
require_once 'db.php';

$reference = trim($_GET['reference'] ?? '');
$statusMessage = '';
$report = null;

if ($reference) {
    $stmt = $pdo->prepare('SELECT * FROM reports WHERE reference_code = :reference LIMIT 1');
    $stmt->execute([':reference' => $reference]);
    $report = $stmt->fetch();

    if (!$report) {
        $statusMessage = 'No report was found for that reference. Please check your code and try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Report Status</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="site-header">
        <div class="header-inner container">
            <div class="brand">
                <img src="logo.svg" alt="Crime Reporting System" class="brand-logo">
                <div>
                    <h1>Crime Reporting System</h1>
                    <p>Status tracking and report lookup</p>
                </div>
            </div>
            <nav>
                <div class="nav-actions">
                    <a class="button secondary" href="index.php">Back Home</a>
                    <a class="button" href="login.php">Officer Login</a>
                </div>
            </nav>
        </div>
    </header>
    <main class="container card lookup-card">
        <div class="section-intro">
            <p class="eyebrow">Report tracker</p>
            <h2>Check the status of your incident</h2>
            <p>Enter the reference code provided at submission to view the current report status and linked case details.</p>
        </div>

        <form class="form-grid" method="get" action="track_status.php">
            <label class="full-width">
                Tracking reference
                <input type="text" name="reference" value="<?php echo htmlspecialchars($reference); ?>" required placeholder="CASE-xxxxxxxxxxxx">
            </label>
            <div class="form-footer full-width">
                <button type="submit" class="button">Check Status</button>
                <a class="button secondary" href="index.php">Back to Home</a>
            </div>
        </form>

        <?php if ($reference): ?>
            <?php if ($report): ?>
                <div class="lookup-result">
                    <h3>Report status</h3>
                    <p><strong>Reference:</strong> <span class="status-pill"><?php echo htmlspecialchars($report['reference_code']); ?></span></p>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($report['status']); ?></p>
                    <p><strong>Category:</strong> <?php echo htmlspecialchars($report['category']); ?></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($report['location']); ?></p>
                    <p><strong>Incident date:</strong> <?php echo htmlspecialchars($report['incident_date']); ?></p>
                    <p><strong>Submitted by:</strong> <?php echo htmlspecialchars($report['fullname']); ?></p>
                    <p><strong>Description:</strong></p>
                    <p><?php echo nl2br(htmlspecialchars($report['description'])); ?></p>
                    <?php if ($report['officer_notes']): ?>
                        <p><strong>Officer notes:</strong></p>
                        <p><?php echo nl2br(htmlspecialchars($report['officer_notes'])); ?></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="lookup-result">
                    <p><?php echo htmlspecialchars($statusMessage); ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <footer class="footer footer-wide">
        <div class="container footer-inner">
            <div class="footer-logo-section">
                <img src="logo.svg" alt="Crime Reporting System" class="footer-logo">
                <div>
                    <p>Built for secure, accountable case handling that meets international public safety standards.</p>
                    <small>&copy; <?php echo date('Y'); ?> Crime Reporting System</small>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
