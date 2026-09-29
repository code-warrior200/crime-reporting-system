<?php
require_once __DIR__ . '/../config/db.php';

$reportCount = (int) $pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn();
$resolvedReportCount = (int) $pdo->query("SELECT COUNT(*) FROM reports WHERE status IN ('Resolved','Closed')")->fetchColumn();
$caseCount = (int) $pdo->query('SELECT COUNT(*) FROM cases')->fetchColumn();
$activeCaseCount = (int) $pdo->query("SELECT COUNT(*) FROM cases WHERE status IN ('New','Under Investigation')")->fetchColumn();
$resolutionRate = $reportCount > 0 ? round(($resolvedReportCount / $reportCount) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zaria Area Command HQ | Digital Reporting Case Study</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <header class="site-header">
        <div class="header-inner container">
            <div class="brand">
                <span class="brand-mark">ZAC</span>
                <div>
                    <h1>Zaria Area Command HQ</h1>
                    <p>Digital Reporting & Case Management Case Study · Kaduna State, Nigeria</p>
                </div>
            </div>
            <nav>
                <div class="nav-actions">
                    <a class="button secondary" href="track_status.php">Check Status</a>
                    <a class="button" href="login.php">Officer Login</a>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero hero-landing">
            <div class="hero-copy container">
                <div>
                    <p class="eyebrow">Zaria Area Command HQ · Case study prototype</p>
                    <h2>Report non-emergency incidents with confidence.</h2>
                    <p>Submit a non-emergency incident report, receive a unique tracking reference, and follow its handling within this Zaria Area Command HQ case study.</p>
                    <div class="hero-actions">
                        <a class="button" href="#report-form">Report Now</a>
                        <a class="button secondary" href="track_status.php">Track Status</a>
                    </div>
                </div>
                <div class="hero-panel">
                    <div class="panel-header">
                        <p>Zaria Area Command HQ</p>
                        <h3>Digital filing for non-emergency cases</h3>
                    </div>
                    <ul class="stats-list">
                        <li><strong>24/7 online access</strong></li>
                        <li><strong>Unique case reference</strong></li>
                        <li><strong>Secure records archive</strong></li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="container performance-section">
            <div class="section-intro">
                <p class="eyebrow">Resolution & performance</p>
                <h2>Live statistics for public accountability</h2>
                    <p>See how this case-study portal tracks incident intake, active investigations, and case-resolution progress.</p>
            </div>
            <div class="performance-grid">
                <article class="performance-card">
                    <span class="metric-label">Reports submitted</span>
                    <strong><?php echo number_format($reportCount); ?></strong>
                    <p>Reports filed through this Zaria Area Command HQ case-study portal.</p>
                </article>
                <article class="performance-card">
                    <span class="metric-label">Resolution rate</span>
                    <strong><?php echo $resolutionRate; ?>%</strong>
                    <p>Reports already moved into resolved or closed status.</p>
                </article>
                <article class="performance-card">
                    <span class="metric-label">Active cases</span>
                    <strong><?php echo number_format($activeCaseCount); ?></strong>
                    <p>Investigations currently managed within the Area Command workflow.</p>
                </article>
                <article class="performance-card">
                    <span class="metric-label">Case records</span>
                    <strong><?php echo number_format($caseCount); ?></strong>
                    <p>Structured case files created for follow-up and evidence handling.</p>
                </article>
            </div>
        </section>

        <section id="features" class="container feature-grid">
            <article class="feature-card">
                <h3>Citizen Reporting</h3>
                <p>Submit non-emergency incidents quickly using a guided form and receive a reliable tracking reference.</p>
            </article>
            <article class="feature-card">
                <h3>Officer Investigation</h3>
                <p>Manage active cases, update investigation status, and keep transparent notes for every report.</p>
            </article>
            <article class="feature-card">
                <h3>Insights & Reports</h3>
                    <p>Monitor incident categories and locations across Zaria to support evidence-based policing decisions.</p>
            </article>
        </section>

        <section id="report-form" class="report-intake-section">
            <div class="container report-intake-shell">
                <aside class="report-intake-aside" aria-label="Reporting guidance">
                    <p class="eyebrow">Submit a report</p>
                    <h2>Non-emergency incident reporting</h2>
                    <p class="report-intake-lede">File a clear report for the Zaria Area Command HQ case study and receive a tracking reference for follow-up.</p>

                    <div class="emergency-notice">
                        <strong>For emergencies</strong>
                        <span>Use official emergency channels or go to the nearest police station immediately. This case-study portal is for non-emergency reporting only.</span>
                    </div>

                    <dl class="report-assurance-list">
                        <div>
                            <dt>Secure intake</dt>
                            <dd>Your report is stored for authorized officer review.</dd>
                        </div>
                        <div>
                            <dt>Trackable record</dt>
                            <dd>A reference code is generated after submission.</dd>
                        </div>
                        <div>
                            <dt>Clear handoff</dt>
                            <dd>Incident details are routed into the case management workflow.</dd>
                        </div>
                    </dl>
                </aside>

                <form id="incidentForm" class="report-intake-form" action="submit_report.php" method="post">
                    <div class="form-section-heading">
                        <div>
                            <span>Step 1</span>
                            <h3>Reporter details</h3>
                        </div>
                        <small>Required fields are marked</small>
                    </div>

                    <div class="report-form-grid">
                        <label>
                            Full name <span aria-hidden="true">*</span>
                            <input type="text" name="fullname" autocomplete="name" placeholder="Enter your legal name" required>
                        </label>
                        <label>
                            Email address <span aria-hidden="true">*</span>
                            <input type="email" name="email" autocomplete="email" placeholder="name@example.com" required>
                        </label>
                        <label>
                            Phone number <span aria-hidden="true">*</span>
                            <input type="tel" name="phone" autocomplete="tel" placeholder="+234 800 000 0000" required>
                        </label>
                    </div>

                    <div class="form-section-heading">
                        <div>
                            <span>Step 2</span>
                            <h3>Incident details</h3>
                        </div>
                    </div>

                    <div class="report-form-grid">
                        <label>
                            Incident category <span aria-hidden="true">*</span>
                            <select name="category" required>
                                <option value="" disabled selected>Select category</option>
                                <option value="Theft">Theft</option>
                                <option value="Burglary">Burglary</option>
                                <option value="Assault">Assault</option>
                                <option value="Vandalism">Vandalism</option>
                                <option value="Harassment">Harassment</option>
                                <option value="Suspicious Activity">Suspicious Activity</option>
                                <option value="Traffic Concern">Traffic Concern</option>
                                <option value="Other">Other</option>
                            </select>
                        </label>
                        <label>
                            Location <span aria-hidden="true">*</span>
                            <input type="text" name="location" autocomplete="street-address" placeholder="Street, landmark, or area" required>
                        </label>
                        <label>
                            Incident date <span aria-hidden="true">*</span>
                            <input type="date" name="incident_date" max="<?php echo date('Y-m-d'); ?>" required>
                        </label>
                    </div>

                    <label class="report-description-field">
                        Incident description <span aria-hidden="true">*</span>
                        <textarea name="description" rows="7" placeholder="Include what happened, when it happened, who was involved, and any visible evidence." required></textarea>
                    </label>

                    <div class="report-submit-row">
                        <p>After submission, keep the reference code shown on the confirmation screen.</p>
                        <button type="submit" class="button">Submit Report</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <footer class="footer footer-wide">
        <div class="container footer-inner">
            <p>Zaria Area Command HQ, Kaduna State, Nigeria — digital reporting and case-management case study.</p>
            <small>&copy; <?php echo date('Y'); ?> Academic prototype. Not an official Nigeria Police Force service.</small>
        </div>
    </footer>

    <script src="../assets/js/script.js"></script>
</body>
</html>
