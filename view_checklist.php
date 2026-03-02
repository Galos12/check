<?php
require_once 'config.php';

$current_page = 'history';

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
$items_current = ($item_stmt->get_result())->fetch_all(MYSQLI_ASSOC);

$revision_stmt = $db->prepare('SELECT id, revision_number, saved_at FROM checklist_revisions WHERE checklist_id = ? ORDER BY revision_number DESC');
$revision_stmt->bind_param('i', $checklist_id);
$revision_stmt->execute();
$revisions = ($revision_stmt->get_result())->fetch_all(MYSQLI_ASSOC);

$revision_items = [];
if ($revisions) {
    $ids = array_map(static fn($r) => (int) $r['id'], $revisions);
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

$selected_revision_id = isset($_GET['rev']) ? (int) $_GET['rev'] : 0;
$selected_revision_index = 0;
if ($revisions) {
    if ($selected_revision_id <= 0) {
        $selected_revision_id = (int) $revisions[0]['id'];
    }
    foreach ($revisions as $index => $revision) {
        if ((int) $revision['id'] === $selected_revision_id) {
            $selected_revision_index = $index;
            break;
        }
    }
}

$selected_revision = $revisions[$selected_revision_index] ?? null;
$display_items = $selected_revision ? ($revision_items[(int) $selected_revision['id']] ?? $items_current) : $items_current;

$prev_revision = $revisions[$selected_revision_index + 1] ?? null; // older
$next_revision = $selected_revision_index > 0 ? $revisions[$selected_revision_index - 1] : null; // newer

$checklist_date = new DateTime($checklist['checklist_date']);
$created_at = new DateTime($checklist['created_at']);

$revision_badge = 'Original';
$revision_time = $created_at->format('d/m/Y H:i');
if ($selected_revision) {
    $revision_number = (int) $selected_revision['revision_number'];
    $revision_badge = $revision_number <= 1 ? 'Original' : 'Modificación #' . $revision_number;
    $revision_time = (new DateTime($selected_revision['saved_at']))->format('d/m/Y H:i');
}

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
    $assigned_parts = array_map(static fn($r) => $r['name'], $assigned_rows);
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
            <a class="<?php echo $current_page === 'index' ? 'active' : ''; ?>" href="index.php">Checklist</a>
            <a class="<?php echo $current_page === 'parts' ? 'active' : ''; ?>" href="parts.php">Repuestos</a>
            <a class="<?php echo $current_page === 'history' ? 'active' : ''; ?>" href="history.php">Historial</a>
            <a class="<?php echo $current_page === 'manage_options' ? 'active' : ''; ?>" href="manage_options.php">Administración</a>
        </nav>
    </header>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h1 class="display-6 fw-bold">Detalle del checklist</h1>
            <p class="text-muted">Registro del <?php echo htmlspecialchars($checklist_date->format('d/m/Y'), ENT_QUOTES); ?>.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm lg-card">
                <div class="card-body">
                    <h2 class="h5">Información</h2>
                    <h3 class="h6 info-subtitle">Datos del checklist</h3>
                    <dl class="row mb-0 info-grid">
                        <dt>Técnico</dt><dd><?php echo htmlspecialchars($checklist['technician_name'], ENT_QUOTES); ?></dd>
                        <dt>Fecha</dt><dd><?php echo htmlspecialchars($checklist_date->format('d/m/Y'), ENT_QUOTES); ?></dd>
                        <dt>Tipo</dt><dd><?php echo ($checklist['checklist_type'] ?? 'Daily') === 'Weekly' ? 'Semanal' : 'Diario'; ?></dd>
                        <dt>Creado</dt><dd><?php echo htmlspecialchars($created_at->format('d/m/Y H:i'), ENT_QUOTES); ?></dd>
                    </dl>

                    <h3 class="h6 info-subtitle info-subtitle-van">Información de la van</h3>
                    <dl class="row mb-0 info-grid">
                        <dt>Van</dt><dd><?php echo htmlspecialchars($checklist['van_name'], ENT_QUOTES); ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm lg-card">
                <div class="card-body">
                    <div class="estado-header">
                        <h2 class="h5 mb-0">Estado actual</h2>
                    </div>
                    <div class="revision-bar">
                        <a class="revision-arrow <?php echo $prev_revision ? '' : 'is-disabled'; ?>" href="<?php echo $prev_revision ? 'view_checklist.php?id=' . $checklist_id . '&rev=' . (int) $prev_revision['id'] : '#'; ?>" aria-disabled="<?php echo $prev_revision ? 'false' : 'true'; ?>" title="Revisión anterior">←</a>
                        <span class="revision-meta"><?php echo htmlspecialchars($revision_badge, ENT_QUOTES); ?> · <?php echo htmlspecialchars($revision_time, ENT_QUOTES); ?></span>
                        <a class="revision-arrow <?php echo $next_revision ? '' : 'is-disabled'; ?>" href="<?php echo $next_revision ? 'view_checklist.php?id=' . $checklist_id . '&rev=' . (int) $next_revision['id'] : '#'; ?>" aria-disabled="<?php echo $next_revision ? 'false' : 'true'; ?>" title="Revisión siguiente">→</a>
                    </div>

                    <div class="estado-extra">
                        <span class="badge text-bg-info">Combustible: <?php echo isset($checklist['fuel_level']) ? (int) $checklist['fuel_level'] . '%' : '-'; ?></span>
                        <span class="badge text-bg-info">Aceite: <?php echo isset($checklist['oil_level']) ? (int) $checklist['oil_level'] . '%' : '-'; ?></span>
                        <span class="badge text-bg-info">Refrigerante: <?php echo htmlspecialchars($checklist['refrigerant_level'] ?? '-', ENT_QUOTES); ?></span>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle lg-table">
                            <thead>
                                <tr>
                                    <th>Ítem</th>
                                    <th>Tipo</th>
                                    <th>Cant.</th>
                                    <th>Asignado</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($display_items as $item) : ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?></td>
                                        <td><?php echo htmlspecialchars($item['item_type'], ENT_QUOTES); ?></td>
                                        <td><?php echo $item['quantity'] !== null ? (int) $item['quantity'] : '-'; ?></td>
                                        <td>
                                            <?php if ($item['item_type'] === 'Part' && in_array($item['item_name'], $assigned_parts, true)) : ?>
                                                <span class="badge text-bg-info">Asignado</span>
                                            <?php elseif ($item['item_type'] === 'Part') : ?>
                                                No
                                            <?php else : ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo (int) $item['is_checked'] === 1 ? '<span class="badge text-bg-success">Llevado</span>' : '<span class="badge text-bg-secondary">No llevado</span>'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
