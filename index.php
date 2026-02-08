<?php
$default_items = [
    ['name' => 'Manifold gauge set', 'type' => 'Tool'],
    ['name' => 'Digital multimeter', 'type' => 'Tool'],
    ['name' => 'Vacuum pump', 'type' => 'Tool'],
    ['name' => 'Refrigerant scale', 'type' => 'Tool'],
    ['name' => 'Thermometer probe', 'type' => 'Tool'],
    ['name' => 'Cordless drill', 'type' => 'Tool'],
    ['name' => 'Service wrenches', 'type' => 'Tool'],
    ['name' => 'PVC cutters', 'type' => 'Tool'],
    ['name' => 'Assorted fuses', 'type' => 'Part'],
    ['name' => 'Capacitors', 'type' => 'Part'],
    ['name' => 'Contactors', 'type' => 'Part'],
    ['name' => 'Thermostat batteries', 'type' => 'Part'],
];
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
        <a class="btn btn-outline-primary mt-3 mt-md-0" href="history.php">View Past Checklists</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form id="checklist-form" action="save_checklist.php" method="post">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="technician_name">Technician Name</label>
                        <input class="form-control" id="technician_name" name="technician_name" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="van_name">Van Name / Number</label>
                        <input class="form-control" id="van_name" name="van_name" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="checklist_date">Checklist Date</label>
                        <input class="form-control" id="checklist_date" name="checklist_date" type="date" required>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h5 mb-0">Tools & Parts</h2>
                        <small class="text-muted">Check items loaded today. Default items stay on the list; extras can be added or removed.</small>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#add-item-panel" aria-expanded="false" aria-controls="add-item-panel">
                        Add Extra Item
                    </button>
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

                <div class="row" id="items-container">
                    <?php foreach ($default_items as $index => $item): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="form-check checklist-item p-3 border rounded">
                                <input class="form-check-input" type="checkbox" id="item-<?php echo $index; ?>" name="items[<?php echo $index; ?>][checked]" value="1">
                                <input type="hidden" name="items[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>">
                                <input type="hidden" name="items[<?php echo $index; ?>][type]" value="<?php echo htmlspecialchars($item['type'], ENT_QUOTES); ?>">
                                <label class="form-check-label fw-semibold" for="item-<?php echo $index; ?>">
                                    <?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis ms-2"><?php echo htmlspecialchars($item['type'], ENT_QUOTES); ?></span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
