<?php
require_once 'config.php';

$checklist_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($checklist_id <= 0) {
    header('Location: history.php');
    exit;
}

$db = get_db_connection();
$db->query('ALTER TABLE checklists ADD COLUMN IF NOT EXISTS fuel_level TINYINT UNSIGNED NULL');
$db->query('ALTER TABLE checklists ADD COLUMN IF NOT EXISTS oil_level TINYINT UNSIGNED NULL');
$db->query("ALTER TABLE checklists ADD COLUMN IF NOT EXISTS refrigerant_level ENUM('Low','Mid','Full') NULL");
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

$checklist_stmt = $db->prepare('SELECT * FROM checklists WHERE id = ?');
$checklist_stmt->bind_param('i', $checklist_id);
$checklist_stmt->execute();
$checklist = ($checklist_stmt->get_result())->fetch_assoc();
if (!$checklist) {
    header('Location: history.php');
    exit;
}

$item_stmt = $db->prepare('SELECT item_name, item_type, is_checked, quantity FROM checklist_items WHERE checklist_id = ? ORDER BY item_type, item_name');
$item_stmt->bind_param('i', $checklist_id);
$item_stmt->execute();
$items = ($item_stmt->get_result())->fetch_all(MYSQLI_ASSOC);

$revision_stmt = $db->prepare('SELECT id, revision_number, saved_at FROM checklist_revisions WHERE checklist_id = ? ORDER BY revision_number DESC');
$revision_stmt->bind_param('i', $checklist_id);
$revision_stmt->execute();
$revisions = ($revision_stmt->get_result())->fetch_all(MYSQLI_ASSOC);

$revision_items = [];
if ($revisions) {
    $ids = array_map(fn($r) => (int) $r['id'], $revisions);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $db->prepare("SELECT revision_id, item_name, item_type, is_checked, quantity FROM checklist_revision_items WHERE revision_id IN ($ph) ORDER BY item_type, item_name");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $rows = ($stmt->get_result())->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $row) {
        $revision_items[(int) $row['revision_id']][] = $row;
    }
}

$checklist_date = new DateTime($checklist['checklist_date']);
$created_at = new DateTime($checklist['created_at']);
$assigned_parts = [];
$technician_stmt = $db->prepare('SELECT id FROM technicians WHERE name = ?');
$technician_stmt->bind_param('s', $checklist['technician_name']);
$technician_stmt->execute();
$technician = ($technician_stmt->get_result())->fetch_assoc();
if ($technician) {
    $assigned_stmt = $db->prepare('SELECT p.name FROM technician_part_assignments tpa JOIN parts_library p ON p.id = tpa.part_id WHERE tpa.technician_id = ? AND tpa.assigned_date = ?');
    $tech_id = (int) $technician['id'];
    $date_iso = $checklist_date->format('Y-m-d');
    $assigned_stmt->bind_param('is', $tech_id, $date_iso);
    $assigned_stmt->execute();
    $assigned_rows = ($assigned_stmt->get_result())->fetch_all(MYSQLI_ASSOC);
    $assigned_parts = array_map(fn($r) => $r['name'], $assigned_rows);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle del checklist</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="lg-theme">
<div class="container py-5">
    <header class="lg-topbar mb-4">
        <div class="lg-brand">
            <span class="lg-dot">LG</span>
            <span class="lg-title">HVAC Service Hub</span>
        </div>
        <nav class="lg-nav">
            <a href="index.php">Checklist</a>
            <a href="parts.php">Repuestos</a>
            <a href="history.php">Historial</a>
            <a href="manage_options.php">Administración</a>
        </nav>
    </header>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div><h1 class="display-6 fw-bold">Detalle del checklist</h1><p class="text-muted">Registro del <?php echo htmlspecialchars($checklist_date->format('d/m/Y'), ENT_QUOTES); ?>.</p></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm lg-card"><div class="card-body"><h2 class="h5">Información</h2><dl class="row mb-0">
                <dt class="col-5">Técnico</dt><dd class="col-7"><?php echo htmlspecialchars($checklist['technician_name'], ENT_QUOTES); ?></dd>
                <dt class="col-5">Van</dt><dd class="col-7"><?php echo htmlspecialchars($checklist['van_name'], ENT_QUOTES); ?></dd>
                <dt class="col-5">Fecha</dt><dd class="col-7"><?php echo htmlspecialchars($checklist_date->format('d/m/Y'), ENT_QUOTES); ?></dd>
                <dt class="col-5">Tipo</dt><dd class="col-7"><?php echo ($checklist['checklist_type'] ?? 'Daily') === 'Weekly' ? 'Semanal' : 'Diario'; ?></dd>
                <dt class="col-5">Creado</dt><dd class="col-7"><?php echo htmlspecialchars($created_at->format('d/m/Y H:i'), ENT_QUOTES); ?></dd>
                <dt class="col-5">Modificado</dt><dd class="col-7"><?php echo count($revisions) > 1 ? 'Sí' : 'No'; ?></dd>
                <dt class="col-5">Combustible</dt><dd class="col-7"><?php echo isset($checklist['fuel_level']) ? (int) $checklist['fuel_level'] . '%' : '-'; ?></dd>
                <dt class="col-5">Aceite</dt><dd class="col-7"><?php echo isset($checklist['oil_level']) ? (int) $checklist['oil_level'] . '%' : '-'; ?></dd>
                <dt class="col-5">Refrigerante</dt><dd class="col-7"><?php echo htmlspecialchars($checklist['refrigerant_level'] ?? '-', ENT_QUOTES); ?></dd>
            </dl></div></div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow-sm lg-card"><div class="card-body"><h2 class="h5">Estado actual</h2>
                <div class="table-responsive"><table class="table align-middle lg-table"><thead><tr><th>Ítem</th><th>Tipo</th><th>Cant.</th><th>Asignado</th><th>Estado</th></tr></thead><tbody>
                <?php foreach ($items as $item) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?></td>
                        <td><?php echo htmlspecialchars($item['item_type'], ENT_QUOTES); ?></td>
                        <td><?php echo $item['quantity'] !== null ? (int) $item['quantity'] : '-'; ?></td>
                        <td><?php if ($item['item_type'] === 'Part' && in_array($item['item_name'], $assigned_parts, true)) { echo '<span class="badge text-bg-info">Asignado</span>'; } elseif ($item['item_type'] === 'Part') { echo 'No'; } else { echo '-'; } ?></td>
                        <td><?php echo (int) $item['is_checked'] === 1 ? '<span class="badge text-bg-success">Llevado</span>' : '<span class="badge text-bg-secondary">No llevado</span>'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
            </div></div>
        </div>
    </div>

    <div class="card shadow-sm mt-4 lg-card">
        <div class="card-body">
            <h2 class="h5">Historial de modificaciones</h2>
            <?php if (empty($revisions)) : ?>
                <p class="text-muted">Sin revisiones guardadas.</p>
            <?php else : ?>
                <div class="history-groups">
                    <?php foreach ($revisions as $revision) : $rid = (int) $revision['id']; $saved = new DateTime($revision['saved_at']); ?>
                        <details class="history-group">
                            <summary>Revisión #<?php echo (int) $revision['revision_number']; ?> · <?php echo htmlspecialchars($saved->format('d/m/Y H:i'), ENT_QUOTES); ?></summary>
                            <div class="group-body">
                                    <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Ítem</th><th>Tipo</th><th>Cant.</th><th>Estado</th></tr></thead><tbody>
                                    <?php foreach (($revision_items[$rid] ?? []) as $rev_item) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($rev_item['item_name'], ENT_QUOTES); ?></td>
                                            <td><?php echo htmlspecialchars($rev_item['item_type'], ENT_QUOTES); ?></td>
                                            <td><?php echo $rev_item['quantity'] !== null ? (int) $rev_item['quantity'] : '-'; ?></td>
                                            <td><?php echo (int) $rev_item['is_checked'] === 1 ? 'Llevado' : 'No llevado'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody></table></div>
                                </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
