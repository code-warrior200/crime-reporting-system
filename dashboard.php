<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function safeFetchAll(PDO $pdo, string $sql): array
{
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (PDOException $e) {
        $message = $e->getMessage();
        if (stripos($message, "doesn't exist in engine") !== false || stripos($message, "can't open table") !== false || stripos($message, 'doesn\'t exist') !== false) {
            if (function_exists('ensureSchema')) {
                ensureSchema($pdo);
            }
            return $pdo->query($sql)->fetchAll();
        }
        throw $e;
    }
}

$officers = safeFetchAll($pdo, "SELECT username, fullname, role FROM users WHERE role IN ('supervisor','detective','officer') ORDER BY fullname");
$reports = safeFetchAll($pdo, 'SELECT * FROM reports ORDER BY created_at DESC');
$cases = safeFetchAll($pdo, 'SELECT c.*, r.reference_code, r.fullname AS reporter_name, r.officer_notes AS report_officer_notes FROM cases c LEFT JOIN reports r ON c.report_id = r.id ORDER BY c.created_at DESC');
$caseEvidence = safeFetchAll($pdo, 'SELECT * FROM case_evidence ORDER BY logged_at DESC');
$caseUpdates = safeFetchAll($pdo, 'SELECT * FROM case_updates ORDER BY created_at DESC');

$filterStartDate = trim($_GET['start_date'] ?? '');
$filterEndDate = trim($_GET['end_date'] ?? '');
$statsWhere = ['1=1'];
$statsParams = [];

if ($filterStartDate !== '') {
    $statsWhere[] = 'incident_date >= :start_date';
    $statsParams[':start_date'] = $filterStartDate;
}

if ($filterEndDate !== '') {
    $statsWhere[] = 'incident_date <= :end_date';
    $statsParams[':end_date'] = $filterEndDate;
}

$whereSql = implode(' AND ', $statsWhere);

$statsByCategoryStmt = $pdo->prepare("SELECT category, COUNT(*) AS total FROM reports WHERE $whereSql GROUP BY category ORDER BY total DESC, category");
$statsByCategoryStmt->execute($statsParams);
$statsByCategory = $statsByCategoryStmt->fetchAll();

$statsByLocationStmt = $pdo->prepare("SELECT location, COUNT(*) AS total FROM reports WHERE $whereSql GROUP BY location ORDER BY total DESC, location");
$statsByLocationStmt->execute($statsParams);
$statsByLocation = $statsByLocationStmt->fetchAll();

$statsByPeriodStmt = $pdo->prepare("SELECT DATE_FORMAT(incident_date, '%b %Y') AS period_label, COUNT(*) AS total FROM reports WHERE $whereSql GROUP BY period_label ORDER BY MIN(incident_date)");
$statsByPeriodStmt->execute($statsParams);
$statsByPeriod = $statsByPeriodStmt->fetchAll();

$searchQuery = trim($_GET['search'] ?? '');
$caseStatusFilter = trim($_GET['case_status'] ?? '');
$reportStatusFilter = trim($_GET['report_status'] ?? '');

$filteredReports = array_values(array_filter($reports, function (array $report) use ($searchQuery, $reportStatusFilter): bool {
    $needle = strtolower($searchQuery);
    $matchesSearch = $needle === '' || strpos(strtolower($report['reference_code']), $needle) !== false || strpos(strtolower($report['fullname']), $needle) !== false || strpos(strtolower($report['category']), $needle) !== false || strpos(strtolower($report['location']), $needle) !== false || strpos(strtolower($report['status']), $needle) !== false;
    $matchesStatus = $reportStatusFilter === '' || $report['status'] === $reportStatusFilter;
    return $matchesSearch && $matchesStatus;
}));

$filteredCases = array_values(array_filter($cases, function (array $caseItem) use ($searchQuery, $caseStatusFilter): bool {
    $needle = strtolower($searchQuery);
    $matchesSearch = $needle === '' || strpos(strtolower($caseItem['case_code']), $needle) !== false || strpos(strtolower($caseItem['title']), $needle) !== false || strpos(strtolower($caseItem['assigned_officer'] ?: ''), $needle) !== false || strpos(strtolower($caseItem['reference_code'] ?: ''), $needle) !== false || strpos(strtolower($caseItem['reporter_name'] ?: ''), $needle) !== false;
    $matchesStatus = $caseStatusFilter === '' || $caseItem['status'] === $caseStatusFilter;
    return $matchesSearch && $matchesStatus;
}));

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

            if ($report_id) {
                $statusStmt = $pdo->prepare('UPDATE reports SET status = :status WHERE id = :report_id');
                $statusStmt->execute([
                    ':status' => $status,
                    ':report_id' => $report_id,
                ]);
            }
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

            $linkStmt = $pdo->prepare('SELECT report_id FROM cases WHERE id = :id LIMIT 1');
            $linkStmt->execute([':id' => $case_id]);
            $linkedCase = $linkStmt->fetch();
            if ($linkedCase && $linkedCase['report_id']) {
                $statusStmt = $pdo->prepare('UPDATE reports SET status = :status WHERE id = :report_id');
                $statusStmt->execute([
                    ':status' => $status,
                    ':report_id' => $linkedCase['report_id'],
                ]);
            }
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
    } elseif ($action === 'resolve_case') {
        $case_id = (int) ($_POST['case_id'] ?? 0);

        if ($case_id) {
            $stmt = $pdo->prepare('UPDATE cases SET status = :status WHERE id = :id');
            $stmt->execute([
                ':status' => 'Resolved',
                ':id' => $case_id,
            ]);

            $linkStmt = $pdo->prepare('SELECT report_id FROM cases WHERE id = :id LIMIT 1');
            $linkStmt->execute([':id' => $case_id]);
            $linkedCase = $linkStmt->fetch();
            if ($linkedCase && $linkedCase['report_id']) {
                $statusStmt = $pdo->prepare('UPDATE reports SET status = :status WHERE id = :report_id');
                $statusStmt->execute([
                    ':status' => 'Resolved',
                    ':report_id' => $linkedCase['report_id'],
                ]);
            }
        }
    } elseif ($action === 'update_report_notes') {
        $report_id = (int) ($_POST['report_id'] ?? 0);
        $officer_notes = trim($_POST['officer_notes'] ?? '');
        $status = trim($_POST['report_status'] ?? '');

        if ($report_id) {
            $noteStmt = $pdo->prepare('UPDATE reports SET officer_notes = :officer_notes, status = :status WHERE id = :id');
            $noteStmt->execute([
                ':officer_notes' => $officer_notes,
                ':status' => $status ?: 'Under Investigation',
                ':id' => $report_id,
            ]);
        }
    }

    header('Location: dashboard.php');
    exit;
}

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

        <section class="card stats-filter-card">
            <div class="section-header">
                <div>
                    <p class="eyebrow">Crime statistics</p>
                    <h2>Filter incident reporting trends</h2>
                </div>
                <p class="table-note">Review crime volume by category, location, and month for any reporting window.</p>
            </div>
            <form method="get" action="dashboard.php" class="stats-filter">
                <label>
                    From
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($filterStartDate); ?>">
                </label>
                <label>
                    To
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($filterEndDate); ?>">
                </label>
                <button type="submit" class="button">Apply filter</button>
            </form>
        </section>

        <section class="stats-panel">
            <div class="stats-card">
                <h2>Reports by category</h2>
                <?php if (count($statsByCategory) === 0): ?>
                    <p class="empty-note">No reports match the selected period.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($statsByCategory as $item): ?>
                            <li><?php echo htmlspecialchars($item['category']); ?> <span><?php echo $item['total']; ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="stats-card">
                <h2>Reports by location</h2>
                <?php if (count($statsByLocation) === 0): ?>
                    <p class="empty-note">No reports match the selected period.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($statsByLocation as $item): ?>
                            <li><?php echo htmlspecialchars($item['location']); ?> <span><?php echo $item['total']; ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="stats-card full-width">
                <h2>Reports by time period</h2>
                <?php if (count($statsByPeriod) === 0): ?>
                    <p class="empty-note">No reports match the selected period.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($statsByPeriod as $item): ?>
                            <li><?php echo htmlspecialchars($item['period_label']); ?> <span><?php echo $item['total']; ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
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
            <form method="get" action="dashboard.php" class="dashboard-search">
                <label>
                    Search cases or reports
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Reference, case title, officer, location">
                </label>
                <label>
                    Case status
                    <select name="case_status">
                        <option value="">All</option>
                        <option value="New" <?php echo $caseStatusFilter === 'New' ? 'selected' : ''; ?>>New</option>
                        <option value="Under Investigation" <?php echo $caseStatusFilter === 'Under Investigation' ? 'selected' : ''; ?>>Under Investigation</option>
                        <option value="Resolved" <?php echo $caseStatusFilter === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="Closed" <?php echo $caseStatusFilter === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </label>
                <label>
                    Report status
                    <select name="report_status">
                        <option value="">All</option>
                        <option value="New" <?php echo $reportStatusFilter === 'New' ? 'selected' : ''; ?>>New</option>
                        <option value="Under Investigation" <?php echo $reportStatusFilter === 'Under Investigation' ? 'selected' : ''; ?>>Under Investigation</option>
                        <option value="Resolved" <?php echo $reportStatusFilter === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="Closed" <?php echo $reportStatusFilter === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </label>
                <div class="search-actions">
                    <button type="submit" class="button">Search</button>
                    <a class="button secondary" href="dashboard.php">Reset</a>
                </div>
            </form>
            <?php if (count($filteredCases) === 0): ?>
                <div class="empty-state">
                    <p class="empty-title">No matching case records were found.</p>
                    <p>Try another keyword or reset the filters to review the full case queue.</p>
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
                            <?php foreach ($filteredCases as $case): ?>
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
                <form method="post" action="dashboard.php" class="case-update-form">
                    <input type="hidden" name="action" value="update_report_notes">
                    <input type="hidden" name="report_id" value="${selectedCase.report_id || ''}">
                    <input type="hidden" name="report_status" value="${selectedCase.status}">
                    <label class="full-width">
                        Officer notes
                        <textarea name="officer_notes" rows="3">${selectedCase.report_officer_notes ? selectedCase.report_officer_notes.replace(/"/g, '&quot;') : ''}</textarea>
                    </label>
                    <button type="submit" class="button secondary">Save notes</button>
                </form>
                <form method="post" action="dashboard.php" class="case-update-form">
                    <input type="hidden" name="action" value="resolve_case">
                    <input type="hidden" name="case_id" value="${selectedCase.id}">
                    <button type="submit" class="button secondary">Resolve case</button>
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
