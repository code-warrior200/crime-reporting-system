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
            </div>
            <div class="hero-summary-grid">
                <div class="hero-stat-card">
                    <span>Total reports</span>
                    <strong><?php echo count($reports); ?></strong>
                </div>
                <div class="hero-stat-card">
                    <span>Awaiting review</span>
                    <strong><?php echo $statusCounts['New']; ?></strong>
                </div>
                <div class="hero-stat-card">
                    <span>Active investigations</span>
                    <strong><?php echo $statusCounts['Under Investigation']; ?></strong>
                </div>
                <div class="hero-stat-card">
                    <span>Closed cases</span>
                    <strong><?php echo $statusCounts['Closed']; ?></strong>
                </div>
            </div>
        </section>
        <section class="dashboard-metrics">
            <article class="metric-card">
                <p class="metric-label">Total Reports</p>
                <strong><?php echo count($reports); ?></strong>
            </article>
            <article class="metric-card">
                <p class="metric-label">New Reports</p>
                <strong><?php echo $statusCounts['New']; ?></strong>
            </article>
            <article class="metric-card">
                <p class="metric-label">Under Investigation</p>
                <strong><?php echo $statusCounts['Under Investigation']; ?></strong>
            </article>
            <article class="metric-card">
                <p class="metric-label">Closed Cases</p>
                <strong><?php echo $statusCounts['Closed']; ?></strong>
            </article>
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
                <p class="table-note">Click view to update status and officer notes.</p>
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
                                    <td><button class="button small" onclick="showCase(<?php echo $report['id']; ?>)">View</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <div id="caseModal" class="modal hidden">
        <div class="modal-content">
            <button class="close" onclick="hideModal()">×</button>
            <div id="modalBody"></div>
        </div>
    </div>

    <script>
        const reports = <?php echo json_encode($reports, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        function showCase(id) {
            const report = reports.find(r => r.id == id);
            if (!report) return;

            const body = document.getElementById('modalBody');
            body.innerHTML = `
                <div class="case-details">
                    <div class="case-meta">
                        <h2>${report.reference_code}</h2>
                        <p><strong>Reporter:</strong> ${report.fullname}</p>
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
            const modal = document.getElementById('caseModal');
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
        }

        function hideModal() {
            const modal = document.getElementById('caseModal');
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
            document.getElementById('modalBody').innerHTML = '';
        }

        const modalElement = document.getElementById('caseModal');
        modalElement?.addEventListener('click', (event) => {
            if (event.target === modalElement) {
                hideModal();
            }
        });

        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modalElement.classList.contains('hidden')) {
                hideModal();
            }
        });
    </script>
</body>
</html>
