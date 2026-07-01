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

CREATE TABLE IF NOT EXISTS cases (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS case_evidence (
  id INT AUTO_INCREMENT PRIMARY KEY,
  case_id INT NOT NULL,
  evidence_type VARCHAR(100) NOT NULL,
  details TEXT NOT NULL,
  logged_by VARCHAR(100) NOT NULL,
  logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS case_updates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  case_id INT NOT NULL,
  update_text TEXT NOT NULL,
  updated_by VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (username, password, fullname, role)
VALUES
    ('NPF/2024/100001', '$2y$10$KQG1n1bZqqQF4V5U6oWc2O8aUBS5wKfBV1uZt2UQloObxE7Y6pYLe', 'Chief Superintendent Akin', 'supervisor'),
    ('NPF/2024/100002', '$2y$10$KQG1n1bZqqQF4V5U6oWc2O8aUBS5wKfBV1uZt2UQloObxE7Y6pYLe', 'Detective Chief Inspector Bello', 'detective'),
    ('NPF/2024/100003', '$2y$10$KQG1n1bZqqQF4V5U6oWc2O8aUBS5wKfBV1uZt2UQloObxE7Y6pYLe', 'Sergeant Chidi', 'officer'),
    ('NPF/2024/100004', '$2y$10$KQG1n1bZqqQF4V5U6oWc2O8aUBS5wKfBV1uZt2UQloObxE7Y6pYLe', 'Inspector Danjuma', 'officer'),
    ('NPF/2024/100005', '$2y$10$KQG1n1bZqqQF4V5U6oWc2O8aUBS5wKfBV1uZt2UQloObxE7Y6pYLe', 'Constable Eze', 'officer'),
    ('NPF/2024/100006', '$2y$10$KQG1n1bZqqQF4V5U6oWc2O8aUBS5wKfBV1uZt2UQloObxE7Y6pYLe', 'Detective Sergeant Farouk', 'detective'),
    ('NPF/2024/100007', '$2y$10$KQG1n1bZqqQF4V5U6oWc2O8aUBS5wKfBV1uZt2UQloObxE7Y6pYLe', 'Corporal Grace', 'officer'),
    ('NPF/2024/100008', '$2y$10$KQG1n1bZqqQF4V5U6oWc2O8aUBS5wKfBV1uZt2UQloObxE7Y6pYLe', 'Station Officer Hassan', 'officer'),
    ('NPF/2024/100009', '$2y$10$KQG1n1bZqqQF4V5U6oWc2O8aUBS5wKfBV1uZt2UQloObxE7Y6pYLe', 'Patrol Officer Ifeoma', 'officer'),
    ('NPF/2024/100010', '$2y$10$KQG1n1bZqqQF4V5U6oWc2O8aUBS5wKfBV1uZt2UQloObxE7Y6pYLe', 'Investigations Officer James', 'officer'),
    ('NPF/2024/100011', '$2y$10$KQG1n1bZqqQF4V5U6oWc2O8aUBS5wKfBV1uZt2UQloObxE7Y6pYLe', 'Response Officer Kelechi', 'officer'),
    ('NPF/2024/100012', '$2y$10$KQG1n1bZqqQF4V5U6oWc2O8aUBS5wKfBV1uZt2UQloObxE7Y6pYLe', 'Traffic Officer Ladi', 'officer'),
    ('NPF/2024/100013', '$2y$10$KQG1n1bZqqQF4V5U6oWc2O8aUBS5wKfBV1uZt2UQloObxE7Y6pYLe', 'Cybercrime Officer Musa', 'officer'),
    ('NPF/2024/100014', '$2y$10$KQG1n1bZqqQF4V5U6oWc2O8aUBS5wKfBV1uZt2UQloObxE7Y6pYLe', 'Community Liaison Nneka', 'officer'),
    ('NPF/2024/100015', '$2y$10$KQG1n1bZqqQF4V5U6oWc2O8aUBS5wKfBV1uZt2UQloObxE7Y6pYLe', 'Support Officer Obi', 'officer')
ON DUPLICATE KEY UPDATE fullname = VALUES(fullname), role = VALUES(role);

-- Password for all NPF/2024/1000XX accounts is: officer@123
