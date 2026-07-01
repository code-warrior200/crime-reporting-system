<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$case_id = isset($_GET['case_id']) ? (int) $_GET['case_id'] : 0;

$stmt = $pdo->prepare('SELECT c.*, r.reference_code, r.fullname AS reporter_name, r.email AS reporter_email, r.phone AS reporter_phone, r.category AS report_category, r.location AS report_location, r.incident_date AS report_incident_date, r.description AS report_description FROM cases c LEFT JOIN reports r ON c.report_id = r.id WHERE c.id = :id LIMIT 1');
$stmt->execute([':id' => $case_id]);
$case = $stmt->fetch();

if (!$case) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Crime Report Not Found</title></head><body><h1>Report not found</h1><p>The requested case report does not exist.</p></body></html>';
    exit;
}

$evidence = $pdo->prepare('SELECT * FROM case_evidence WHERE case_id = :case_id ORDER BY logged_at DESC');
$evidence->execute([':case_id' => $case_id]);
$evidenceEntries = $evidence->fetchAll();

$updates = $pdo->prepare('SELECT * FROM case_updates WHERE case_id = :case_id ORDER BY created_at DESC');
$updates->execute([':case_id' => $case_id]);
$updatesEntries = $updates->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crime Report - <?php echo htmlspecialchars($case['case_code']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 2rem;
            background: #f8fafc;
            color: #111827;
        }
        .report-shell {
            max-width: 960px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
        }
        .report-header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        .report-header h1 {
            margin: 0;
            font-size: 2rem;
        }
        .report-meta,
        .report-section {
            margin-bottom: 1.75rem;
        }
        .report-meta p,
        .report-section p,
        .report-section li {
            margin: 0.55rem 0;
            line-height: 1.65;
        }
        .report-list {
            list-style: none;
            padding-left: 0;
        }
        .report-list li {
            border-bottom: 1px solid #e2e8f0;
            padding: 0.9rem 0;
        }
        .report-list li:last-child {
            border-bottom: none;
        }
        .section-title {
            font-size: 1.1rem;
            margin-bottom: 0.9rem;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }
        .print-actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 1rem;
        }
        .print-button {
            background: #1d4ed8;
            color: white;
            border: none;
            border-radius: 1rem;
            padding: 0.9rem 1.3rem;
            cursor: pointer;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .report-shell {
                box-shadow: none;
                border-radius: 0;
                padding: 0;
            }
            .print-actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="report-shell">
        <div class="print-actions">
            <button class="print-button" onclick="window.print()">Print report</button>
        </div>
        <div class="report-header">
            <div>
                <p class="section-title">Crime report</p>
                <h1><?php echo htmlspecialchars($case['case_code']); ?></h1>
            </div>
            <div>
                <p><strong>Status:</strong> <?php echo htmlspecialchars($case['status']); ?></p>
                <p><strong>Assigned officer:</strong> <?php echo htmlspecialchars($case['assigned_officer'] ?: 'Unassigned'); ?></p>
                <p><strong>Created by:</strong> <?php echo htmlspecialchars($case['created_by']); ?></p>
            </div>
        </div>

        <section class="report-meta">
            <h2 class="section-title">Case summary</h2>
            <p><strong>Title:</strong> <?php echo htmlspecialchars($case['title']); ?></p>
            <p><strong>Description:</strong></p>
            <p><?php echo nl2br(htmlspecialchars($case['description'])); ?></p>
        </section>

        <?php if ($case['reference_code']): ?>
        <section class="report-meta">
            <h2 class="section-title">Linked incident</h2>
            <p><strong>Reference:</strong> <?php echo htmlspecialchars($case['reference_code']); ?></p>
            <p><strong>Reporter:</strong> <?php echo htmlspecialchars($case['reporter_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($case['reporter_email']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($case['reporter_phone']); ?></p>
            <p><strong>Category:</strong> <?php echo htmlspecialchars($case['report_category']); ?></p>
            <p><strong>Location:</strong> <?php echo htmlspecialchars($case['report_location']); ?></p>
            <p><strong>Incident date:</strong> <?php echo htmlspecialchars($case['report_incident_date']); ?></p>
        </section>
        <?php endif; ?>

        <section class="report-section">
            <h2 class="section-title">Evidence entries</h2>
            <?php if (count($evidenceEntries) === 0): ?>
                <p>No evidence entries have been logged for this case.</p>
            <?php else: ?>
                <ul class="report-list">
                    <?php foreach ($evidenceEntries as $entry): ?>
                        <li>
                            <p><strong>Type:</strong> <?php echo htmlspecialchars($entry['evidence_type']); ?></p>
                            <p><?php echo nl2br(htmlspecialchars($entry['details'])); ?></p>
                            <p><em>Logged by <?php echo htmlspecialchars($entry['logged_by']); ?> on <?php echo htmlspecialchars($entry['logged_at']); ?></em></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="report-section">
            <h2 class="section-title">Investigation updates</h2>
            <?php if (count($updatesEntries) === 0): ?>
                <p>No investigation updates have been recorded yet.</p>
            <?php else: ?>
                <ul class="report-list">
                    <?php foreach ($updatesEntries as $update): ?>
                        <li>
                            <p><?php echo nl2br(htmlspecialchars($update['update_text'])); ?></p>
                            <p><em>Updated by <?php echo htmlspecialchars($update['updated_by']); ?> on <?php echo htmlspecialchars($update['created_at']); ?></em></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
