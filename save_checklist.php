<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$technician_name = trim($_POST['technician_name'] ?? '');
$van_name = trim($_POST['van_name'] ?? '');
$checklist_date_input = trim($_POST['checklist_date'] ?? '');
$checklist_date_display = trim($_POST['checklist_date_display'] ?? '');
$checklist_type = trim($_POST['checklist_type'] ?? 'Daily');
$notes = trim($_POST['notes'] ?? '');
$items = $_POST['items'] ?? [];
$existing_checklist_id = (int) ($_POST['existing_checklist_id'] ?? 0);

if ($technician_name === '' || $van_name === '' || ($checklist_date_input === '' && $checklist_date_display === '')) {
    header('Location: index.php');
    exit;
}

$checklist_date = $checklist_date_input !== '' ? $checklist_date_input : $checklist_date_display;
if (strpos($checklist_date, '/') !== false) {
    $date = DateTime::createFromFormat('d/m/Y', $checklist_date);
    $checklist_date = $date ? $date->format('Y-m-d') : '';
}
if ($checklist_date === '') {
    $checklist_date = (new DateTime())->format('Y-m-d');
}

if (!in_array($checklist_type, ['Daily', 'Weekly'], true)) {
    $checklist_type = 'Daily';
}

$db = get_db_connection();
$db->begin_transaction();

try {
    $db->query('CREATE TABLE IF NOT EXISTS checklist_revisions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        checklist_id INT NOT NULL,
        revision_number INT NOT NULL,
        saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_revision (checklist_id, revision_number)
    )');
    $db->query('CREATE TABLE IF NOT EXISTS checklist_revision_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        revision_id INT NOT NULL,
        item_name VARCHAR(150) NOT NULL,
        item_type ENUM("Tool","Part") NOT NULL,
        is_checked TINYINT(1) NOT NULL DEFAULT 0,
        quantity INT NULL,
        FOREIGN KEY (revision_id) REFERENCES checklist_revisions(id) ON DELETE CASCADE
    )');

    $checklist_id = 0;
    if ($checklist_type === 'Daily') {
        if ($existing_checklist_id > 0) {
            $check_stmt = $db->prepare('SELECT id FROM checklists WHERE id = ? AND checklist_type = "Daily" LIMIT 1');
            $check_stmt->bind_param('i', $existing_checklist_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result && $check_result->num_rows === 1) {
                $checklist_id = $existing_checklist_id;
            }
        }

        if ($checklist_id === 0) {
            $find_stmt = $db->prepare('SELECT id FROM checklists WHERE technician_name = ? AND van_name = ? AND checklist_date = ? AND checklist_type = "Daily" ORDER BY created_at DESC LIMIT 1');
            $find_stmt->bind_param('sss', $technician_name, $van_name, $checklist_date);
            $find_stmt->execute();
            $found = $find_stmt->get_result();
            $row = $found ? $found->fetch_assoc() : null;
            if ($row) {
                $checklist_id = (int) $row['id'];
            }
        }
    }

    if ($checklist_id > 0) {
        $update_stmt = $db->prepare('UPDATE checklists SET notes = ?, van_name = ?, technician_name = ? WHERE id = ?');
        $update_stmt->bind_param('sssi', $notes, $van_name, $technician_name, $checklist_id);
        $update_stmt->execute();

        $delete_items_stmt = $db->prepare('DELETE FROM checklist_items WHERE checklist_id = ?');
        $delete_items_stmt->bind_param('i', $checklist_id);
        $delete_items_stmt->execute();
    } else {
        $insert_stmt = $db->prepare('INSERT INTO checklists (technician_name, van_name, checklist_date, checklist_type, notes) VALUES (?, ?, ?, ?, ?)');
        $insert_stmt->bind_param('sssss', $technician_name, $van_name, $checklist_date, $checklist_type, $notes);
        $insert_stmt->execute();
        $checklist_id = (int) $db->insert_id;
    }

    $item_stmt = $db->prepare('INSERT INTO checklist_items (checklist_id, item_name, item_type, is_checked, quantity) VALUES (?, ?, ?, ?, ?)');
    $saved_items = [];
    foreach ($items as $item) {
        $item_name = trim($item['name'] ?? '');
        $item_type = $item['type'] ?? 'Tool';
        $is_checked = isset($item['checked']) ? 1 : 0;
        $quantity = isset($item['quantity']) && $item['quantity'] !== '' ? (int) $item['quantity'] : null;

        if ($item_name === '') {
            continue;
        }
        if (!in_array($item_type, ['Tool', 'Part'], true)) {
            $item_type = 'Tool';
        }

        $item_stmt->bind_param('issii', $checklist_id, $item_name, $item_type, $is_checked, $quantity);
        $item_stmt->execute();
        $saved_items[] = ['name' => $item_name, 'type' => $item_type, 'checked' => $is_checked, 'quantity' => $quantity];
    }

    $rev_num_stmt = $db->prepare('SELECT COALESCE(MAX(revision_number), 0) AS max_revision FROM checklist_revisions WHERE checklist_id = ?');
    $rev_num_stmt->bind_param('i', $checklist_id);
    $rev_num_stmt->execute();
    $rev_result = $rev_num_stmt->get_result();
    $max_revision = (int) (($rev_result ? $rev_result->fetch_assoc()['max_revision'] : 0) ?? 0);
    $new_revision = $max_revision + 1;

    $rev_insert_stmt = $db->prepare('INSERT INTO checklist_revisions (checklist_id, revision_number) VALUES (?, ?)');
    $rev_insert_stmt->bind_param('ii', $checklist_id, $new_revision);
    $rev_insert_stmt->execute();
    $revision_id = (int) $db->insert_id;

    $rev_item_stmt = $db->prepare('INSERT INTO checklist_revision_items (revision_id, item_name, item_type, is_checked, quantity) VALUES (?, ?, ?, ?, ?)');
    foreach ($saved_items as $saved_item) {
        $rev_item_stmt->bind_param('issii', $revision_id, $saved_item['name'], $saved_item['type'], $saved_item['checked'], $saved_item['quantity']);
        $rev_item_stmt->execute();
    }

    $db->commit();
    header('Location: view_checklist.php?id=' . $checklist_id);
    exit;
} catch (Throwable $error) {
    $db->rollback();
    http_response_code(500);
    echo 'Unable to save checklist. Please try again.';
}
