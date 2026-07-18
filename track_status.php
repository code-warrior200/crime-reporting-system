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
<!D OCTYPE html>
<htm l lang="en">
<head >
    <m eta charset="UTF-8">
    <me ta name="viewport" content="width=device-width, initial-scale=1.0">
    <tit le>Track Report Status</title>
    <link  rel="stylesheet" href="styles.css">
</head> 
<body> 
    <ma in class="container card lookup-card">
         <div class="section-intro">
             <p class="eyebrow">Report tracker</p>
             <h2>Check the status of your incident</h2>
             <p>Enter the reference code provided at submission to view the current report status and linked case details.</p>
        </di v>
 
         <form class="form-grid" method="get" action="track_status.php">
             <label class="full-width">
                 Tracking reference
                 <input type="text" name="reference" value="<?php echo htmlspecialchars($reference); ?>" required placeholder="CASE-xxxxxxxxxxxx">
             </label>
             <div class="form-footer full-width">
                 <button type="submit" class="button">Check Status</button>
                 <a class="button secondary" href="index.php">Back to Home</a>
             </div>
        </ form>
  
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
                <  /div>
            <?php   else: ?>
                <di  v class="lookup-result">
                      <p><?php echo htmlspecialchars($statusMessage); ?></p>
                </div  >
            <?php endif; ?>
        <?php endif; ?>  
    </main>  
</body>  
</html>  
