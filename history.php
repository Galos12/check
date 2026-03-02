<?php
require_once 'config.php';

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

$technicians_result = $db->query('SELECT name FROM technicians ORDER BY name');
$technicians = $technicians_result ? $technicians_result->fetch_all(MYSQLI_ASSOC) : [];
$vans_result = $db->query('SELECT name FROM vans ORDER BY name');
$vans = $vans_result ? $vans_result->fetch_all(MYSQLI_ASSOC) : [];

$filters = [
    'technician' => trim($_GET['technician'] ?? ''),
    'van' => trim($_GET['van'] ?? ''),
    'date' => trim($_GET['date'] ?? ''),
];

$query = 'SELECT c.id, c.technician_name, c.van_name, c.checklist_date, c.checklist_type, c.created_at, c.fuel_level, c.oil_level, c.refrigerant_level,
          COALESCE(r.revision_count, 0) AS revision_count, r.last_saved_at
          FROM checklists c
          LEFT JOIN (
            SELECT checklist_id, COUNT(*) AS revision_count, MAX(saved_at) AS last_saved_at
            FROM checklist_revisions
            GROUP BY checklist_id
          ) r ON r.checklist_id = c.id';
$where = [];
$params = [];
$types = '';

if ($filters['technician'] !== '') {
    $where[] = 'c.technician_name = ?';
    $params[] = $filters['technician'];
    $types .= 's';
}
if ($filters['van'] !== '') {
    $where[] = 'c.van_name = ?';
    $params[] = $filters['van'];
    $types .= 's';
}
if ($filters['date'] !== '') {
    $date = DateTime::createFromFormat('d/m/Y', $filters['date']);
    if ($date) {
        $where[] = 'c.checklist_date = ?';
        $params[] = $date->format('Y-m-d');
        $types .= 's';
    }
}
if ($where) {
    $query .= ' WHERE ' . implode(' AND ', $where);
}
$query .= ' ORDER BY c.checklist_date DESC, c.created_at DESC';

$stmt = $db->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$checklists = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$grouped_checklists = [];
foreach ($checklists as $checklist) {
    $group_key = (new DateTime($checklist['checklist_date']))->format('F Y');
    $grouped_checklists[$group_key][] = $checklist;
}
$has_filters = $filters['technician'] !== '' || $filters['van'] !== '' || $filters['date'] !== '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Historial de checklists</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="lg-theme">
<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h1 class="display-6 fw-bold">Historial de checklists</h1>
            <p class="text-muted mb-0">Revisa cargas anteriores y detecta modificaciones.</p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <a class="btn btn-outline-secondary" href="parts.php">Repuestos</a>
            <a class="btn btn-outline-secondary" href="manage_options.php">Administrar opciones</a>
            <a class="btn btn-lg-primary" href="index.php">Crear checklist</a>
        </div>
    </div>

    <div class="card shadow-sm mb-4 lg-card">
        <div class="card-body">
            <form class="row g-3 align-items-end" method="get">
                <div class="col-md-4"><label class="form-label" for="technician">Técnico</label><select class="form-select" id="technician" name="technician"><option value="">Todos</option><?php foreach ($technicians as $technician) : ?><option value="<?php echo htmlspecialchars($technician['name'], ENT_QUOTES); ?>" <?php echo $filters['technician'] === $technician['name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($technician['name'], ENT_QUOTES); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label" for="van">Van</label><select class="form-select" id="van" name="van"><option value="">Todas</option><?php foreach ($vans as $van) : ?><option value="<?php echo htmlspecialchars($van['name'], ENT_QUOTES); ?>" <?php echo $filters['van'] === $van['name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($van['name'], ENT_QUOTES); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label" for="date">Fecha (DD/MM/AAAA)</label><input class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($filters['date'], ENT_QUOTES); ?>" placeholder="DD/MM/AAAA"></div>
                <div class="col-md-1 d-grid"><button class="btn btn-lg-primary" type="submit">Filtrar</button></div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm lg-card">
        <div class="card-body">
            <?php if (empty($checklists)) : ?>
                <p class="text-muted mb-0">Aún no hay checklists guardados.</p>
            <?php else : ?>
                <?php $render = function (array $items) { ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 lg-table">
                            <thead><tr><th>Fecha</th><th>Técnico</th><th>Van</th><th>Comb.</th><th>Aceite</th><th>Ref.</th><th>Creado</th><th>Tipo</th><th>Modificado</th><th>Última edición</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($items as $checklist) :
                                $date = new DateTime($checklist['checklist_date']);
                                $created = new DateTime($checklist['created_at']);
                                $modified = ((int) $checklist['revision_count']) > 1;
                                $lastEdited = $checklist['last_saved_at'] ? new DateTime($checklist['last_saved_at']) : null;
                                $typeLabel = ($checklist['checklist_type'] ?? 'Daily') === 'Weekly' ? 'Semanal' : 'Diario';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($date->format('d/m/Y'), ENT_QUOTES); ?></td>
                                    <td><?php echo htmlspecialchars($checklist['technician_name'], ENT_QUOTES); ?></td>
                                    <td><?php echo htmlspecialchars($checklist['van_name'], ENT_QUOTES); ?></td>
                                    <td><?php echo isset($checklist['fuel_level']) ? (int) $checklist['fuel_level'] . '%' : '-'; ?></td>
                                    <td><?php echo isset($checklist['oil_level']) ? (int) $checklist['oil_level'] . '%' : '-'; ?></td>
                                    <td><?php echo htmlspecialchars($checklist['refrigerant_level'] ?? '-', ENT_QUOTES); ?></td>
                                    <td><?php echo htmlspecialchars($created->format('d/m/Y H:i'), ENT_QUOTES); ?></td>
                                    <td><?php echo htmlspecialchars($typeLabel, ENT_QUOTES); ?></td>
                                    <td><?php echo $modified ? '<span class="badge text-bg-warning">Sí</span>' : '<span class="badge text-bg-secondary">No</span>'; ?></td>
                                    <td><?php echo $lastEdited ? htmlspecialchars($lastEdited->format('d/m/Y H:i'), ENT_QUOTES) : '-'; ?></td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="view_checklist.php?id=<?php echo (int) $checklist['id']; ?>">Ver</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php }; ?>

                <?php if ($has_filters) : ?>
                    <?php $render($checklists); ?>
                <?php else : ?>
                    <div class="history-groups">
                        <?php foreach ($grouped_checklists as $group_label => $group_items) : $group_id = 'group-' . preg_replace('/\s+/', '-', strtolower($group_label)); ?>
                            <details class="history-group">
                                <summary><?php echo htmlspecialchars($group_label, ENT_QUOTES); ?></summary><div class="group-body"><?php $render($group_items); ?></div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
