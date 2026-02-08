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
$selected_date_display = $_GET['date'] ?? '';

try {
    $db = get_db_connection();
    $technicians = $db->query('SELECT name FROM technicians ORDER BY name')->fetchAll();
    $vans = $db->query('SELECT name FROM vans ORDER BY name')->fetchAll();
    $parts_rows = $db->query('SELECT name FROM parts_library ORDER BY name')->fetchAll();
    $daily_tools = $db->query("SELECT name, has_counter FROM tools_library WHERE category = 'Daily' ORDER BY name")->fetchAll();
    $weekly_tools = $db->query("SELECT name, has_counter FROM tools_library WHERE category = 'Weekly' ORDER BY name")->fetchAll();
    if (!empty($parts_rows)) {
        $parts = array_map(fn($row) => $row['name'], $parts_rows);
    }

    if ($selected_technician !== '' && $selected_date_display !== '') {
        $date = DateTime::createFromFormat('d/m/Y', $selected_date_display);
        $technician_stmt = $db->prepare('SELECT id FROM technicians WHERE name = :name');
        $technician_stmt->execute([':name' => $selected_technician]);
        $technician = $technician_stmt->fetch();
        if ($date && $technician) {
            $parts_stmt = $db->prepare(
                'SELECT p.name FROM technician_part_assignments tpa
                 JOIN parts_library p ON p.id = tpa.part_id
                 WHERE tpa.technician_id = :technician_id AND tpa.assigned_date = :assigned_date
                 ORDER BY p.name'
            );
            $parts_stmt->execute([
                ':technician_id' => $technician['id'],
                ':assigned_date' => $date->format('Y-m-d'),
            ]);
            $assigned_parts = array_map(fn($row) => $row['name'], $parts_stmt->fetchAll());
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

$all_parts = array_values(array_unique(array_merge($parts, $assigned_parts)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HVAC Van Checklist</title>
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
            <h1 class="display-6 fw-bold">HVAC Service Call Checklist</h1>
            <p class="text-muted mb-0">Track tools and parts technicians take on every van rollout.</p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <a class="btn btn-outline-secondary" href="manage_options.php">Manage Options</a>
            <a class="btn btn-outline-primary" href="history.php">View Past Checklists</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form id="checklist-form" action="save_checklist.php" method="post">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="technician_name">Technician Name</label>
                        <select class="form-select" id="technician_name" name="technician_name" required>
                            <option value="" disabled selected>Select technician</option>
                            <?php foreach ($technicians as $technician) : ?>
                                <option value="<?php echo htmlspecialchars($technician['name'], ENT_QUOTES); ?>" <?php echo $selected_technician === $technician['name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($technician['name'], ENT_QUOTES); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="van_name">Van Name / Number</label>
                        <select class="form-select" id="van_name" name="van_name" required>
                            <option value="" disabled selected>Select van</option>
                            <?php foreach ($vans as $van) : ?>
                                <option value="<?php echo htmlspecialchars($van['name'], ENT_QUOTES); ?>">
                                    <?php echo htmlspecialchars($van['name'], ENT_QUOTES); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="checklist_date_display">Checklist Date (DD/MM/YYYY)</label>
                        <input class="form-control" id="checklist_date_display" name="checklist_date_display" placeholder="DD/MM/YYYY" value="<?php echo htmlspecialchars($selected_date_display, ENT_QUOTES); ?>" required>
                        <input type="hidden" id="checklist_date" name="checklist_date">
                        <input type="hidden" id="checklist_type" name="checklist_type" value="Daily">
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h5 mb-0">Tools & Parts</h2>
                        <small class="text-muted">Check items loaded today. Default items stay on the list; extras can be added or removed.</small>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-sm btn-outline-primary" type="button" id="add-full-tool-list-btn" data-weekly-tools='<?php echo json_encode($weekly_tools, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>'>
                            Add Full Tool List
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#add-item-panel" aria-expanded="false" aria-controls="add-item-panel">
                            Add Extra Item
                        </button>
                    </div>
                </div>

                <div class="collapse mb-3" id="add-item-panel">
                    <div class="card card-body bg-light border">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label" for="new_item_name">Item Name</label>
                                <input class="form-control" id="new_item_name" type="text" placeholder="Example: Extension ladder">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="new_item_type">Type</label>
                                <select class="form-select" id="new_item_type">
                                    <option value="Tool">Tool</option>
                                    <option value="Part">Part</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-grid">
                                <button class="btn btn-primary" type="button" id="add-item-btn">Add</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4" id="items-container">
                    <div class="col-lg-6">
                        <div class="card checklist-card h-100">
                            <div class="card-header bg-white">
                                <h3 class="h6 mb-0 text-uppercase text-muted">Tools</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col">Taken</th>
                                                <th scope="col">Tool</th>
                                                <th scope="col"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="tools-list">
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
                                                            <input class="form-control form-control-sm quantity-input" type="number" min="0" name="items[<?php echo $index; ?>][quantity]" placeholder="Qty">
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card checklist-card h-100">
                            <div class="card-header bg-white">
                                <h3 class="h6 mb-0 text-uppercase text-muted">Parts</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col">Taken</th>
                                                <th scope="col">Part</th>
                                                <th scope="col"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="parts-list">
                                            <?php foreach ($all_parts as $index => $part): ?>
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
                                                            <span class="badge text-bg-info ms-2">Assigned</span>
                                                        <?php endif; ?>
                                                        <input type="hidden" name="items[<?php echo $item_index; ?>][name]" value="<?php echo htmlspecialchars($part, ENT_QUOTES); ?>">
                                                        <input type="hidden" name="items[<?php echo $item_index; ?>][type]" value="Part">
                                                    </td>
                                                    <td></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="form-label" for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Optional notes about the loadout"></textarea>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button class="btn btn-success" type="submit">Save Checklist</button>
                    <button class="btn btn-outline-secondary" type="reset">Clear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="script.js"></script>
</body>
</html>
