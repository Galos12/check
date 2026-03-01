<?php
require_once 'config.php';

$db = get_db_connection();
$technicians_result = $db->query('SELECT name FROM technicians ORDER BY name');
if ($technicians_result === false) {
    throw new RuntimeException($db->error);
}
$technicians = $technicians_result->fetch_all(MYSQLI_ASSOC);

$vans_result = $db->query('SELECT name FROM vans ORDER BY name');
if ($vans_result === false) {
    throw new RuntimeException($db->error);
}
$vans = $vans_result->fetch_all(MYSQLI_ASSOC);

$filters = [
    'technician' => trim($_GET['technician'] ?? ''),
    'van' => trim($_GET['van'] ?? ''),
    'date' => trim($_GET['date'] ?? ''),
];

$query = 'SELECT id, technician_name, van_name, checklist_date, checklist_type, created_at FROM checklists';
$where = [];
$params = [];
$types = '';

if ($filters['technician'] !== '') {
    $where[] = 'technician_name = ?';
    $params[] = $filters['technician'];
    $types .= 's';
}

if ($filters['van'] !== '') {
    $where[] = 'van_name = ?';
    $params[] = $filters['van'];
    $types .= 's';
}

if ($filters['date'] !== '') {
    $date = DateTime::createFromFormat('d/m/Y', $filters['date']);
    if ($date) {
        $where[] = 'checklist_date = ?';
        $params[] = $date->format('Y-m-d');
        $types .= 's';
    }
}

if ($where) {
    $query .= ' WHERE ' . implode(' AND ', $where);
}

$query .= ' ORDER BY checklist_date DESC, created_at DESC';
$stmt = $db->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$checklists = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$grouped_checklists = [];

foreach ($checklists as $checklist) {
    $date = new DateTime($checklist['checklist_date']);
    $group_key = $date->format('F Y');
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
            <h1 class="display-6 fw-bold">Historial de checklists</h1>
            <p class="text-muted mb-0">Revisa cargas anteriores de herramientas y repuestos.</p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <a class="btn btn-outline-secondary" href="parts.php">Repuestos</a>
            <a class="btn btn-outline-secondary" href="manage_options.php">Administrar opciones</a>
            <a class="btn btn-outline-primary" href="index.php">Crear checklist</a>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form class="row g-3 align-items-end" method="get">
                <div class="col-md-4">
                    <label class="form-label" for="technician">Técnico</label>
                    <select class="form-select" id="technician" name="technician">
                        <option value="">Todos los técnicos</option>
                        <?php foreach ($technicians as $technician) : ?>
                            <option value="<?php echo htmlspecialchars($technician['name'], ENT_QUOTES); ?>" <?php echo $filters['technician'] === $technician['name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($technician['name'], ENT_QUOTES); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="van">Van</label>
                    <select class="form-select" id="van" name="van">
                        <option value="">Todas las vans</option>
                        <?php foreach ($vans as $van) : ?>
                            <option value="<?php echo htmlspecialchars($van['name'], ENT_QUOTES); ?>" <?php echo $filters['van'] === $van['name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($van['name'], ENT_QUOTES); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="date">Fecha del checklist (DD/MM/AAAA)</label>
                    <input class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($filters['date'], ENT_QUOTES); ?>" placeholder="DD/MM/AAAA">
                </div>
                <div class="col-md-1 d-grid">
                    <button class="btn btn-primary" type="submit">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($checklists)) : ?>
                <p class="text-muted mb-0">Aún no hay checklists guardados.</p>
            <?php else : ?>
                <?php if ($has_filters) : ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Técnico</th>
                                    <th>Van</th>
                                    <th>Creado</th>
                                    <th>Tipo</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($checklists as $checklist) : ?>
                                    <?php
                                    $date = new DateTime($checklist['checklist_date']);
                                    $created = new DateTime($checklist['created_at']);
                                    $type_label = $checklist['checklist_type'] ?? 'Daily';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($date->format('d/m/Y'), ENT_QUOTES); ?></td>
                                        <td><?php echo htmlspecialchars($checklist['technician_name'], ENT_QUOTES); ?></td>
                                        <td><?php echo htmlspecialchars($checklist['van_name'], ENT_QUOTES); ?></td>
                                        <td><?php echo htmlspecialchars($created->format('d/m/Y'), ENT_QUOTES); ?></td>
                                        <td><?php echo htmlspecialchars($type_label === 'Weekly' ? 'Semanal' : 'Diario', ENT_QUOTES); ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-secondary" href="view_checklist.php?id=<?php echo (int) $checklist['id']; ?>">Ver</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <div class="accordion" id="checklist-history">
                        <?php foreach ($grouped_checklists as $group_label => $group_items) : ?>
                            <?php $group_id = 'group-' . preg_replace('/\\s+/', '-', strtolower($group_label)); ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="<?php echo $group_id; ?>-heading">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $group_id; ?>" aria-expanded="false" aria-controls="<?php echo $group_id; ?>">
                                        <?php echo htmlspecialchars($group_label, ENT_QUOTES); ?>
                                    </button>
                                </h2>
                                <div id="<?php echo $group_id; ?>" class="accordion-collapse collapse" aria-labelledby="<?php echo $group_id; ?>-heading" data-bs-parent="#checklist-history">
                                    <div class="accordion-body">
                                        <div class="table-responsive">
                                            <table class="table align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Fecha</th>
                                                        <th>Técnico</th>
                                                        <th>Van</th>
                                                        <th>Creado</th>
                                                        <th>Tipo</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($group_items as $checklist) : ?>
                                                        <?php
                                                        $date = new DateTime($checklist['checklist_date']);
                                                        $created = new DateTime($checklist['created_at']);
                                                        $type_label = $checklist['checklist_type'] ?? 'Daily';
                                                        ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($date->format('d/m/Y'), ENT_QUOTES); ?></td>
                                                            <td><?php echo htmlspecialchars($checklist['technician_name'], ENT_QUOTES); ?></td>
                                                            <td><?php echo htmlspecialchars($checklist['van_name'], ENT_QUOTES); ?></td>
                                                            <td><?php echo htmlspecialchars($created->format('d/m/Y'), ENT_QUOTES); ?></td>
                                                            <td><?php echo htmlspecialchars($type_label === 'Weekly' ? 'Semanal' : 'Diario', ENT_QUOTES); ?></td>
                                                            <td class="text-end">
                                                                <a class="btn btn-sm btn-outline-secondary" href="view_checklist.php?id=<?php echo (int) $checklist['id']; ?>">Ver</a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
