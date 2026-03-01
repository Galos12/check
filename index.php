<?php
require_once 'config.php';

$fallback_daily_tools = [
    'Manifold gauge set',
    'Digital multimeter',
    'Vacuum pump',
    'Refrigerant scale',
    'Thermometer probe',
    'Cordless drill',
    'Service wrenches',
    'PVC cutters',
];

$fallback_weekly_tools = [
    'Adjustable wrench set',
    'Allen key set',
    'Cable ties',
    'Caulking gun',
    'Circuit tester',
    'Cordless drill',
    'Crimping tool',
    'Digital multimeter',
    'Duct tape',
    'Extension ladder',
    'Extension cords',
    'Flashlight',
    'Handheld vacuum',
    'Hose clamp assortment',
    'Infrared thermometer',
    'Insulated screwdrivers',
    'Level',
    'Manifold gauge set',
    'Nut drivers',
    'Pliers set',
    'PVC cutters',
    'Recovery machine',
    'Refrigerant scale',
    'R-22 refrigerant',
    'R-410A refrigerant',
    'Safety goggles',
    'Service wrenches',
    'Sheet metal snips',
    'Step ladder',
    'Tape measure',
    'Thermometer probe',
    'Toolbox',
    'Vacuum pump',
    'Voltage tester',
    'Work gloves',
];

$fallback_parts = [
    'Assorted fuses',
    'Capacitors',
    'Contactors',
    'Thermostat batteries',
];

$technicians = [];
$vans = [];
$parts = $fallback_parts;
$daily_tools = [];
$weekly_tools = [];
$assigned_parts = [];
$selected_technician = $_GET['technician'] ?? '';
$selected_van = $_GET['van'] ?? '';
$selected_date_display = $_GET['date'] ?? '';

try {
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

    $parts_result = $db->query('SELECT name FROM parts_library ORDER BY name');
    if ($parts_result === false) {
        throw new RuntimeException($db->error);
    }
    $parts_rows = $parts_result->fetch_all(MYSQLI_ASSOC);

    $daily_tools_result = $db->query("SELECT name, has_counter FROM tools_library WHERE is_daily = 1 ORDER BY name");
    if ($daily_tools_result === false) {
        throw new RuntimeException($db->error);
    }
    $daily_tools = $daily_tools_result->fetch_all(MYSQLI_ASSOC);

    $weekly_tools_result = $db->query("SELECT name, has_counter FROM tools_library WHERE is_weekly = 1 ORDER BY name");
    if ($weekly_tools_result === false) {
        throw new RuntimeException($db->error);
    }
    $weekly_tools = $weekly_tools_result->fetch_all(MYSQLI_ASSOC);

    if (!empty($parts_rows)) {
        $parts = array_map(fn($row) => $row['name'], $parts_rows);
    }

    if ($selected_technician !== '' && $selected_date_display !== '') {
        $date = DateTime::createFromFormat('d/m/Y', $selected_date_display);
        $technician_stmt = $db->prepare('SELECT id FROM technicians WHERE name = ?');
        $technician_stmt->bind_param('s', $selected_technician);
        $technician_stmt->execute();
        $technician_result = $technician_stmt->get_result();
        $technician = $technician_result ? $technician_result->fetch_assoc() : null;
        if ($date && $technician) {
            $parts_stmt = $db->prepare(
                'SELECT p.name FROM technician_part_assignments tpa
                 JOIN parts_library p ON p.id = tpa.part_id
                 WHERE tpa.technician_id = ? AND tpa.assigned_date = ?
                 ORDER BY p.name'
            );
            $assigned_date = $date->format('Y-m-d');
            $technician_id = (int) $technician['id'];
            $parts_stmt->bind_param('is', $technician_id, $assigned_date);
            $parts_stmt->execute();
            $parts_result = $parts_stmt->get_result();
            $assigned_rows = $parts_result ? $parts_result->fetch_all(MYSQLI_ASSOC) : [];
            $assigned_parts = array_map(fn($row) => $row['name'], $assigned_rows);
        }
    }
} catch (Throwable $error) {
    $parts = $fallback_parts;
    $daily_tools = array_map(fn($name) => ['name' => $name, 'has_counter' => 0], $fallback_daily_tools);
    $weekly_tools = array_map(fn($name) => ['name' => $name, 'has_counter' => 0], $fallback_weekly_tools);
}

if (empty($daily_tools)) {
    $daily_tools = array_map(fn($name) => ['name' => $name, 'has_counter' => 0], $fallback_daily_tools);
}

if (empty($weekly_tools)) {
    $weekly_tools = array_map(fn($name) => ['name' => $name, 'has_counter' => 0], $fallback_weekly_tools);
}

$show_checklist = $selected_technician !== '' && $selected_van !== '';
$parts_to_show = $show_checklist ? array_values(array_unique($assigned_parts)) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lista de Verificación HVAC</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h1 class="display-6 fw-bold">Lista de Verificación de Servicio HVAC</h1>
            <p class="text-muted mb-0">Registra herramientas y repuestos que los técnicos llevan en cada salida.</p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <a class="btn btn-outline-secondary" href="parts.php">Repuestos</a>
            <a class="btn btn-outline-secondary" href="manage_options.php">Administrar opciones</a>
            <a class="btn btn-outline-primary" href="history.php">Ver listas anteriores</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form id="checklist-form" action="save_checklist.php" method="post">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="technician_name">Técnico</label>
                        <select class="form-select" id="technician_name" name="technician_name" required>
                            <option value="" disabled selected>Selecciona técnico</option>
                            <?php foreach ($technicians as $technician) : ?>
                                <option value="<?php echo htmlspecialchars($technician['name'], ENT_QUOTES); ?>" <?php echo $selected_technician === $technician['name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($technician['name'], ENT_QUOTES); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="van_name">Unidad / Número de van</label>
                        <select class="form-select" id="van_name" name="van_name" required>
                            <option value="" disabled selected>Selecciona van</option>
                            <?php foreach ($vans as $van) : ?>
                                <option value="<?php echo htmlspecialchars($van['name'], ENT_QUOTES); ?>" <?php echo $selected_van === $van['name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($van['name'], ENT_QUOTES); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="checklist_date_display">Fecha de lista (DD/MM/AAAA)</label>
                        <input class="form-control" id="checklist_date_display" name="checklist_date_display" placeholder="DD/MM/AAAA" value="<?php echo htmlspecialchars($selected_date_display, ENT_QUOTES); ?>" required>
                        <input type="hidden" id="checklist_date" name="checklist_date">
                        <input type="hidden" id="checklist_type" name="checklist_type" value="Daily">
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h5 mb-0">Herramientas y Repuestos</h2>
                        <small class="text-muted">Marca lo cargado hoy. Los elementos base se mantienen; los extras se pueden agregar o quitar.</small>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-sm btn-outline-primary" type="button" id="add-full-tool-list-btn" data-weekly-tools='<?php echo json_encode($weekly_tools, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>'>
                            Agregar lista semanal
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#add-item-panel" aria-expanded="false" aria-controls="add-item-panel">
                            Agregar extra
                        </button>
                    </div>
                </div>

                <div class="collapse mb-3" id="add-item-panel">
                    <div class="card card-body bg-light border">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label" for="new_item_name">Nombre del ítem</label>
                                <input class="form-control" id="new_item_name" type="text" placeholder="Ejemplo: Escalera">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="new_item_type">Tipo</label>
                                <select class="form-select" id="new_item_type">
                                    <option value="Tool">Herramienta</option>
                                    <option value="Part">Repuesto</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-grid">
                                <button class="btn btn-primary" type="button" id="add-item-btn">Agregar</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4" id="items-container">
                    <div class="col-lg-6">
                        <div class="card checklist-card h-100">
                            <div class="card-header bg-white">
                                <h3 class="h6 mb-0 text-uppercase text-muted">Herramientas</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col">Llevado</th>
                                                <th scope="col">Herramienta</th>
                                                <th scope="col">Cant.</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tools-list">
                                            <tr id="tools-empty" class="<?php echo $show_checklist ? 'd-none' : ''; ?>">
                                                <td colspan="3" class="text-center text-muted py-4">Selecciona técnico y van para ver herramientas.</td>
                                            </tr>
                                            <?php if ($show_checklist) : ?>
                                                <?php foreach ($daily_tools as $index => $tool): ?>
                                                    <tr>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="checkbox" id="tool-<?php echo $index; ?>" name="items[<?php echo $index; ?>][checked]" value="1">
                                                        </td>
                                                        <td>
                                                            <label class="fw-semibold" for="tool-<?php echo $index; ?>">
                                                                <?php echo htmlspecialchars($tool['name'], ENT_QUOTES); ?>
                                                            </label>
                                                            <input type="hidden" name="items[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($tool['name'], ENT_QUOTES); ?>">
                                                            <input type="hidden" name="items[<?php echo $index; ?>][type]" value="Tool">
                                                        </td>
                                                        <td class="text-end">
                                                            <?php if ((int) $tool['has_counter'] === 1) : ?>
                                                                <input class="form-control form-control-sm quantity-input" type="number" min="0" name="items[<?php echo $index; ?>][quantity]" placeholder="0">
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card checklist-card h-100">
                            <div class="card-header bg-white">
                                <h3 class="h6 mb-0 text-uppercase text-muted">Repuestos</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col">Llevado</th>
                                                <th scope="col">Repuesto</th>
                                                <th scope="col"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="parts-list">
                                            <tr id="parts-empty" class="<?php echo $show_checklist ? 'd-none' : ''; ?>">
                                                <td colspan="3" class="text-center text-muted py-4">Selecciona técnico y van para ver repuestos.</td>
                                            </tr>
                                            <?php if ($show_checklist && empty($parts_to_show)) : ?>
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted py-4">No hay repuestos asignados para este técnico/fecha.</td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if ($show_checklist) : ?>
                                                <?php foreach ($parts_to_show as $index => $part): ?>
                                                    <?php $item_index = $index + count($daily_tools); ?>
                                                    <tr>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="checkbox" id="part-<?php echo $item_index; ?>" name="items[<?php echo $item_index; ?>][checked]" value="1">
                                                        </td>
                                                        <td>
                                                            <label class="fw-semibold" for="part-<?php echo $item_index; ?>">
                                                                <?php echo htmlspecialchars($part, ENT_QUOTES); ?>
                                                            </label>
                                                            <?php if (in_array($part, $assigned_parts, true)) : ?>
                                                                <span class="badge text-bg-info ms-2">Asignado</span>
                                                            <?php endif; ?>
                                                            <input type="hidden" name="items[<?php echo $item_index; ?>][name]" value="<?php echo htmlspecialchars($part, ENT_QUOTES); ?>">
                                                            <input type="hidden" name="items[<?php echo $item_index; ?>][type]" value="Part">
                                                        </td>
                                                        <td></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="form-label" for="notes">Notas</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Notas opcionales sobre la carga"></textarea>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button class="btn btn-success" type="submit">Guardar lista</button>
                    <button class="btn btn-outline-secondary" type="reset">Limpiar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="script.js"></script>
</body>
</html>
