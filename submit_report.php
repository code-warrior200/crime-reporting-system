<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

function sendReferenceEmail(string $to, string $fullname, string $reference): bool
{
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

    $headers = [
        'From: Crime Reporting System <no-reply@crime-reporting-system.local>',
        'Reply-To: no-reply@crime-reporting-system.local',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    return mail($to, $subject, $message, implode("\r\n", $headers));
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

$emailSent = sendReferenceEmail($email, $fullname, $reference);
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
        <?php endif; ?>
        <p>Officers will review your report shortly. Use the reference above to check the latest status at <a href="track_status.php">track_status.php</a>.</p>
        <a class="button" href="index.php">Submit Another Report</a>
    </main>
</body>
</html>
