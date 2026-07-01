<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$officers = $pdo->query("SELECT username, fullname, role FROM users WHERE role IN ('supervisor','detective','officer') ORDER BY fullname")->fetchAll();
$reports = $pdo->query('SELECT * FROM reports ORDER BY created_at DESC')->fetchAll();
$cases = $pdo->query('SELECT c.*, r.reference_code, r.fullname AS reporter_name FROM cases c LEFT JOIN reports r ON c.report_id = r.id ORDER BY c.created_at DESC')->fetchAll();
$caseEvidence = $pdo->query('SELECT * FROM case_evidence ORDER BY logged_at DESC')->fetchAll();
$caseUpdates = $pdo->query('SELECT * FROM case_updates ORDER BY created_at DESC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_case') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $assigned_officer = trim($_POST['assigned_officer'] ?? '') ?: null;
        $status = $_POST['status'] ?? 'New';
        $report_id = !empty($_POST['report_id']) ? (int) $_POST['report_id'] : null;

        if ($title && $description) {
            $case_code = 'CASE-' . strtoupper(uniqid());
            $stmt = $pdo->prepare('INSERT INTO cases (case_code, report_id, title, description, assigned_officer, status, created_by) VALUES (:case_code, :report_id, :title, :description, :assigned_officer, :status, :created_by)');
            $stmt->execute([
                ':case_code' => $case_code,
                ':report_id' => $report_id,
                ':title' => $title,
                ':description' => $description,
                ':assigned_officer' => $assigned_officer,
                ':status' => $status,
                ':created_by' => $_SESSION['fullname'],
            ]);
        }
    } elseif ($action === 'update_case') {
        $case_id = (int) ($_POST['case_id'] ?? 0);
        $assigned_officer = trim($_POST['assigned_officer'] ?? '') ?: null;
        $status = $_POST['status'] ?? 'New';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($case_id && $title && $description) {
            $stmt = $pdo->prepare('UPDATE cases SET title = :title, description = :description, assigned_officer = :assigned_officer, status = :status WHERE id = :id');
            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':assigned_officer' => $assigned_officer,
                ':status' => $status,
                ':id' => $case_id,
            ]);
        }
    } elseif ($action === 'log_evidence') {
        $case_id = (int) ($_POST['case_id'] ?? 0);
        $evidence_type = trim($_POST['evidence_type'] ?? '');
        $details = trim($_POST['details'] ?? '');

        if ($case_id && $evidence_type && $details) {
            $stmt = $pdo->prepare('INSERT INTO case_evidence (case_id, evidence_type, details, logged_by) VALUES (:case_id, :evidence_type, :details, :logged_by)');
            $stmt->execute([
                ':case_id' => $case_id,
                ':evidence_type' => $evidence_type,
                ':details' => $details,
                ':logged_by' => $_SESSION['fullname'],
            ]);
        }
    } elseif ($action === 'log_update') {
        $case_id = (int) ($_POST['case_id'] ?? 0);
        $update_text = trim($_POST['update_text'] ?? '');

        if ($case_id && $update_text) {
            $stmt = $pdo->prepare('INSERT INTO case_updates (case_id, update_text, updated_by) VALUES (:case_id, :update_text, :updated_by)');
            $stmt->execute([
                ':case_id' => $case_id,
                ':update_text' => $update_text,
                ':updated_by' => $_SESSION['fullname'],
            ]);
        }
    }

    header('Location: dashboard.php');
    exit;
}

$statsByCategory = $pdo->query('SELECT category, COUNT(*) AS total FROM reports GROUP BY category')->fetchAll();
$statsByLocation = $pdo->query('SELECT location, COUNT(*) AS total FROM reports GROUP BY location')->fetchAll();
$reportStatusCounts = [
    'New' => 0,
    'Under Investigation' => 0,
    'Resolved' => 0,
    'Closed' => 0,
];

foreach ($reports as $report) {
    if (isset($reportStatusCounts[$report['status']])) {
        $reportStatusCounts[$report['status']]++;
    }
}

$caseStatusCounts = [
    'New' => 0,
    'Under Investigation' => 0,
    'Resolved' => 0,
    'Closed' => 0,
];

foreach ($cases as $case) {
    if (isset($caseStatusCounts[$case['status']])) {
        $caseStatusCounts[$case['status']]++;
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
                <p>Manage investigations, assign evidence, and generate crime reports from a secure officer console.</p>
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
                <p>Track reports, manage case records, and maintain a clear evidence trail for every investigation.</p>
                <div class="hero-stats-list">
                    <div class="hero-stat-card hero-stat-card--badge">
                        <span>Total reports</span>
                        <strong><?php echo count($reports); ?></strong>
                    </div>
                    <div class="hero-stat-card hero-stat-card--badge">
                        <span>Open investigations</span>
                        <strong><?php echo $caseStatusCounts['Under Investigation']; ?></strong>
                    </div>
                    <div class="hero-stat-card hero-stat-card--badge">
                        <span>Closed cases</span>
                        <strong><?php echo $caseStatusCounts['Closed']; ?></strong>
                    </div>
                    <div class="hero-stat-card hero-stat-card--badge">
                        <span>Cases created</span>
                        <strong><?php echo count($cases); ?></strong>
                    </div>
                </div>
            </div>
        </section>

        <section class="stats-panel">
            <div class="stats-card">
                <h2>Reports by category</h2>
                <ul>
                    <?php foreach ($statsByCategory as $item): ?>
                        <li><?php echo htmlspecialchars($item['category']); ?> <span><?php echo $item['total']; ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="stats-card">
                <h2>Reports by location</h2>
                <ul>
                    <?php foreach ($statsByLocation as $item): ?>
                        <li><?php echo htmlspecialchars($item['location']); ?> <span><?php echo $item['total']; ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <section class="card form-card">
            <div class="section-header">
                <div>
                    <p class="eyebrow">Create case</p>
                    <h2>New case record</h2>
                </div>
                <p class="table-note">Start a new investigation, link it to a public report, and assign an officer.</p>
            </div>
            <form method="post" action="dashboard.php" class="form-grid case-form">
                <input type="hidden" name="action" value="create_case">
                <label>
                    Case title
                    <input type="text" name="title" required>
                </label>
                <label>
                    Related report
                    <select name="report_id">
                        <option value="">None</option>
                        <?php foreach ($reports as $report): ?>
                            <option value="<?php echo $report['id']; ?>"><?php echo htmlspecialchars($report['reference_code'] . ' — ' . $report['fullname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Assigned officer
                    <select name="assigned_officer">
                        <option value="">Unassigned</option>
                        <?php foreach ($officers as $officer): ?>
                            <option value="<?php echo htmlspecialchars($officer['username']); ?>"><?php echo htmlspecialchars($officer['fullname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Status
                    <select name="status">
                        <option value="New">New</option>
                        <option value="Under Investigation">Under Investigation</option>
                        <option value="Resolved">Resolved</option>
                        <option value="Closed">Closed</option>
                    </select>
                </label>
                <label class="full-width">
                    Case description
                    <textarea name="description" rows="5" required></textarea>
                </label>
                <div class="form-footer full-width">
                    <p>Create secure case records that include assignment and investigation tracking.</p>
                    <button type="submit" class="button">Create Case</button>
                </div>
            </form>
        </section>

        <section class="card table-card">
            <div class="section-header">
                <div>
                    <p class="eyebrow">Case records</p>
                    <h2>Active investigations</h2>
                </div>
                <p class="table-note">Open, assign, and track investigations from one place.</p>
            </div>
            <?php if (count($cases) === 0): ?>
                <div class="empty-state">
                    <p class="empty-title">No cases have been created yet.</p>
                    <p>Use the form above to convert public reports into case records and manage every investigation.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Case code</th>
                                <th>Title</th>
                                <th>Assigned officer</th>
                                <th>Status</th>
                                <th>Linked report</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cases as $case): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($case['case_code']); ?></td>
                                    <td><?php echo htmlspecialchars($case['title']); ?></td>
                                    <td><?php echo htmlspecialchars($case['assigned_officer'] ?: 'Unassigned'); ?></td>
                                    <td><?php echo htmlspecialchars($case['status']); ?></td>
                                    <td><?php echo htmlspecialchars($case['reference_code'] ?: 'None'); ?></td>
                                    <td><button class="button small" onclick="showCase(<?php echo $case['id']; ?>)">Details</button></td>
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
                    <h2 id="detailsReference">Select a case to review</h2>
                </div>
                <button class="button secondary small" onclick="hideDetails()">Close</button>
            </div>
            <div id="detailsBody"></div>
        </section>
    </main>

    <script>
        const reports = <?php echo json_encode($reports, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const cases = <?php echo json_encode($cases, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const evidenceEntries = <?php echo json_encode($caseEvidence, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const caseUpdates = <?php echo json_encode($caseUpdates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const officers = <?php echo json_encode($officers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        function getOfficerName(username) {
            const officer = officers.find(o => o.username === username);
            return officer ? officer.fullname : 'Unassigned';
        }

        function showCase(id) {
            const selectedCase = cases.find(c => c.id == id);
            if (!selectedCase) return;

            document.getElementById('detailsReference').textContent = selectedCase.case_code;
            const body = document.getElementById('detailsBody');

            const evidenceList = evidenceEntries.filter(item => item.case_id == selectedCase.id);
            const updatesList = caseUpdates.filter(item => item.case_id == selectedCase.id);

            body.innerHTML = `
                <div class="case-details">
                    <div class="case-meta">
                        <p><strong>Case code:</strong> ${selectedCase.case_code}</p>
                        <p><strong>Title:</strong> ${selectedCase.title}</p>
                        <p><strong>Assigned officer:</strong> ${getOfficerName(selectedCase.assigned_officer)}</p>
                        <p><strong>Status:</strong> ${selectedCase.status}</p>
                        <p><strong>Created by:</strong> ${selectedCase.created_by}</p>
                        <p><strong>Linked report:</strong> ${selectedCase.reference_code ? selectedCase.reference_code : 'None'}</p>
                        <p><strong>Reporter:</strong> ${selectedCase.reporter_name ? selectedCase.reporter_name : 'N/A'}</p>
                    </div>
                    <div class="case-text">
                        <h3>Case description</h3>
                        <p>${selectedCase.description.replace(/\n/g, '<br>')}</p>
                    </div>
                </div>
                <div class="case-history">
                    <div class="case-log">
                        <h3>Evidence log</h3>
                        ${evidenceList.length ? evidenceList.map(item => `<div class="case-log-item"><strong>${item.evidence_type}</strong><p>${item.details.replace(/\n/g, '<br>')}</p><span>Logged by ${item.logged_by} on ${item.logged_at}</span></div>`).join('') : '<p class="empty-note">No evidence logged yet.</p>'}
                    </div>
                    <div class="case-log">
                        <h3>Investigation updates</h3>
                        ${updatesList.length ? updatesList.map(item => `<div class="case-log-item"><p>${item.update_text.replace(/\n/g, '<br>')}</p><span>Updated by ${item.updated_by} on ${item.created_at}</span></div>`).join('') : '<p class="empty-note">No updates recorded yet.</p>'}
                    </div>
                </div>
                <form method="post" action="dashboard.php" class="case-update-form">
                    <input type="hidden" name="action" value="update_case">
                    <input type="hidden" name="case_id" value="${selectedCase.id}">
                    <label>
                        Case title
                        <input type="text" name="title" value="${selectedCase.title.replace(/"/g, '&quot;')}" required>
                    </label>
                    <label>
                        Assign investigator
                        <select name="assigned_officer">
                            <option value="">Unassigned</option>
                            ${officers.map(off => `<option value="${off.username}" ${off.username === selectedCase.assigned_officer ? 'selected' : ''}>${off.fullname}</option>`).join('')}
                        </select>
                    </label>
                    <label>
                        Status
                        <select name="status">
                            <option value="New" ${selectedCase.status === 'New' ? 'selected' : ''}>New</option>
                            <option value="Under Investigation" ${selectedCase.status === 'Under Investigation' ? 'selected' : ''}>Under Investigation</option>
                            <option value="Resolved" ${selectedCase.status === 'Resolved' ? 'selected' : ''}>Resolved</option>
                            <option value="Closed" ${selectedCase.status === 'Closed' ? 'selected' : ''}>Closed</option>
                        </select>
                    </label>
                    <label class="full-width">
                        Case narrative
                        <textarea name="description" rows="4" required>${selectedCase.description}</textarea>
                    </label>
                    <button type="submit" class="button">Save case updates</button>
                </form>
                <form method="post" action="dashboard.php" class="case-update-form">
                    <input type="hidden" name="action" value="log_evidence">
                    <input type="hidden" name="case_id" value="${selectedCase.id}">
                    <label>
                        Evidence type
                        <input type="text" name="evidence_type" required>
                    </label>
                    <label class="full-width">
                        Details
                        <textarea name="details" rows="3" required></textarea>
                    </label>
                    <button type="submit" class="button">Log evidence</button>
                </form>
                <form method="post" action="dashboard.php" class="case-update-form">
                    <input type="hidden" name="action" value="log_update">
                    <input type="hidden" name="case_id" value="${selectedCase.id}">
                    <label class="full-width">
                        Investigation update
                        <textarea name="update_text" rows="3" required></textarea>
                    </label>
                    <button type="submit" class="button">Add update</button>
                </form>
                <div class="report-actions">
                    <a class="button secondary" href="case_report.php?case_id=${selectedCase.id}" target="_blank">Generate crime report</a>
                </div>
            `;

            document.getElementById('caseDetailsPanel').classList.remove('hidden');
        }

        function hideDetails() {
            document.getElementById('caseDetailsPanel').classList.add('hidden');
            document.getElementById('detailsReference').textContent = 'Select a case to review';
            document.getElementById('detailsBody').innerHTML = '';
        }
    </script>
</body>
</html>
