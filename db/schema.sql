-- ILS Help Desk Database Schema
CREATE DATABASE IF NOT EXISTS ilshd_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ilshd_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    middle_initial VARCHAR(5) DEFAULT NULL,
    last_name VARCHAR(100) NOT NULL,
    suffix VARCHAR(20) DEFAULT NULL,
    department VARCHAR(50) NOT NULL,
    classification VARCHAR(50) NOT NULL,
    school_email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','admin') NOT NULL DEFAULT 'student',
    phone VARCHAR(30) DEFAULT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    remember_token VARCHAR(64) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    concern_type ENUM('Request','Incident') NOT NULL,
    urgency_level ENUM('Low','Medium','High') NOT NULL,
    subject VARCHAR(100) NOT NULL,
    issue_description VARCHAR(255) NOT NULL,
    additional_comments TEXT DEFAULT NULL,
    device_type VARCHAR(50) DEFAULT NULL,
    attachment VARCHAR(255) DEFAULT NULL,
    date_needed DATE DEFAULT NULL,
    status ENUM('Pending','Resolved') NOT NULL DEFAULT 'Pending',
    resolved_date DATE DEFAULT NULL,
    resolved_comment TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Start ticket IDs from 1001
ALTER TABLE tickets AUTO_INCREMENT = 1001;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ticket_id INT NOT NULL,
    type ENUM('submitted','resolved') NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL
);

-- Default admin account (password: admin123)
INSERT IGNORE INTO users (first_name, last_name, department, classification, school_email, password, role)
VALUES ('ILS', 'Support', 'ILS', 'Admin', 'admin@ils.edu.ph', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
