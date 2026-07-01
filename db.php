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

    $stmt = $pdo->prepare('INSERT IGNORE INTO users (username, password, fullname, role) VALUES (:username, :password, :fullname, :role)');
    $stmt->execute([
        ':username' => 'officer1',
        ':password' => password_hash('officer@123', PASSWORD_DEFAULT),
        ':fullname' => 'Patrol Officer',
        ':role' => 'officer',
    ]);
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