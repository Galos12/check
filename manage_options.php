<?php
require_once 'config.php';

$message = '';

try {
    $db = get_db_connection();
} catch (Throwable $error) {
    http_response_code(500);
    echo 'Unable to connect to the database.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $value = trim($_POST['value'] ?? '');

    $action_map = [
        'add_technician' => ['table' => 'technicians', 'column' => 'name'],
        'remove_technician' => ['table' => 'technicians', 'column' => 'name'],
        'add_van' => ['table' => 'vans', 'column' => 'name'],
        'remove_van' => ['table' => 'vans', 'column' => 'name'],
        'add_part' => ['table' => 'parts_library', 'column' => 'name'],
        'remove_part' => ['table' => 'parts_library', 'column' => 'name'],
        'add_tool' => ['table' => 'tools_library', 'column' => 'name'],
        'remove_tool' => ['table' => 'tools_library', 'column' => 'name'],
    ];

    if (isset($_POST['remove_tool'])) {
        $tool_id = (int) $_POST['remove_tool'];
        if ($tool_id > 0) {
            $stmt = $db->prepare('DELETE FROM tools_library WHERE id = :id');
            $stmt->execute([':id' => $tool_id]);
            $message = 'Herramienta eliminada.';
        }
    } elseif ($action === 'assign_parts') {
        $technician_id = (int) ($_POST['technician_id'] ?? 0);
        $assigned_date = $_POST['assigned_date'] ?? '';
        $part_ids = $_POST['part_ids'] ?? [];

        $date = DateTime::createFromFormat('d/m/Y', $assigned_date);
        if ($technician_id > 0 && $date) {
            $db->beginTransaction();
            $delete_stmt = $db->prepare(
                'DELETE FROM technician_part_assignments
                 WHERE technician_id = :technician_id AND assigned_date = :assigned_date'
            );
            $delete_stmt->execute([
                ':technician_id' => $technician_id,
                ':assigned_date' => $date->format('Y-m-d'),
            ]);
            $stmt = $db->prepare(
                'INSERT INTO technician_part_assignments (technician_id, part_id, assigned_date)
                 VALUES (:technician_id, :part_id, :assigned_date)'
            );
            foreach ($part_ids as $part_id) {
                $stmt->execute([
                    ':technician_id' => $technician_id,
                    ':part_id' => (int) $part_id,
                    ':assigned_date' => $date->format('Y-m-d'),
                ]);
            }
            $db->commit();
            $message = 'Repuestos asignados.';
        }
    } elseif ($action === 'update_tool_categories') {
        $tool_ids = array_map('intval', $_POST['tool_ids'] ?? []);
        $daily_ids = array_map('intval', $_POST['daily_tool_ids'] ?? []);
        $weekly_ids = array_map('intval', $_POST['weekly_tool_ids'] ?? []);
        if ($tool_ids) {
            $stmt = $db->prepare('UPDATE tools_library SET is_daily = :is_daily, is_weekly = :is_weekly WHERE id = :id');
            foreach ($tool_ids as $tool_id) {
                $stmt->execute([
                    ':is_daily' => in_array($tool_id, $daily_ids, true) ? 1 : 0,
                    ':is_weekly' => in_array($tool_id, $weekly_ids, true) ? 1 : 0,
                    ':id' => $tool_id,
                ]);
            }
            $message = 'Lista de herramientas actualizada.';
        }
    } elseif ($value !== '' && isset($action_map[$action])) {
        $table = $action_map[$action]['table'];
        $column = $action_map[$action]['column'];

        if (str_starts_with($action, 'add_')) {
            $has_counter = isset($_POST['has_counter']) ? 1 : 0;
            if ($action === 'add_tool') {
                $stmt = $db->prepare("INSERT IGNORE INTO {$table} ({$column}, has_counter, is_daily, is_weekly) VALUES (:value, :has_counter, 0, 0)");
                $stmt->execute([
                    ':value' => $value,
                    ':has_counter' => $has_counter,
                ]);
            } else {
                $stmt = $db->prepare("INSERT IGNORE INTO {$table} ({$column}) VALUES (:value)");
                $stmt->execute([':value' => $value]);
            }
            $message = 'Guardado.';
        } elseif (str_starts_with($action, 'remove_')) {
            $stmt = $db->prepare("DELETE FROM {$table} WHERE {$column} = :value");
            $stmt->execute([':value' => $value]);
            $message = 'Eliminado.';
        }
    }
}

$technicians = $db->query('SELECT id, name FROM technicians ORDER BY name')->fetchAll();
$vans = $db->query('SELECT name FROM vans ORDER BY name')->fetchAll();
$parts = $db->query('SELECT id, name FROM parts_library ORDER BY name')->fetchAll();
$tools = $db->query('SELECT id, name, has_counter, is_daily, is_weekly FROM tools_library ORDER BY name')->fetchAll();
$assignments_raw = $db->query(
    'SELECT tpa.technician_id, tpa.assigned_date, p.id AS part_id, p.name AS part_name
     FROM technician_part_assignments tpa
     JOIN parts_library p ON p.id = tpa.part_id
     ORDER BY tpa.assigned_date DESC, p.name'
)->fetchAll();

$assignments = [];
foreach ($assignments_raw as $row) {
    $tech_id = (int) $row['technician_id'];
    $date_key = (new DateTime($row['assigned_date']))->format('d/m/Y');
    $assignments[$tech_id]['dates'][$date_key][] = [
        'id' => (int) $row['part_id'],
        'name' => $row['part_name'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrar opciones de checklist</title>
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
            <h1 class="display-6 fw-bold">Administrar opciones del checklist</h1>
            <p class="text-muted mb-0">Actualiza técnicos, vans, repuestos y herramientas disponibles.</p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <a class="btn btn-outline-secondary" href="index.php">Volver al checklist</a>
            <a class="btn btn-outline-primary" href="history.php">Ver historial</a>
        </div>
    </div>

    <?php if ($message !== '') : ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Técnicos</h2>
                    <form class="d-flex gap-2 mb-3" method="post">
                        <input type="hidden" name="action" value="add_technician">
                        <input class="form-control" name="value" placeholder="Agregar técnico" required>
                        <button class="btn btn-primary" type="submit">Agregar</button>
                    </form>
                    <ul class="list-group scroll-list">
                        <?php foreach ($technicians as $technician) : ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <button class="btn btn-link p-0 text-decoration-none" type="button" data-bs-toggle="modal" data-bs-target="#assignPartsModal" data-tech-id="<?php echo (int) $technician['id']; ?>" data-tech-name="<?php echo htmlspecialchars($technician['name'], ENT_QUOTES); ?>">
                                    <?php echo htmlspecialchars($technician['name'], ENT_QUOTES); ?>
                                </button>
                                <form method="post">
                                    <input type="hidden" name="action" value="remove_technician">
                                    <input type="hidden" name="value" value="<?php echo htmlspecialchars($technician['name'], ENT_QUOTES); ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Quitar</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <small class="text-muted d-block mt-2">Haz clic en un técnico para asignar repuestos por fecha.</small>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Vans</h2>
                    <form class="d-flex gap-2 mb-3" method="post">
                        <input type="hidden" name="action" value="add_van">
                        <input class="form-control" name="value" placeholder="Agregar van" required>
                        <button class="btn btn-primary" type="submit">Agregar</button>
                    </form>
                    <ul class="list-group scroll-list">
                        <?php foreach ($vans as $van) : ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($van['name'], ENT_QUOTES); ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="remove_van">
                                    <input type="hidden" name="value" value="<?php echo htmlspecialchars($van['name'], ENT_QUOTES); ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Quitar</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Repuestos diarios</h2>
                    <form class="d-flex gap-2 mb-3" method="post">
                        <input type="hidden" name="action" value="add_part">
                        <input class="form-control" name="value" placeholder="Agregar repuesto" required>
                        <button class="btn btn-primary" type="submit">Agregar</button>
                    </form>
                    <ul class="list-group scroll-list">
                        <?php foreach ($parts as $part) : ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($part['name'], ENT_QUOTES); ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="remove_part">
                                    <input type="hidden" name="value" value="<?php echo htmlspecialchars($part['name'], ENT_QUOTES); ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Quitar</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Checklist de herramientas</h2>
                    <form class="row g-2 align-items-end mb-3" method="post">
                        <input type="hidden" name="action" value="add_tool">
                        <div class="col-md-6">
                            <label class="form-label" for="tool_name">Nombre de herramienta</label>
                            <input class="form-control" id="tool_name" name="value" placeholder="Agregar herramienta" required>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="tool_has_counter" name="has_counter" value="1">
                                <label class="form-check-label" for="tool_has_counter">Requiere contador</label>
                            </div>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-primary" type="submit">Agregar</button>
                        </div>
                    </form>

                    <form method="post">
                        <input type="hidden" name="action" value="update_tool_categories">
                        <div class="table-responsive">
                            <table class="table align-middle table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Herramienta</th>
                                        <th>Contador</th>
                                        <th>Diario</th>
                                        <th>Semanal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tools as $tool) : ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($tool['name'], ENT_QUOTES); ?>
                                                <input type="hidden" name="tool_ids[]" value="<?php echo (int) $tool['id']; ?>">
                                            </td>
                                            <td>
                                                <?php if ((int) $tool['has_counter'] === 1) : ?>
                                                    <span class="badge text-bg-info">Contador</span>
                                                <?php else : ?>
                                                    <span class="text-muted">No</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <input class="form-check-input" type="checkbox" name="daily_tool_ids[]" value="<?php echo (int) $tool['id']; ?>" <?php echo (int) $tool['is_daily'] === 1 ? 'checked' : ''; ?>>
                                            </td>
                                            <td>
                                                <input class="form-check-input" type="checkbox" name="weekly_tool_ids[]" value="<?php echo (int) $tool['id']; ?>" <?php echo (int) $tool['is_weekly'] === 1 ? 'checked' : ''; ?>>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-danger" type="submit" name="remove_tool" value="<?php echo (int) $tool['id']; ?>" formnovalidate>Quitar</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button class="btn btn-primary" type="submit">Guardar selección</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="assignPartsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Asignar repuestos a <span id="assignTechName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_parts">
                    <input type="hidden" name="technician_id" id="assignTechId">
                    <div class="mb-3">
                        <label class="form-label" for="assigned_date">Fecha de asignación (DD/MM/AAAA)</label>
                        <input class="form-control" id="assigned_date" name="assigned_date" placeholder="DD/MM/AAAA" required>
                    </div>
                    <div class="table-responsive modal-scroll-table">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                <?php foreach ($parts as $part) : ?>
                                    <tr>
                                        <td class="text-center" style="width: 32px;">
                                            <input class="form-check-input assign-part-checkbox" type="checkbox" id="part-<?php echo (int) $part['id']; ?>" name="part_ids[]" value="<?php echo (int) $part['id']; ?>">
                                        </td>
                                        <td>
                                            <label class="form-check-label" for="part-<?php echo (int) $part['id']; ?>">
                                                <?php echo htmlspecialchars($part['name'], ENT_QUOTES); ?>
                                            </label>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        <h6 class="text-uppercase text-muted">Asignaciones previas</h6>
                        <div id="assignmentHistory" class="small text-muted">
                            Selecciona un técnico para ver asignaciones por fecha.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit">Enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
const assignModal = document.getElementById('assignPartsModal');
const assignTechName = document.getElementById('assignTechName');
const assignTechId = document.getElementById('assignTechId');
const assignedDateInput = document.getElementById('assigned_date');
const assignmentHistory = document.getElementById('assignmentHistory');
const assignments = <?php echo json_encode($assignments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

const formatToday = () => {
    const today = new Date();
    const day = String(today.getDate()).padStart(2, '0');
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const year = today.getFullYear();
    return `${day}/${month}/${year}`;
};

const renderHistory = (techId) => {
    const history = assignments[techId]?.dates ?? {};
    const entries = Object.entries(history);
    if (!entries.length) {
        assignmentHistory.textContent = 'Sin asignaciones aún.';
        return;
    }
    assignmentHistory.innerHTML = entries.map(([date, parts]) => {
        const partNames = parts.map((part) => part.name).join(', ');
        return `<div><strong>${date}:</strong> ${partNames}</div>`;
    }).join('');
};

const updateCheckedParts = (techId, dateValue) => {
    const selected = assignments[techId]?.dates?.[dateValue] ?? [];
    const selectedIds = new Set(selected.map((part) => String(part.id)));
    document.querySelectorAll('.assign-part-checkbox').forEach((checkbox) => {
        checkbox.checked = selectedIds.has(checkbox.value);
    });
};

assignModal.addEventListener('show.bs.modal', (event) => {
    const button = event.relatedTarget;
    const techId = button.getAttribute('data-tech-id');
    assignTechName.textContent = button.getAttribute('data-tech-name');
    assignTechId.value = techId;
    assignedDateInput.value = formatToday();
    renderHistory(techId);
    updateCheckedParts(techId, assignedDateInput.value);
});

assignedDateInput.addEventListener('change', () => {
    updateCheckedParts(assignTechId.value, assignedDateInput.value);
});
</script>
</body>
</html>
