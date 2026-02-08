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

if ($technician_name === '' || $van_name === '' || ($checklist_date_input === '' && $checklist_date_display === '')) {
    header('Location: index.php');
    exit;
}

$checklist_date = $checklist_date_input;
if ($checklist_date === '' && $checklist_date_display !== '') {
    $checklist_date = $checklist_date_display;
}

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
$db->beginTransaction();

try {
    $stmt = $db->prepare('INSERT INTO checklists (technician_name, van_name, checklist_date, checklist_type, notes) VALUES (:technician_name, :van_name, :checklist_date, :checklist_type, :notes)');
    $stmt->execute([
        ':technician_name' => $technician_name,
        ':van_name' => $van_name,
        ':checklist_date' => $checklist_date,
        ':checklist_type' => $checklist_type,
        ':notes' => $notes,
    ]);

    $checklist_id = (int) $db->lastInsertId();
    $item_stmt = $db->prepare('INSERT INTO checklist_items (checklist_id, item_name, item_type, is_checked, quantity) VALUES (:checklist_id, :item_name, :item_type, :is_checked, :quantity)');

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

        $item_stmt->execute([
            ':checklist_id' => $checklist_id,
            ':item_name' => $item_name,
            ':item_type' => $item_type,
            ':is_checked' => $is_checked,
            ':quantity' => $quantity,
        ]);
    }

    $db->commit();
    header('Location: view_checklist.php?id=' . $checklist_id);
    exit;
} catch (Throwable $error) {
    $db->rollBack();
    http_response_code(500);
    echo 'Unable to save checklist. Please try again.';
}
