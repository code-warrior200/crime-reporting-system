<?php
require_once 'db.php';
require_once 'smtp_mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

function sendReferenceEmail(string $to, string $fullname, string $reference, ?string &$error = null): bool
{
    $error = null;
    $subject = 'Your crime report tracking reference';
    $trackUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF']) . '/track_status.php';
    $trackUrl = str_replace('\\', '/', $trackUrl);
    $trackUrl = preg_replace('#/+#', '/', $trackUrl);
    $trackUrl = preg_replace('#^http:/#', 'http://', $trackUrl);
    $trackUrl = preg_replace('#^https:/#', 'https://', $trackUrl);

    $message = "Dear {$fullname},\n\n";
    $message .= "Your report has been received by the Crime Reporting System.\n\n";
    $message .= "Tracking reference code: {$reference}\n\n";
    $message .= "Use this code to check your report status here:\n{$trackUrl}\n\n";
    $message .= "Please keep this reference safe for follow-up.\n\n";
    $message .= "Crime Reporting System";

    $mailer = new SmtpMailer(loadSmtpConfig());
    $sent = $mailer->send($to, $fullname, $subject, $message);

    if (!$sent) {
        $error = $mailer->getLastError();
        error_log('Tracking reference email failed: ' . $error);
    }

    return $sent;
}

function isLocalRequest(): bool
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return str_starts_with($host, 'localhost') || str_starts_with($host, '127.0.0.1');
}

$fullname = trim($_POST['fullname'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$category = trim($_POST['category'] ?? '');
$location = trim($_POST['location'] ?? '');
$incident_date = trim($_POST['incident_date'] ?? '');
$description = trim($_POST['description'] ?? '');

if (!$fullname || !$email || !$phone || !$category || !$location || !$incident_date || !$description) {
    die('Please complete all required fields.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('Please provide a valid email address.');
}

$incidentDateValue = DateTimeImmutable::createFromFormat('Y-m-d', $incident_date);
$dateErrors = DateTimeImmutable::getLastErrors();
if (!$incidentDateValue || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
    die('Please provide a valid incident date.');
}

$today = new DateTimeImmutable('today');
if ($incidentDateValue > $today) {
    die('Incident date cannot be in the future.');
}

$reference = 'CASE-' . strtoupper(uniqid());

$sql = 'INSERT INTO reports (reference_code, fullname, email, phone, category, location, incident_date, description) VALUES (:reference, :fullname, :email, :phone, :category, :location, :incident_date, :description)';
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':reference' => $reference,
    ':fullname' => $fullname,
    ':email' => $email,
    ':phone' => $phone,
    ':category' => $category,
    ':location' => $location,
    ':incident_date' => $incident_date,
    ':description' => $description,
]);

$emailError = null;
$emailSent = sendReferenceEmail($email, $fullname, $reference, $emailError);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Submitted</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <main class="container card success-card">
        <h1>Report Submitted Successfully</h1>
        <p>Thank you, <strong><?php echo htmlspecialchars($fullname); ?></strong>.</p>
        <p>Your incident has been logged with reference:</p>
        <div class="reference-code"><?php echo htmlspecialchars($reference); ?></div>
        <?php if ($emailSent): ?>
            <p>A copy of this tracking reference code has been sent to <?php echo htmlspecialchars($email); ?>.</p>
        <?php else: ?>
            <p class="alert">The report was saved, but the email could not be sent by the server. Please copy and keep your tracking reference code.</p>
            <?php if ($emailError && isLocalRequest()): ?>
                <p class="alert">SMTP setup issue: <?php echo htmlspecialchars($emailError); ?></p>
            <?php endif; ?>
        <?php endif; ?>
        <p>Officers will review your report shortly.</p>
        <a class="button" href="index.php">Submit Another Report</a>
    </main>
</body>
</html>
