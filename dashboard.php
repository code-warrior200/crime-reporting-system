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

$currentRole = strtolower((string) ($_SESSION['role'] ?? 'officer'));
$currentOfficerUsername = (string) ($_SESSION['username'] ?? '');
$roleLabels = [
    'supervisor' => 'Supervisor',
    'detective' => 'Detective',
    'officer' => 'Officer',
];

function isSupervisor(string $role): bool
{
    return $role === 'supervisor';
}

function isDetective(string $role): bool
{
    return $role === 'detective';
}

function canCreateCases(string $role): bool
{
    return isSupervisor($role) || isDetective($role);
}

function canAssignCases(string $role): bool
{
    return isSupervisor($role);
}

function canSeeCaseRecords(string $role): bool
{
    return in_array($role, ['supervisor', 'detective', 'officer'], true);
}

function isAssignedOfficer(array $case, string $username): bool
{
    return (($case['assigned_officer'] ?? '') === $username);
}

function canViewCase(array $case, string $role, string $username): bool
{
    return isSupervisor($role) || isAssignedOfficer($case, $username);
}

function canManageCase(array $case, string $role, string $username): bool
{
    return isSupervisor($role) || isDetective($role) || isAssignedOfficer($case, $username);
}

function canEditCaseRecord(array $case, string $role, string $username): bool
{
    return canManageCase($case, $role, $username);
}

function canResolveCase(array $case, string $username): bool
{
    return isAssignedOfficer($case, $username);
}

function canUpdateCaseProgress(array $case, string $username): bool
{
    return isAssignedOfficer($case, $username);
}

function canLogEvidence(array $case, string $username): bool
{
    return isAssignedOfficer($case, $username);
}

function canCloseCase(array $case, string $role, string $username): bool
{
    return isSupervisor($role) && ($case['status'] ?? '') === 'Resolved';
}

function clampProgress($value): int
{
    return max(0, min(100, (int) $value));
}

function findCaseById(array $cases, int $caseId): ?array
{
    foreach ($cases as $case) {
        if ((int) $case['id'] === $caseId) {
            return $case;
        }
    }

    return null;
}

function syncLinkedReportStatus(PDO $pdo, int $caseId, string $status): void
{
    $linkStmt = $pdo->prepare('SELECT report_id FROM cases WHERE id = :id LIMIT 1');
    $linkStmt->execute([':id' => $caseId]);
    $linkedCase = $linkStmt->fetch();
    if ($linkedCase && $linkedCase['report_id']) {
        $statusStmt = $pdo->prepare('UPDATE reports SET status = :status WHERE id = :report_id');
        $statusStmt->execute([
            ':status' => $status,
            ':report_id' => $linkedCase['report_id'],
        ]);
    }
}

function createCaseAssignmentNotification(PDO $pdo, int $caseId, string $recipientUsername, string $assignedBy, string $caseCode, string $caseTitle): void
{
    $message = sprintf('Supervisor assigned case %s to you: %s', $caseCode, $caseTitle);
    $stmt = $pdo->prepare('INSERT INTO case_assignment_notifications (case_id, recipient_username, assigned_by, message) VALUES (:case_id, :recipient_username, :assigned_by, :message)');
    $stmt->execute([
        ':case_id' => $caseId,
        ':recipient_username' => $recipientUsername,
        ':assigned_by' => $assignedBy,
        ':message' => $message,
    ]);
}

$officers = safeFetchAll($pdo, "SELECT username, fullname, role FROM users WHERE role IN ('supervisor','detective','officer') ORDER BY fullname");
$officerNames = [];
foreach ($officers as $officer) {
    $officerNames[$officer['username']] = $officer['fullname'];
}
$reports = safeFetchAll($pdo, 'SELECT * FROM reports ORDER BY created_at DESC');
$cases = safeFetchAll($pdo, 'SELECT c.*, r.reference_code, r.fullname AS reporter_name, r.officer_notes AS report_officer_notes FROM cases c LEFT JOIN reports r ON c.report_id = r.id ORDER BY c.created_at DESC');
$caseUpdates = safeFetchAll($pdo, 'SELECT * FROM case_updates ORDER BY created_at DESC');
$visibleCases = array_values(array_filter($cases, function (array $case) use ($currentRole, $currentOfficerUsername): bool {
    return canViewCase($case, $currentRole, $currentOfficerUsername);
}));
$visibleReportIds = [];
$visibleCaseIds = [];
foreach ($visibleCases as $case) {
    $visibleCaseIds[(int) $case['id']] = true;
    if (!empty($case['report_id'])) {
        $visibleReportIds[(int) $case['report_id']] = true;
    }
}
$visibleReports = (isSupervisor($currentRole) || isDetective($currentRole))
    ? $reports
    : array_values(array_filter($reports, static function (array $report) use ($visibleReportIds): bool {
        return isset($visibleReportIds[(int) $report['id']]);
    }));
$caseLinkableReports = isSupervisor($currentRole)
    ? $reports
    : array_values(array_filter($reports, static function (array $report) use ($visibleReportIds): bool {
        return isset($visibleReportIds[(int) $report['id']]);
    }));
$caseLinkableReportIds = [];
foreach ($caseLinkableReports as $report) {
    $caseLinkableReportIds[(int) $report['id']] = true;
}
$visibleUpdates = array_values(array_filter($caseUpdates, static function (array $entry) use ($visibleCaseIds): bool {
    return isset($visibleCaseIds[(int) $entry['case_id']]);
}));

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

$filteredReports = array_values(array_filter($visibleReports, function (array $report) use ($searchQuery, $reportStatusFilter): bool {
    $needle = strtolower($searchQuery);
    $matchesSearch = $needle === '' || strpos(strtolower($report['reference_code']), $needle) !== false || strpos(strtolower($report['fullname']), $needle) !== false || strpos(strtolower($report['category']), $needle) !== false || strpos(strtolower($report['location']), $needle) !== false || strpos(strtolower($report['status']), $needle) !== false;
    $matchesStatus = $reportStatusFilter === '' || $report['status'] === $reportStatusFilter;
    return $matchesSearch && $matchesStatus;
}));

$filteredCases = array_values(array_filter($visibleCases, function (array $caseItem) use ($searchQuery, $caseStatusFilter): bool {
    $needle = strtolower($searchQuery);
    $matchesSearch = $needle === '' || strpos(strtolower($caseItem['case_code']), $needle) !== false || strpos(strtolower($caseItem['title']), $needle) !== false || strpos(strtolower($caseItem['assigned_officer'] ?: ''), $needle) !== false || strpos(strtolower($caseItem['reference_code'] ?: ''), $needle) !== false || strpos(strtolower($caseItem['reporter_name'] ?: ''), $needle) !== false;
    $matchesStatus = $caseStatusFilter === '' || $caseItem['status'] === $caseStatusFilter;
    return $matchesSearch && $matchesStatus;
}));
$caseRecordsForDisplay = canSeeCaseRecords($currentRole) ? $filteredCases : [];
$caseUpdatesForDisplay = canSeeCaseRecords($currentRole) ? $visibleUpdates : [];
$latestUpdatesByCase = [];
foreach ($visibleUpdates as $update) {
    $updateCaseId = (int) $update['case_id'];
    if (!isset($latestUpdatesByCase[$updateCaseId])) {
        $latestUpdatesByCase[$updateCaseId] = $update;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_case' && canCreateCases($currentRole)) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $assigned_officer = canAssignCases($currentRole) ? (trim($_POST['assigned_officer'] ?? '') ?: null) : null;
        $status = $_POST['status'] ?? 'New';
        if (!in_array($status, ['New', 'Under Investigation'], true)) {
            $status = 'New';
        }
        $progress_percent = $status === 'Under Investigation' ? 10 : 0;
        $report_id = !empty($_POST['report_id']) ? (int) $_POST['report_id'] : null;
        if ($report_id !== null && !isset($caseLinkableReportIds[$report_id])) {
            $report_id = null;
        }

        if ($title && $description) {
            $case_code = 'CASE-' . strtoupper(uniqid());
            $stmt = $pdo->prepare('INSERT INTO cases (case_code, report_id, title, description, assigned_officer, status, progress_percent, created_by) VALUES (:case_code, :report_id, :title, :description, :assigned_officer, :status, :progress_percent, :created_by)');
            $stmt->execute([
                ':case_code' => $case_code,
                ':report_id' => $report_id,
                ':title' => $title,
                ':description' => $description,
                ':assigned_officer' => $assigned_officer,
                ':status' => $status,
                ':progress_percent' => $progress_percent,
                ':created_by' => $_SESSION['fullname'],
            ]);
            $createdCaseId = (int) $pdo->lastInsertId();

            if (isSupervisor($currentRole) && $assigned_officer) {
                createCaseAssignmentNotification($pdo, $createdCaseId, $assigned_officer, $_SESSION['fullname'], $case_code, $title);
            }

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
        $caseRow = findCaseById($cases, $case_id);

        if ($status === 'Closed' && !canCloseCase($caseRow ?? [], $currentRole, $currentOfficerUsername)) {
            $status = $caseRow['status'] ?? 'Under Investigation';
        }

        if ($status === 'Resolved' && ($caseRow['status'] ?? '') !== 'Resolved' && !canResolveCase($caseRow ?? [], $currentOfficerUsername)) {
            $status = $caseRow['status'] ?? 'Under Investigation';
        }

        if ($caseRow && canEditCaseRecord($caseRow, $currentRole, $currentOfficerUsername) && $title && $description) {
            $progress_percent = clampProgress($caseRow['progress_percent'] ?? 0);
            if (in_array($status, ['Resolved', 'Closed'], true)) {
                $progress_percent = 100;
            }
            $assignmentChanged = canAssignCases($currentRole) && $assigned_officer && $assigned_officer !== ($caseRow['assigned_officer'] ?? '');

            if (canAssignCases($currentRole)) {
                $stmt = $pdo->prepare('UPDATE cases SET title = :title, description = :description, assigned_officer = :assigned_officer, status = :status, progress_percent = :progress_percent WHERE id = :id');
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':assigned_officer' => $assigned_officer,
                    ':status' => $status,
                    ':progress_percent' => $progress_percent,
                    ':id' => $case_id,
                ]);
            } else {
                $stmt = $pdo->prepare('UPDATE cases SET title = :title, description = :description, status = :status, progress_percent = :progress_percent WHERE id = :id');
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':status' => $status,
                    ':progress_percent' => $progress_percent,
                    ':id' => $case_id,
                ]);
            }

            if ($assignmentChanged) {
                createCaseAssignmentNotification($pdo, $case_id, $assigned_officer, $_SESSION['fullname'], $caseRow['case_code'], $title);
            }

            syncLinkedReportStatus($pdo, $case_id, $status);
        }
    } elseif ($action === 'mark_assignment_notification_read') {
        $notificationId = (int) ($_POST['notification_id'] ?? 0);

        if ($notificationId > 0) {
            $stmt = $pdo->prepare('UPDATE case_assignment_notifications SET read_at = CURRENT_TIMESTAMP WHERE id = :id AND recipient_username = :recipient_username');
            $stmt->execute([
                ':id' => $notificationId,
                ':recipient_username' => $currentOfficerUsername,
            ]);
        } else {
            $stmt = $pdo->prepare('UPDATE case_assignment_notifications SET read_at = CURRENT_TIMESTAMP WHERE recipient_username = :recipient_username AND read_at IS NULL');
            $stmt->execute([
                ':recipient_username' => $currentOfficerUsername,
            ]);
        }
    } elseif ($action === 'log_update') {
        $case_id = (int) ($_POST['case_id'] ?? 0);
        $update_text = trim($_POST['update_text'] ?? '');

        $caseRow = findCaseById($cases, $case_id);
        $progress_percent = clampProgress($_POST['progress_percent'] ?? ($caseRow['progress_percent'] ?? 0));

        if ($caseRow && canManageCase($caseRow, $currentRole, $currentOfficerUsername) && $update_text) {
            $stmt = $pdo->prepare('INSERT INTO case_updates (case_id, update_text, updated_by) VALUES (:case_id, :update_text, :updated_by)');
            $stmt->execute([
                ':case_id' => $case_id,
                ':update_text' => $update_text,
                ':updated_by' => $_SESSION['fullname'],
            ]);

            if (canUpdateCaseProgress($caseRow, $currentOfficerUsername)) {
                $progressStmt = $pdo->prepare('UPDATE cases SET progress_percent = :progress_percent WHERE id = :id');
                $progressStmt->execute([
                    ':progress_percent' => $progress_percent,
                    ':id' => $case_id,
                ]);
            }
        }
    } elseif ($action === 'log_evidence') {
        $case_id = (int) ($_POST['case_id'] ?? 0);
        $evidence_type = trim($_POST['evidence_type'] ?? '');
        $details = trim($_POST['details'] ?? '');

        $caseRow = findCaseById($cases, $case_id);

        if ($caseRow && canLogEvidence($caseRow, $currentOfficerUsername) && $evidence_type && $details) {
            $stmt = $pdo->prepare('INSERT INTO case_evidence (case_id, evidence_type, details, logged_by) VALUES (:case_id, :evidence_type, :details, :logged_by)');
            $stmt->execute([
                ':case_id' => $case_id,
                ':evidence_type' => $evidence_type,
                ':details' => $details,
                ':logged_by' => $_SESSION['fullname'],
            ]);
        }
    } elseif ($action === 'resolve_case') {
        $case_id = (int) ($_POST['case_id'] ?? 0);
        $caseRow = findCaseById($cases, $case_id);

        if ($caseRow && canResolveCase($caseRow, $currentOfficerUsername)) {
            $stmt = $pdo->prepare('UPDATE cases SET status = :status, progress_percent = :progress_percent WHERE id = :id');
            $stmt->execute([
                ':status' => 'Resolved',
                ':progress_percent' => 100,
                ':id' => $case_id,
            ]);

            syncLinkedReportStatus($pdo, $case_id, 'Resolved');
        }
    } elseif ($action === 'close_case') {
        $case_id = (int) ($_POST['case_id'] ?? 0);
        $caseRow = findCaseById($cases, $case_id);

        if ($caseRow && canCloseCase($caseRow, $currentRole, $currentOfficerUsername)) {
            $stmt = $pdo->prepare('UPDATE cases SET status = :status, progress_percent = :progress_percent WHERE id = :id');
            $stmt->execute([
                ':status' => 'Closed',
                ':progress_percent' => 100,
                ':id' => $case_id,
            ]);

            syncLinkedReportStatus($pdo, $case_id, 'Closed');
        }
    } elseif ($action === 'update_report_notes') {
        $report_id = (int) ($_POST['report_id'] ?? 0);
        $officer_notes = trim($_POST['officer_notes'] ?? '');
        $status = trim($_POST['report_status'] ?? '');
        $caseRow = null;
        foreach ($cases as $case) {
            if ((int) ($case['report_id'] ?? 0) === $report_id) {
                $caseRow = $case;
                break;
            }
        }

        if ($caseRow && canManageCase($caseRow, $currentRole, $currentOfficerUsername)) {
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

$assignmentNotifications = [];
if ($currentOfficerUsername !== '') {
    $notificationStmt = $pdo->prepare("
        SELECT n.*, c.case_code, c.title, c.status
        FROM case_assignment_notifications n
        INNER JOIN cases c ON c.id = n.case_id
        WHERE n.recipient_username = :recipient_username
          AND n.read_at IS NULL
          AND c.assigned_officer = :assigned_username
        ORDER BY n.created_at DESC
    ");
    $notificationStmt->execute([
        ':recipient_username' => $currentOfficerUsername,
        ':assigned_username' => $currentOfficerUsername,
    ]);
    $assignmentNotifications = $notificationStmt->fetchAll();
}

$reportStatusCounts = [
    'New' => 0,
    'Under Investigation' => 0,
    'Resolved' => 0,
    'Closed' => 0,
];

foreach ($visibleReports as $report) {
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

foreach ($visibleCases as $case) {
    if (isset($caseStatusCounts[$case['status']])) {
        $caseStatusCounts[$case['status']]++;
    }
}

$averageProgress = 0;
if (count($visibleCases) > 0) {
    $progressTotal = 0;
    foreach ($visibleCases as $case) {
        $progressTotal += clampProgress($case['progress_percent'] ?? 0);
    }
    $averageProgress = (int) round($progressTotal / count($visibleCases));
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
                    <span><?php echo htmlspecialchars($roleLabels[$currentRole] ?? 'Officer'); ?></span>
                    <strong><?php echo htmlspecialchars($_SESSION['fullname']); ?></strong>
                </div>
                <a class="button secondary" href="logout.php">Logout</a>
            </div>
        </div>
    </header>

    <main class="container dashboard-layout">
        <?php if (count($assignmentNotifications) > 0): ?>
        <section class="assignment-notifications" aria-label="Case assignment notifications">
            <div class="assignment-notifications-header">
                <div>
                    <p class="eyebrow">New assignment</p>
                    <h2><?php echo count($assignmentNotifications); ?> case<?php echo count($assignmentNotifications) === 1 ? '' : 's'; ?> assigned by the Supervisor</h2>
                </div>
                <?php if (count($assignmentNotifications) > 1): ?>
                <form method="post" action="dashboard.php">
                    <input type="hidden" name="action" value="mark_assignment_notification_read">
                    <button type="submit" class="button secondary small">Dismiss all</button>
                </form>
                <?php endif; ?>
            </div>
            <div class="assignment-notification-list">
                <?php foreach ($assignmentNotifications as $notification): ?>
                    <article class="assignment-notification-card">
                        <div>
                            <span class="assignment-badge"><?php echo htmlspecialchars($notification['case_code']); ?></span>
                            <h3><?php echo htmlspecialchars($notification['title']); ?></h3>
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <small>Assigned by <?php echo htmlspecialchars($notification['assigned_by']); ?> on <?php echo htmlspecialchars($notification['created_at']); ?></small>
                        </div>
                        <div class="assignment-notification-actions">
                            <button type="button" class="button small" onclick="showCase(<?php echo (int) $notification['case_id']; ?>)">View case</button>
                            <form method="post" action="dashboard.php">
                                <input type="hidden" name="action" value="mark_assignment_notification_read">
                                <input type="hidden" name="notification_id" value="<?php echo (int) $notification['id']; ?>">
                                <button type="submit" class="button secondary small">Dismiss</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="dashboard-hero card">
            <div class="hero-copy">
                <p class="eyebrow">Overview</p>
                <h2>Operational readiness at a glance</h2>
                <p>Track reports, manage case records, and maintain a clear evidence trail for every investigation.</p>
                <div class="hero-stats-list">
                    <div class="hero-stat-card hero-stat-card--badge">
                        <span>Total reports</span>
                        <strong><?php echo count($visibleReports); ?></strong>
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
                        <strong><?php echo count($visibleCases); ?></strong>
                    </div>
                    <?php if (isSupervisor($currentRole)): ?>
                    <div class="hero-stat-card hero-stat-card--badge">
                        <span>Average progress</span>
                        <strong><?php echo $averageProgress; ?>%</strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if (isSupervisor($currentRole)): ?>
        <section class="card supervisor-progress-card">
            <div class="section-header">
                <div>
                    <p class="eyebrow">Officer progress</p>
                    <h2>Assigned case progress</h2>
                </div>
                <p class="table-note">Track how far each assigned investigation has moved.</p>
            </div>
            <?php if (count($visibleCases) === 0): ?>
                <p class="empty-note">No assigned cases are available for progress tracking.</p>
            <?php else: ?>
                <div class="progress-tracker-list">
                    <?php foreach ($visibleCases as $case): ?>
                        <?php
                            $caseProgress = clampProgress($case['progress_percent'] ?? 0);
                            $latestUpdate = $latestUpdatesByCase[(int) $case['id']] ?? null;
                        ?>
                        <article class="progress-tracker-item">
                            <div class="progress-tracker-heading">
                                <div>
                                    <span><?php echo htmlspecialchars($case['case_code']); ?></span>
                                    <h3><?php echo htmlspecialchars($case['title']); ?></h3>
                                </div>
                                <strong><?php echo $caseProgress; ?>%</strong>
                            </div>
                            <div class="progress-meter" aria-label="Case progress <?php echo $caseProgress; ?> percent">
                                <span style="width: <?php echo $caseProgress; ?>%"></span>
                            </div>
                            <div class="progress-tracker-meta">
                                <span><?php echo htmlspecialchars($officerNames[$case['assigned_officer']] ?? 'Unassigned'); ?></span>
                                <span><?php echo htmlspecialchars($case['status']); ?></span>
                            </div>
                            <p><?php echo htmlspecialchars($latestUpdate['update_text'] ?? 'No progress update recorded yet.'); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if (isSupervisor($currentRole) || isDetective($currentRole)): ?>
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

        <?php if (canCreateCases($currentRole)): ?>
        <section class="card form-card">
            <div class="section-header">
                <div>
                    <p class="eyebrow">Create case</p>
                    <h2>New case record</h2>
                </div>
                <p class="table-note">Start a new investigation, link it to a public report<?php echo canAssignCases($currentRole) ? ', and assign an officer.' : '.'; ?></p>
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
                        <?php foreach ($caseLinkableReports as $report): ?>
                            <option value="<?php echo $report['id']; ?>"><?php echo htmlspecialchars($report['reference_code'] . ' - ' . $report['fullname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if (canAssignCases($currentRole)): ?>
                    <label>
                        Assigned officer
                        <select name="assigned_officer">
                            <option value="">Unassigned</option>
                            <?php foreach ($officers as $officer): ?>
                                <option value="<?php echo htmlspecialchars($officer['username']); ?>"><?php echo htmlspecialchars($officer['fullname']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                <label>
                    Status
                    <select name="status">
                        <option value="New">New</option>
                        <option value="Under Investigation">Under Investigation</option>
                    </select>
                </label>
                <label class="full-width">
                    Case description
                    <textarea name="description" rows="5" required></textarea>
                </label>
                <div class="form-footer full-width">
                    <p>Create secure case records with investigation tracking<?php echo canAssignCases($currentRole) ? ' and assignment controls.' : '.'; ?></p>
                    <button type="submit" class="button">Create Case</button>
                </div>
            </form>
        </section>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (canSeeCaseRecords($currentRole)): ?>
        <section class="card table-card">
            <div class="section-header">
                <div>
                    <p class="eyebrow">Case records</p>
                    <h2>Active investigations</h2>
                </div>
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
            <?php if (count($caseRecordsForDisplay) === 0): ?>
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
                                <th>Progress</th>
                                <th>Linked report</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($caseRecordsForDisplay as $case): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($case['case_code']); ?></td>
                                    <td><?php echo htmlspecialchars($case['title']); ?></td>
                                    <td><?php echo htmlspecialchars($officerNames[$case['assigned_officer']] ?? 'Unassigned'); ?></td>
                                    <td><?php echo htmlspecialchars($case['status']); ?></td>
                                    <td>
                                        <?php $caseProgress = clampProgress($case['progress_percent'] ?? 0); ?>
                                        <div class="table-progress">
                                            <div class="progress-meter" aria-label="Case progress <?php echo $caseProgress; ?> percent">
                                                <span style="width: <?php echo $caseProgress; ?>%"></span>
                                            </div>
                                            <strong><?php echo $caseProgress; ?>%</strong>
                                        </div>
                                    </td>
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
        <?php endif; ?>
    </main>

    <script>
        const cases = <?php echo json_encode(canSeeCaseRecords($currentRole) ? $visibleCases : [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const caseUpdates = <?php echo json_encode($caseUpdatesForDisplay, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const officers = <?php echo json_encode($officers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const currentOfficerUsername = <?php echo json_encode($currentOfficerUsername, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const permissions = <?php echo json_encode([
            'canCreateCases' => canCreateCases($currentRole),
            'canAssignCases' => canAssignCases($currentRole),
            'isSupervisor' => isSupervisor($currentRole),
            'isDetective' => isDetective($currentRole),
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function nl2br(value) {
            return escapeHtml(value).replace(/\n/g, '<br>');
        }

        function getOfficerName(username) {
            const officer = officers.find(o => o.username === username);
            return officer ? officer.fullname : 'Unassigned';
        }

        function getStatusClass(status) {
            return `status-${String(status || 'new').toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;
        }

        function showCase(id) {
            const selectedCase = cases.find(c => c.id == id);
            if (!selectedCase) return;

            document.getElementById('detailsReference').textContent = `${selectedCase.case_code} - ${selectedCase.title}`;
            const body = document.getElementById('detailsBody');

            const updatesList = caseUpdates.filter(item => item.case_id == selectedCase.id);
            const progressPercent = Math.max(0, Math.min(100, parseInt(selectedCase.progress_percent ?? 0, 10) || 0));
            const isAssignedOfficer = selectedCase.assigned_officer && selectedCase.assigned_officer === currentOfficerUsername;
            const canManageCase = permissions.isSupervisor || permissions.isDetective || isAssignedOfficer;
            const canResolveCase = isAssignedOfficer;
            const evidenceForm = isAssignedOfficer ? `
                <form method="post" action="dashboard.php" class="case-update-form compact-form">
                    <div class="form-section-header">
                        <div>
                            <h3>Evidence</h3>
                            <p>Log physical, digital, or witness evidence.</p>
                        </div>
                    </div>
                    <input type="hidden" name="action" value="log_evidence">
                    <input type="hidden" name="case_id" value="${escapeHtml(selectedCase.id)}">
                    <label>
                        Evidence type
                        <input type="text" name="evidence_type" required>
                    </label>
                    <label class="full-width">
                        Details
                        <textarea name="details" rows="3" required></textarea>
                    </label>
                    <div class="form-actions full-width">
                        <button type="submit" class="button">Log evidence</button>
                    </div>
                </form>
            ` : '';
            const progressInput = isAssignedOfficer ? `
                    <label>
                        Progress (%)
                        <input type="number" name="progress_percent" min="0" max="100" value="${progressPercent}" required>
                    </label>
            ` : '';
            const canCloseCase = permissions.isSupervisor && selectedCase.status === 'Resolved';
            const resolveCaseTitle = 'Only the assigned officer can resolve this case';
            const closeCaseTitle = permissions.isSupervisor
                ? 'Case must be resolved before it can be closed'
                : 'Only the Supervisor can close a resolved case';
            const closeCaseForm = permissions.isSupervisor ? `
                <form method="post" action="dashboard.php" class="case-update-form resolution-form">
                    <input type="hidden" name="action" value="close_case">
                    <input type="hidden" name="case_id" value="${escapeHtml(selectedCase.id)}">
                    <button type="submit" class="button" ${canCloseCase ? '' : `disabled title="${closeCaseTitle}"`}>Close case</button>
                </form>
            ` : '';
            const assignmentField = permissions.canAssignCases
                ? `<label>
                        Assign investigator
                        <select name="assigned_officer">
                            <option value="">Unassigned</option>
                            ${officers.map(off => `<option value="${escapeHtml(off.username)}" ${off.username === selectedCase.assigned_officer ? 'selected' : ''}>${escapeHtml(off.fullname)}</option>`).join('')}
                        </select>
                    </label>`
                : `<input type="hidden" name="assigned_officer" value="${escapeHtml(selectedCase.assigned_officer || '')}">`;
            const actionForms = canManageCase ? `
                <form method="post" action="dashboard.php" class="case-update-form primary-form">
                    <div class="form-section-header">
                        <div>
                            <h3>Case record</h3>
                            <p>Keep the active assignment, status, and narrative current.</p>
                        </div>
                    </div>
                    <input type="hidden" name="action" value="update_case">
                    <input type="hidden" name="case_id" value="${escapeHtml(selectedCase.id)}">
                    <label>
                        Case title
                        <input type="text" name="title" value="${escapeHtml(selectedCase.title)}" required>
                    </label>
                    ${assignmentField}
                    <label>
                        Status
                        <select name="status">
                            <option value="New" ${selectedCase.status === 'New' ? 'selected' : ''}>New</option>
                            <option value="Under Investigation" ${selectedCase.status === 'Under Investigation' ? 'selected' : ''}>Under Investigation</option>
                            <option value="Resolved" ${selectedCase.status === 'Resolved' ? 'selected' : ''} ${canResolveCase || selectedCase.status === 'Resolved' ? '' : 'disabled'}>Resolved</option>
                            <option value="Closed" ${selectedCase.status === 'Closed' ? 'selected' : ''} ${canCloseCase || selectedCase.status === 'Closed' ? '' : 'disabled'}>Closed</option>
                        </select>
                    </label>
                    <label class="full-width">
                        Case narrative
                        <textarea name="description" rows="4" required>${escapeHtml(selectedCase.description)}</textarea>
                    </label>
                    <div class="form-actions full-width">
                        <button type="submit" class="button">Save case updates</button>
                    </div>
                </form>
                <div class="case-action-grid">
                ${evidenceForm}
                <form method="post" action="dashboard.php" class="case-update-form compact-form">
                    <div class="form-section-header">
                        <div>
                            <h3>Progress update</h3>
                            <p>Record the latest investigation activity.</p>
                        </div>
                    </div>
                    <input type="hidden" name="action" value="log_update">
                    <input type="hidden" name="case_id" value="${escapeHtml(selectedCase.id)}">
                    <label class="full-width">
                        Investigation update
                        <textarea name="update_text" rows="3" required></textarea>
                    </label>
                    ${progressInput}
                    <div class="form-actions full-width">
                        <button type="submit" class="button">Add update</button>
                    </div>
                </form>
                <form method="post" action="dashboard.php" class="case-update-form compact-form">
                    <div class="form-section-header">
                        <div>
                            <h3>Officer notes</h3>
                            <p>Keep linked report notes current.</p>
                        </div>
                    </div>
                    <input type="hidden" name="action" value="update_report_notes">
                    <input type="hidden" name="report_id" value="${escapeHtml(selectedCase.report_id || '')}">
                    <input type="hidden" name="report_status" value="${escapeHtml(selectedCase.status)}">
                    <label class="full-width">
                        Officer notes
                        <textarea name="officer_notes" rows="3">${escapeHtml(selectedCase.report_officer_notes || '')}</textarea>
                    </label>
                    <div class="form-actions full-width">
                        <button type="submit" class="button secondary">Save notes</button>
                    </div>
                </form>
                <div class="case-resolution-actions">
                    <div class="form-section-header">
                        <div>
                            <h3>Resolution</h3>
                            <p>Move the case toward completion.</p>
                        </div>
                    </div>
                <form method="post" action="dashboard.php" class="case-update-form resolution-form">
                    <input type="hidden" name="action" value="resolve_case">
                    <input type="hidden" name="case_id" value="${escapeHtml(selectedCase.id)}">
                    <button type="submit" class="button secondary" ${canResolveCase ? '' : `disabled title="${resolveCaseTitle}"`}>Resolve case</button>
                </form>

                ${closeCaseForm}
                </div>
                </div>
            ` : '<div class="case-view-only"><strong>View only</strong><span>You can review this case, but updates are limited to the assigned officer or Supervisor.</span></div>';

            body.innerHTML = `
                <div class="case-detail-shell">
                <div class="case-overview">
                    <section class="case-summary">
                        <div class="case-summary-topline">
                            <span class="case-code">${escapeHtml(selectedCase.case_code)}</span>
                            <span class="status-pill ${getStatusClass(selectedCase.status)}">${escapeHtml(selectedCase.status)}</span>
                        </div>
                        <h3>${escapeHtml(selectedCase.title)}</h3>
                        <p>${escapeHtml(selectedCase.reference_code ? `Linked to public report ${selectedCase.reference_code}` : 'No linked public report')}</p>
                        <div class="case-progress-summary">
                            <div class="progress-label">
                                <span>Case progress</span>
                                <strong>${progressPercent}%</strong>
                            </div>
                            <div class="progress-meter" aria-label="Case progress ${progressPercent} percent">
                                <span style="width: ${progressPercent}%"></span>
                            </div>
                        </div>
                        <div class="case-summary-actions">
                            <a class="button secondary small" href="case_report.php?case_id=${encodeURIComponent(selectedCase.id)}" target="_blank">Generate crime report</a>
                        </div>
                    </section>
                    <aside class="case-quick-stats" aria-label="Case summary">
                        <div>
                            <span>Assigned officer</span>
                            <strong>${escapeHtml(getOfficerName(selectedCase.assigned_officer))}</strong>
                        </div>
                        <div>
                            <span>Reporter</span>
                            <strong>${escapeHtml(selectedCase.reporter_name ? selectedCase.reporter_name : 'N/A')}</strong>
                        </div>
                        <div>
                            <span>Created by</span>
                            <strong>${escapeHtml(selectedCase.created_by)}</strong>
                        </div>
                        <div>
                            <span>Progress</span>
                            <strong>${progressPercent}%</strong>
                        </div>
                    </aside>
                </div>
                <div class="case-details">
                    <section class="case-text">
                        <div class="section-title-row">
                            <h3>Case description</h3>
                        </div>
                        <p>${nl2br(selectedCase.description)}</p>
                    </section>
                    <aside class="case-meta">
                        <h3>Record details</h3>
                        <dl>
                            <div>
                                <dt>Case code</dt>
                                <dd>${escapeHtml(selectedCase.case_code)}</dd>
                            </div>
                            <div>
                                <dt>Linked report</dt>
                                <dd>${escapeHtml(selectedCase.reference_code ? selectedCase.reference_code : 'None')}</dd>
                            </div>
                            <div>
                                <dt>Status</dt>
                                <dd>${escapeHtml(selectedCase.status)}</dd>
                            </div>
                            <div>
                                <dt>Assigned officer</dt>
                                <dd>${escapeHtml(getOfficerName(selectedCase.assigned_officer))}</dd>
                            </div>
                            <div>
                                <dt>Progress</dt>
                                <dd>${progressPercent}%</dd>
                            </div>
                        </dl>
                    </aside>
                </div>
                <div class="case-history">
                    <section class="case-log">
                        <div class="section-title-row">
                            <h3>Investigation updates</h3>
                            <span>${updatesList.length} ${updatesList.length === 1 ? 'entry' : 'entries'}</span>
                        </div>
                        ${updatesList.length ? updatesList.map(item => `<article class="case-log-item"><p>${nl2br(item.update_text)}</p><span>Updated by ${escapeHtml(item.updated_by)} on ${escapeHtml(item.created_at)}</span></article>`).join('') : '<p class="empty-note">No updates recorded yet.</p>'}
                    </section>
                </div>
                <div class="case-workspace">
                    <div class="section-title-row workspace-title">
                        <h3>Case actions</h3>
                        <span>${canManageCase ? 'Available to your role' : 'View only'}</span>
                    </div>
                    ${actionForms}
                </div>
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
