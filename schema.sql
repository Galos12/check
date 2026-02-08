CREATE DATABASE IF NOT EXISTS hvac_checklists;
USE hvac_checklists;

CREATE TABLE IF NOT EXISTS checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    technician_name VARCHAR(100) NOT NULL,
    van_name VARCHAR(100) NOT NULL,
    checklist_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS checklist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checklist_id INT NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    item_type ENUM('Tool', 'Part') NOT NULL,
    is_checked TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE CASCADE
);
