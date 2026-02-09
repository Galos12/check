<?php
require_once 'config.php';

function migrate(PDO $db): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS checklists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            technician_name TEXT NOT NULL,
            van_name TEXT NOT NULL,
            checklist_date TEXT NOT NULL,
            checklist_type TEXT NOT NULL DEFAULT 'Daily',
            notes TEXT,
            created_at DATETIME DEFAULT (datetime('now'))
        )",

        "CREATE TABLE IF NOT EXISTS checklist_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            checklist_id INTEGER NOT NULL,
            item_name TEXT NOT NULL,
            item_type TEXT NOT NULL,
            is_checked INTEGER NOT NULL DEFAULT 0,
            quantity INTEGER NULL,
            FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE CASCADE
        )",

        "CREATE TABLE IF NOT EXISTS technicians (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE
        )",

        "CREATE TABLE IF NOT EXISTS vans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE
        )",

        "CREATE TABLE IF NOT EXISTS parts_library (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE
        )",

        "CREATE TABLE IF NOT EXISTS tools_library (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            has_counter INTEGER NOT NULL DEFAULT 0,
            is_daily INTEGER NOT NULL DEFAULT 0,
            is_weekly INTEGER NOT NULL DEFAULT 0
        )",

        "CREATE TABLE IF NOT EXISTS technician_part_assignments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            technician_id INTEGER NOT NULL,
            part_id INTEGER NOT NULL,
            assigned_date TEXT NOT NULL,
            created_at DATETIME DEFAULT (datetime('now')),
            FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE CASCADE,
            FOREIGN KEY (part_id) REFERENCES parts_library(id) ON DELETE CASCADE
        )",
    ];

    foreach ($statements as $sql) {
        $db->exec($sql);
    }
}

try {
    $db = get_db_connection();
    migrate($db);
    echo "Migration complete. SQLite DB created at: " . DB_FILE . "\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
