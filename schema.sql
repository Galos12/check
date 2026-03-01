CREATE DATABASE IF NOT EXISTS hvac_checklists;
USE hvac_checklists;

CREATE TABLE IF NOT EXISTS checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    technician_name VARCHAR(100) NOT NULL,
    van_name VARCHAR(100) NOT NULL,
    checklist_date DATE NOT NULL,
    checklist_type ENUM('Daily', 'Weekly') NOT NULL DEFAULT 'Daily',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS checklist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checklist_id INT NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    item_type ENUM('Tool', 'Part') NOT NULL,
    is_checked TINYINT(1) NOT NULL DEFAULT 0,
    quantity INT NULL,
    FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS technicians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS vans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS parts_library (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    part_type VARCHAR(150) NOT NULL DEFAULT '',
    stock INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_part_name_type (name, part_type)
);

CREATE TABLE IF NOT EXISTS tools_library (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    has_counter TINYINT(1) NOT NULL DEFAULT 0,
    is_daily TINYINT(1) NOT NULL DEFAULT 0,
    is_weekly TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS technician_part_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    technician_id INT NOT NULL,
    part_id INT NOT NULL,
    assigned_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE CASCADE,
    FOREIGN KEY (part_id) REFERENCES parts_library(id) ON DELETE CASCADE
);
