<?php
require_once 'config.php';

$checklist_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($checklist_id <= 0) {
    header('Location: history.php');
    exit;
}

$db = get_db_connection();
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light lg-theme">
<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div><h1 class="display-6 fw-bold">Detalle del checklist</h1><p class="text-muted mb-0">Registro del <?php echo htmlspecialchars($checklist_date->format('d/m/Y'), ENT_QUOTES); ?>.</p></div>
        <div class="d-flex gap-2 mt-3 mt-md-0"><a class="btn btn-outline-secondary" href="history.php">Volver</a><a class="btn btn-lg-primary" href="index.php?technician=<?php echo urlencode($checklist['technician_name']); ?>&van=<?php echo urlencode($checklist['van_name']); ?>&date=<?php echo urlencode($checklist_date->format('d/m/Y')); ?>">Reabrir checklist</a></div>
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
                <p class="text-muted mb-0">Sin revisiones guardadas.</p>
            <?php else : ?>
                <div class="accordion" id="revisionAccordion">
                    <?php foreach ($revisions as $revision) : $rid = (int) $revision['id']; $saved = new DateTime($revision['saved_at']); ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="rev-<?php echo $rid; ?>-h">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rev-<?php echo $rid; ?>">
                                    Revisión #<?php echo (int) $revision['revision_number']; ?> · <?php echo htmlspecialchars($saved->format('d/m/Y H:i'), ENT_QUOTES); ?>
                                </button>
                            </h2>
                            <div id="rev-<?php echo $rid; ?>" class="accordion-collapse collapse" data-bs-parent="#revisionAccordion">
                                <div class="accordion-body">
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
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
