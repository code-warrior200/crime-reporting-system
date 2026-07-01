CREATE DATABASE IF NOT EXISTS crime_reporting_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crime_reporting_system;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  fullname VARCHAR(100) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'officer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reports (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (username, password, fullname, role)
VALUES ('officer1', '$2y$10$KQG1n1bZqqQF4V5U6oWc2O8aUBS5wKfBV1uZt2UQloObxE7Y6pYLe', 'Patrol Officer', 'officer')
ON DUPLICATE KEY UPDATE fullname = VALUES(fullname), role = VALUES(role);

-- Password for officer1 is: officer@123
