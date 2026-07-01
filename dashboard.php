<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_id'])) {
    $report_id = (int) $_POST['report_id'];
    $status = $_POST['status'] ?? 'New';
    $officer_notes = trim($_POST['officer_notes'] ?? '');

    $stmt = $pdo->prepare('UPDATE reports SET status = :status, officer_notes = :notes WHERE id = :id');
    $stmt->execute([
        ':status' => $status,
        ':notes' => $officer_notes,
        ':id' => $report_id,
    ]);
}

$reports = $pdo->query('SELECT * FROM reports ORDER BY created_at DESC')->fetchAll();

$statsByCategory = $pdo->query('SELECT category, COUNT(*) AS total FROM reports GROUP BY category')->fetchAll();
$statsByLocation = $pdo->query('SELECT location, COUNT(*) AS total FROM reports GROUP BY location')->fetchAll();
$statusCounts = [
    'New' => 0,
    'Under Investigation' => 0,
    'Resolved' => 0,
    'Closed' => 0,
];
foreach ($reports as $report) {
    if (isset($statusCounts[$report['status']])) {
        $statusCounts[$report['status']]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="site-header dashboard-header">
        <div class="header-inner container dashboard-topbar">
            <div>
                <p class="eyebrow">Officer portal</p>
                <h1>Case Management Dashboard</h1>
                <p>Manage investigations, review public reports, and monitor performance metrics.</p>
            </div>
            <div class="header-actions">
                <div class="user-pill">
                    <span>Officer</span>
                    <strong><?php echo htmlspecialchars($_SESSION['fullname']); ?></strong>
                </div>
                <a class="button secondary" href="logout.php">Logout</a>
            </div>
        </div>
    </header>

    <main class="container dashboard-layout">
        <section class="dashboard-hero card">
            <div class="hero-copy">
                <p class="eyebrow">Overview</p>
                <h2>Operational readiness at a glance</h2>
                <p>Track non-emergency reports, case status, and performance metrics from a single secure portal.</p>
                <div class="hero-stats-list">
                    <div class="hero-stat-card hero-stat-card--badge">
                        <span>Total reports</span>
                        <strong><?php echo count($reports); ?></strong>
                    </div>
                    <div class="hero-stat-card hero-stat-card--badge">
                        <span>Awaiting review</span>
                        <strong><?php echo $statusCounts['New']; ?></strong>
                    </div>
                    <div class="hero-stat-card hero-stat-card--badge">
                        <span>Active investigations</span>
                        <strong><?php echo $statusCounts['Under Investigation']; ?></strong>
                    </div>
                    <div class="hero-stat-card hero-stat-card--badge">
                        <span>Closed cases</span>
                        <strong><?php echo $statusCounts['Closed']; ?></strong>
                    </div>
                </div>
            </div>
        </section>

        <section class="stats-panel">
            <div class="stats-card">
                <h2>By Category</h2>
                <ul>
                    <?php foreach ($statsByCategory as $item): ?>
                        <li><?php echo htmlspecialchars($item['category']); ?> <span><?php echo $item['total']; ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="stats-card">
                <h2>By Location</h2>
                <ul>
                    <?php foreach ($statsByLocation as $item): ?>
                        <li><?php echo htmlspecialchars($item['location']); ?> <span><?php echo $item['total']; ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <section class="card table-card">
            <div class="section-header">
                <div>
                    <p class="eyebrow">Case Records</p>
                    <h2>Recent reports</h2>
                </div>
                <p class="table-note">Click details to update status and officer notes.</p>
            </div>
            <?php if (count($reports) === 0): ?>
                <div class="empty-state">
                    <p class="empty-title">No reports available yet.</p>
                    <p>Public reports will appear here once submitted. The dashboard is ready to manage cases in real time.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Category</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($report['reference_code']); ?></td>
                                    <td><?php echo htmlspecialchars($report['category']); ?></td>
                                    <td><?php echo htmlspecialchars($report['location']); ?></td>
                                    <td><?php echo htmlspecialchars($report['status']); ?></td>
                                    <td><?php echo htmlspecialchars($report['created_at']); ?></td>
                                    <td><button class="button small" onclick="showCase(<?php echo $report['id']; ?>)">Details</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section id="caseDetailsPanel" class="card details-panel hidden" aria-live="polite">
            <div class="details-header">
                <div>
                    <p class="eyebrow">Case details</p>
                    <h2 id="detailsReference">Select a report to review</h2>
                </div>
                <button class="button secondary small" onclick="hideDetails()">Close</button>
            </div>
            <div id="detailsBody"></div>
        </section>
    </main>

    <script>
        const reports = <?php echo json_encode($reports, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        function showCase(id) {
            const report = reports.find(r => r.id == id);
            if (!report) return;

            document.getElementById('detailsReference').textContent = report.reference_code;
            const body = document.getElementById('detailsBody');
            body.innerHTML = `
                <div class="case-details">
                    <div class="case-meta">
                        <p><strong>Reporter:</strong> ${report.fullname}</p>
                        <p><strong>Email:</strong> ${report.email}</p>
                        <p><strong>Phone:</strong> ${report.phone}</p>
                        <p><strong>Category:</strong> ${report.category}</p>
                        <p><strong>Location:</strong> ${report.location}</p>
                        <p><strong>Incident Date:</strong> ${report.incident_date}</p>
                    </div>
                    <div class="case-text">
                        <h3>Description</h3>
                        <p>${report.description.replace(/\n/g, '<br>')}</p>
                        <h3>Officer Notes</h3>
                        <p>${(report.officer_notes || 'None').replace(/\n/g, '<br>')}</p>
                    </div>
                </div>
                <form method="post" action="dashboard.php" class="case-update-form">
                    <input type="hidden" name="report_id" value="${report.id}">
                    <label>
                        Status
                        <select name="status">
                            <option value="New" ${report.status === 'New' ? 'selected' : ''}>New</option>
                            <option value="Under Investigation" ${report.status === 'Under Investigation' ? 'selected' : ''}>Under Investigation</option>
                            <option value="Resolved" ${report.status === 'Resolved' ? 'selected' : ''}>Resolved</option>
                            <option value="Closed" ${report.status === 'Closed' ? 'selected' : ''}>Closed</option>
                        </select>
                    </label>
                    <label>
                        Officer Notes
                        <textarea name="officer_notes" rows="4">${report.officer_notes || ''}</textarea>
                    </label>
                    <button type="submit" class="button">Save Updates</button>
                </form>
            `;
            document.getElementById('caseDetailsPanel').classList.remove('hidden');
        }

        function hideDetails() {
            const panel = document.getElementById('caseDetailsPanel');
            panel.classList.add('hidden');
            document.getElementById('detailsReference').textContent = 'Select a report to review';
            document.getElementById('detailsBody').innerHTML = '';
        }
    </script>
</body>
</html>
