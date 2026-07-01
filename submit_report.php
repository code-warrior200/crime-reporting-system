<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
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
        <p>Officers will review your report shortly. Keep this reference for follow-up.</p>
        <a class="button" href="index.php">Submit Another Report</a>
    </main>
</body>
</html>
