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
$existing_checklist = null;
$existing_items_map = [];
$existing_part_names = [];
$selected_technician = $_GET['technician'] ?? '';
$selected_van = $_GET['van'] ?? '';
$selected_date_display = $_GET['date'] ?? '';

try {
    $db = get_db_connection();
    $db->query('ALTER TABLE checklists ADD COLUMN IF NOT EXISTS fuel_level TINYINT UNSIGNED NULL');
    $db->query('ALTER TABLE checklists ADD COLUMN IF NOT EXISTS oil_level TINYINT UNSIGNED NULL');
    $db->query("ALTER TABLE checklists ADD COLUMN IF NOT EXISTS refrigerant_level ENUM('Low','Mid','Full') NULL");
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


    if ($selected_technician !== '' && $selected_van !== '' && $selected_date_display !== '') {
        $date = DateTime::createFromFormat('d/m/Y', $selected_date_display);
        if ($date) {
            $search_date = $date->format('Y-m-d');
            $existing_stmt = $db->prepare('SELECT id, notes FROM checklists WHERE technician_name = ? AND van_name = ? AND checklist_date = ? AND checklist_type = "Daily" ORDER BY created_at DESC LIMIT 1');
            $existing_stmt->bind_param('sss', $selected_technician, $selected_van, $search_date);
            $existing_stmt->execute();
            $existing_result = $existing_stmt->get_result();
            $existing_checklist = $existing_result ? $existing_result->fetch_assoc() : null;

            if ($existing_checklist) {
                $items_stmt = $db->prepare('SELECT item_name, item_type, is_checked, quantity FROM checklist_items WHERE checklist_id = ?');
                $existing_id = (int) $existing_checklist['id'];
                $items_stmt->bind_param('i', $existing_id);
                $items_stmt->execute();
                $items_result = $items_stmt->get_result();
                $existing_rows = $items_result ? $items_result->fetch_all(MYSQLI_ASSOC) : [];
                foreach ($existing_rows as $row) {
                    $key = strtolower(trim($row['item_type'] . '|' . $row['item_name']));
                    $existing_items_map[$key] = [
                        'checked' => (int) $row['is_checked'] === 1,
                        'quantity' => $row['quantity'] !== null ? (int) $row['quantity'] : null,
                    ];
                    if ($row['item_type'] === 'Part') {
                        $existing_part_names[] = $row['item_name'];
                    }
                }
            }
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
$existing_fuel_level = $existing_checklist ? (int) ($existing_checklist['fuel_level'] ?? 0) : 50;
$existing_oil_level = $existing_checklist ? (int) ($existing_checklist['oil_level'] ?? 0) : 50;
$existing_refrigerant_level = $existing_checklist['refrigerant_level'] ?? 'Mid';
$parts_to_show = $show_checklist ? array_values(array_unique(array_merge($assigned_parts, $existing_part_names))) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lista de Verificación HVAC</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="lg-theme">
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
                        <input type="hidden" name="existing_checklist_id" value="<?php echo $existing_checklist ? (int) $existing_checklist['id'] : 0; ?>">
                    </div>
                </div>

                <?php if ($existing_checklist) : ?>
                    <div class="alert alert-info mt-3 mb-0">Checklist diario ya guardado para esta fecha. Puedes reabrirlo y volver a guardar para actualizarlo.</div>
                <?php endif; ?>


                <section class="van-check-section">
                    <div class="section-header">
                        <button class="btn btn-outline-secondary" type="button" id="open-van-guide">¿Cómo revisar la van?</button>
                        <h2 class="h5 mb-0">Inspección rápida de van</h2>
                    </div>
                    <div class="van-gauges-grid">
                        <div class="gauge-card">
                            <h3>Combustible</h3>
                            <div class="quarter-gauge" data-gauge="fuel">
                                <svg viewBox="0 0 220 130" class="gauge-svg">
                                    <path d="M20 110 A90 90 0 0 1 200 110" class="gauge-arc"></path>
                                    <line x1="110" y1="110" x2="170" y2="60" class="gauge-needle" id="fuel-needle"></line>
                                    <circle cx="110" cy="110" r="6" class="gauge-center"></circle>
                                </svg>
                                <input type="range" min="0" max="100" value="<?php echo $existing_fuel_level; ?>" id="fuel-slider" name="fuel_level">
                                <div class="gauge-value"><span id="fuel-value"><?php echo $existing_fuel_level; ?></span>%</div>
                            </div>
                        </div>
                        <div class="gauge-card">
                            <h3>Aceite</h3>
                            <div class="quarter-gauge" data-gauge="oil">
                                <svg viewBox="0 0 220 130" class="gauge-svg">
                                    <path d="M20 110 A90 90 0 0 1 200 110" class="gauge-arc oil"></path>
                                    <line x1="110" y1="110" x2="170" y2="60" class="gauge-needle" id="oil-needle"></line>
                                    <circle cx="110" cy="110" r="6" class="gauge-center"></circle>
                                </svg>
                                <input type="range" min="0" max="100" value="<?php echo $existing_oil_level; ?>" id="oil-slider" name="oil_level">
                                <div class="gauge-value"><span id="oil-value"><?php echo $existing_oil_level; ?></span>%</div>
                            </div>
                        </div>
                        <div class="gauge-card">
                            <h3>Refrigerante</h3>
                            <label for="refrigerant_level" class="form-label">Nivel</label>
                            <select id="refrigerant_level" name="refrigerant_level" class="form-select">
                                <option value="Low" <?php echo $existing_refrigerant_level === 'Low' ? 'selected' : ''; ?>>Bajo</option>
                                <option value="Mid" <?php echo $existing_refrigerant_level === 'Mid' ? 'selected' : ''; ?>>Medio</option>
                                <option value="Full" <?php echo $existing_refrigerant_level === 'Full' ? 'selected' : ''; ?>>Lleno</option>
                            </select>
                        </div>
                    </div>
                </section>

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
                        <button class="btn btn-sm btn-outline-secondary" type="button" id="toggle-add-item-panel">
                            Agregar extra
                        </button>
                    </div>
                </div>

                <div class="mb-3 hidden" id="add-item-panel">
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
                                                            <?php $tool_key = strtolower('tool|' . $tool['name']); $tool_state = $existing_items_map[$tool_key] ?? null; ?>
                                                            <input class="form-check-input" type="checkbox" id="tool-<?php echo $index; ?>" name="items[<?php echo $index; ?>][checked]" value="1" <?php echo ($tool_state && $tool_state['checked']) ? 'checked' : ''; ?>>
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
                                                                <input class="form-control form-control-sm quantity-input" type="number" min="0" name="items[<?php echo $index; ?>][quantity]" placeholder="0" value="<?php echo ($tool_state && $tool_state['quantity'] !== null) ? (int) $tool_state['quantity'] : ''; ?>">
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
                                                            <?php $part_key = strtolower('part|' . $part); $part_state = $existing_items_map[$part_key] ?? null; ?>
                                                            <input class="form-check-input" type="checkbox" id="part-<?php echo $item_index; ?>" name="items[<?php echo $item_index; ?>][checked]" value="1" <?php echo ($part_state && $part_state['checked']) ? 'checked' : ''; ?>>
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
                    <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Notas opcionales sobre la carga"><?php echo htmlspecialchars($existing_checklist['notes'] ?? '', ENT_QUOTES); ?></textarea>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button class="btn btn-success" type="submit">Guardar lista</button>
                    <button class="btn btn-outline-secondary" type="reset">Limpiar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="van-guide-modal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="van-guide-title">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="van-guide-title">Cómo revisar la van</h2>
            <button type="button" class="btn btn-outline-secondary" id="close-van-guide">Cerrar</button>
        </div>
        <div class="guide-grid">
            <figure><img src="assets/guide/fuel.svg" alt="Guía combustible"><figcaption>1) Enciende contacto y ajusta el indicador según tablero.</figcaption></figure>
            <figure><img src="assets/guide/oil.svg" alt="Guía aceite"><figcaption>2) Verifica varilla/indicador y marca el nivel real.</figcaption></figure>
            <figure><img src="assets/guide/refrigerant.svg" alt="Guía refrigerante"><figcaption>3) Selecciona Bajo, Medio o Lleno según lectura.</figcaption></figure>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="script.js"></script>
</body>
</html>
