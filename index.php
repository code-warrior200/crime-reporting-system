<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Reporting & Case Management</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="site-header">
        <div class="header-inner container">
            <div class="brand">
                <span class="brand-mark">CRS</span>
                <div>
                    <h1>Crime Reporting System</h1>
                    <p>Global-grade digital reporting and case tracking.</p>
                </div>
            </div>
            <nav>
                <ul class="nav-list">
                    <li><a href="#report-form">Submit Report</a></li>
                    <li><a href="track_status.php">Track Status</a></li>
                    <li><a href="#features">Features</a></li>
                </ul>
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
                    <p class="eyebrow">Trustworthy, transparent, accountable</p>
                    <h2>Report non-emergency incidents with confidence.</h2>
                    <p>Submit a digital report, get a unique tracking reference, and monitor case handling through a secure police archive.</p>
                    <div class="hero-actions">
                        <a class="button" href="#report-form">Report Now</a>
                        <a class="button secondary" href="track_status.php">Track Status</a>
                        <a class="button secondary" href="#features">See Features</a>
                    </div>
                </div>
                <div class="hero-panel">
                    <div class="panel-header">
                        <p>Fast incident support</p>
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
                <p>Monitor crime data by category and location to support evidence-based policing decisions.</p>
            </article>
        </section>

        <section id="report-form" class="container card form-card">
            <div class="section-intro">
                <p class="eyebrow">Submit a report</p>
                <h2>Non-emergency incident reporting</h2>
                <p>Complete the form below and keep your reference handy for follow-up.</p>
            </div>
            <form id="incidentForm" action="submit_report.php" method="post">
                <div class="form-grid">
                    <label>
                        Full Name
                        <input type="text" name="fullname" required>
                    </label>
                    <label>
                        Email Address
                        <input type="email" name="email" required>
                    </label>
                    <label>
                        Phone Number
                        <input type="tel" name="phone" required>
                    </label>
                    <label>
                        Incident Category
                        <select name="category" required>
                            <option value="Theft">Theft</option>
                            <option value="Vandalism">Vandalism</option>
                            <option value="Harassment">Harassment</option>
                            <option value="Suspicious Activity">Suspicious Activity</option>
                            <option value="Traffic Concern">Traffic Concern</option>
                            <option value="Other">Other</option>
                        </select>
                    </label>
                    <label>
                        Location
                        <input type="text" name="location" required>
                    </label>
                    <label>
                        Incident Date
                        <input type="date" name="incident_date" required>
                    </label>
                </div>
                <label class="full-width">
                    Incident Description
                    <textarea name="description" rows="6" required></textarea>
                </label>
                <div class="form-footer">
                    <p>All reports are securely recorded and accessible only to authorized officers.</p>
                    <button type="submit" class="button">Submit Report</button>
                </div>
            </form>
        </section>
    </main>

    <footer class="footer footer-wide">
        <div class="container footer-inner">
            <p>Built for secure, accountable case handling that meets international public safety standards.</p>
            <small>&copy; <?php echo date('Y'); ?> Crime Reporting System</small>
        </div>
    </footer>

    <script src="script.js"></script>
</body>
</html>
