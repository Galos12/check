<?php
require_once 'config.php';

$checklist_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($checklist_id <= 0) {
    header('Location: history.php');
    exit;
}

$db = get_db_connection();
$checklist_stmt = $db->prepare('SELECT * FROM checklists WHERE id = ?');
$checklist_stmt->bind_param('i', $checklist_id);
$checklist_stmt->execute();
$checklist_result = $checklist_stmt->get_result();
$checklist = $checklist_result ? $checklist_result->fetch_assoc() : null;

if (!$checklist) {
    header('Location: history.php');
    exit;
}

$item_stmt = $db->prepare('SELECT item_name, item_type, is_checked, quantity FROM checklist_items WHERE checklist_id = ? ORDER BY item_type, item_name');
$item_stmt->bind_param('i', $checklist_id);
$item_stmt->execute();
$item_result = $item_stmt->get_result();
$items = $item_result ? $item_result->fetch_all(MYSQLI_ASSOC) : [];

$checklist_date = new DateTime($checklist['checklist_date']);
$created_at = new DateTime($checklist['created_at']);
$assigned_parts = [];

$technician_name = $checklist['technician_name'];
$technician_stmt = $db->prepare('SELECT id FROM technicians WHERE name = ?');
$technician_stmt->bind_param('s', $technician_name);
$technician_stmt->execute();
$technician_result = $technician_stmt->get_result();
$technician = $technician_result ? $technician_result->fetch_assoc() : null;
if ($technician) {
    $assigned_stmt = $db->prepare(
        'SELECT p.name FROM technician_part_assignments tpa
         JOIN parts_library p ON p.id = tpa.part_id
         WHERE tpa.technician_id = ? AND tpa.assigned_date = ?'
    );
    $technician_id = (int) $technician['id'];
    $assigned_date = $checklist_date->format('Y-m-d');
    $assigned_stmt->bind_param('is', $technician_id, $assigned_date);
    $assigned_stmt->execute();
    $assigned_result = $assigned_stmt->get_result();
    $assigned_rows = $assigned_result ? $assigned_result->fetch_all(MYSQLI_ASSOC) : [];
    $assigned_parts = array_map(fn($row) => $row['name'], $assigned_rows);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle del checklist</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h1 class="display-6 fw-bold">Detalle del checklist</h1>
            <p class="text-muted mb-0">Carga del técnico del <?php echo htmlspecialchars($checklist_date->format('d/m/Y'), ENT_QUOTES); ?>.</p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <a class="btn btn-outline-secondary" href="history.php">Volver al historial</a>
            <a class="btn btn-outline-primary" href="index.php">Nuevo checklist</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Información</h2>
                    <dl class="row mb-0">
                        <dt class="col-5">Técnico</dt>
                        <dd class="col-7"><?php echo htmlspecialchars($checklist['technician_name'], ENT_QUOTES); ?></dd>
                        <dt class="col-5">Van</dt>
                        <dd class="col-7"><?php echo htmlspecialchars($checklist['van_name'], ENT_QUOTES); ?></dd>
                        <dt class="col-5">Fecha</dt>
                        <dd class="col-7"><?php echo htmlspecialchars($checklist_date->format('d/m/Y'), ENT_QUOTES); ?></dd>
                        <dt class="col-5">Tipo</dt>
                        <?php $type_label = ($checklist['checklist_type'] ?? 'Daily') === 'Weekly' ? 'Semanal' : 'Diario'; ?>
                        <dd class="col-7"><?php echo htmlspecialchars($type_label, ENT_QUOTES); ?></dd>
                        <dt class="col-5">Creado</dt>
                        <dd class="col-7"><?php echo htmlspecialchars($created_at->format('d/m/Y'), ENT_QUOTES); ?></dd>
                    </dl>
                    <?php if (!empty($checklist['notes'])) : ?>
                        <hr>
                        <h3 class="h6">Notas</h3>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($checklist['notes'], ENT_QUOTES)); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Herramientas y Repuestos</h2>
                    <?php if (empty($items)) : ?>
                        <p class="text-muted mb-0">No se guardaron ítems en este checklist.</p>
                    <?php else : ?>
                        <div class="table-responsive">
                            <table class="table align-middle">
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
                                    <?php foreach ($items as $item) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?></td>
                                            <td>
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                                    <?php echo htmlspecialchars($item['item_type'], ENT_QUOTES); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $item['quantity'] !== null ? (int) $item['quantity'] : '-'; ?></td>
                                            <td>
                                                <?php if ($item['item_type'] === 'Part') : ?>
                                                    <?php if (in_array($item['item_name'], $assigned_parts, true)) : ?>
                                                        <span class="badge text-bg-info">Asignado</span>
                                                    <?php else : ?>
                                                        <span class="text-muted">No</span>
                                                    <?php endif; ?>
                                                <?php else : ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ((int) $item['is_checked'] === 1) : ?>
                                                    <span class="badge text-bg-success">Llevado</span>
                                                <?php else : ?>
                                                    <span class="badge text-bg-secondary">No llevado</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
