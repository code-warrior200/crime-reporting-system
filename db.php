<?php
session_start();

$dbHost = 'localhost';
$dbName = 'crime_reporting_system';
$dbUser = 'root';
$dbPass = '';

function ensureSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        fullname VARCHAR(100) NOT NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'officer'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reference_code VARCHAR(50) NOT NULL UNIQUE,
        fullname VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(30) NOT NULL,
        category VARCHAR(50) NOT NULL,
        location VARCHAR(150) NOT NULL,
        incident_date DATE NOT NULL,
        description TEXT NOT NULL,
        officer_notes TEXT,
        status ENUM('New','Under Investigation','Resolved','Closed') NOT NULL DEFAULT 'New',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        case_code VARCHAR(50) NOT NULL UNIQUE,
        report_id INT NULL,
        title VARCHAR(150) NOT NULL,
        description TEXT NOT NULL,
        assigned_officer VARCHAR(50) DEFAULT NULL,
        status ENUM('New','Under Investigation','Resolved','Closed') NOT NULL DEFAULT 'New',
        created_by VARCHAR(100) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS case_evidence (
        id INT AUTO_INCREMENT PRIMARY KEY,
        case_id INT NOT NULL,
        evidence_type VARCHAR(100) NOT NULL,
        details TEXT NOT NULL,
        logged_by VARCHAR(100) NOT NULL,
        logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS case_updates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        case_id INT NOT NULL,
        update_text TEXT NOT NULL,
        updated_by VARCHAR(100) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare('INSERT IGNORE INTO users (username, password, fullname, role) VALUES (:username, :password, :fullname, :role)');
    $officers = [
        ['NPF/2024/100001', 'Chief Superintendent Akin', 'supervisor'],
        ['NPF/2024/100002', 'Detective Chief Inspector Bello', 'detective'],
        ['NPF/2024/100003', 'Sergeant Chidi', 'officer'],
        ['NPF/2024/100004', 'Inspector Danjuma', 'officer'],
        ['NPF/2024/100005', 'Constable Eze', 'officer'],
        ['NPF/2024/100006', 'Detective Sergeant Farouk', 'detective'],
        ['NPF/2024/100007', 'Corporal Grace', 'officer'],
        ['NPF/2024/100008', 'Station Officer Hassan', 'officer'],
        ['NPF/2024/100009', 'Patrol Officer Ifeoma', 'officer'],
        ['NPF/2024/100010', 'Investigations Officer James', 'officer'],
        ['NPF/2024/100011', 'Response Officer Kelechi', 'officer'],
        ['NPF/2024/100012', 'Traffic Officer Ladi', 'officer'],
        ['NPF/2024/100013', 'Cybercrime Officer Musa', 'officer'],
        ['NPF/2024/100014', 'Community Liaison Nneka', 'officer'],
        ['NPF/2024/100015', 'Support Officer Obi', 'officer'],
    ];

    foreach ($officers as [$username, $fullname, $role]) {
        $stmt->execute([
            ':username' => $username,
            ':password' => password_hash('officer@123', PASSWORD_DEFAULT),
            ':fullname' => $fullname,
            ':role' => $role,
        ]);
    }
}

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    ensureSchema($pdo);
} catch (PDOException $e) {
    if ($e->getCode() === 1049) {
        try {
            $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");
            ensureSchema($pdo);
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $inner) {
            die('Database auto-create failed: ' . htmlspecialchars($inner->getMessage()));
        }
    } else {
        die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
    }
}
?>