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
    ];

    if ($value !== '' && isset($action_map[$action])) {
        $table = $action_map[$action]['table'];
        $column = $action_map[$action]['column'];

        if (str_starts_with($action, 'add_')) {
            $stmt = $db->prepare("INSERT IGNORE INTO {$table} ({$column}) VALUES (:value)");
            $stmt->execute([':value' => $value]);
            $message = 'Saved.';
        } elseif (str_starts_with($action, 'remove_')) {
            $stmt = $db->prepare("DELETE FROM {$table} WHERE {$column} = :value");
            $stmt->execute([':value' => $value]);
            $message = 'Removed.';
        }
    }
}

$technicians = $db->query('SELECT name FROM technicians ORDER BY name')->fetchAll();
$vans = $db->query('SELECT name FROM vans ORDER BY name')->fetchAll();
$parts = $db->query('SELECT name FROM parts_library ORDER BY name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Checklist Options</title>
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
            <h1 class="display-6 fw-bold">Manage Checklist Options</h1>
            <p class="text-muted mb-0">Update technicians, vans, and parts available for daily checklists.</p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <a class="btn btn-outline-secondary" href="index.php">Back to Checklist</a>
            <a class="btn btn-outline-primary" href="history.php">View History</a>
        </div>
    </div>

    <?php if ($message !== '') : ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Technicians</h2>
                    <form class="d-flex gap-2 mb-3" method="post">
                        <input type="hidden" name="action" value="add_technician">
                        <input class="form-control" name="value" placeholder="Add technician name" required>
                        <button class="btn btn-primary" type="submit">Add</button>
                    </form>
                    <ul class="list-group">
                        <?php foreach ($technicians as $technician) : ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($technician['name'], ENT_QUOTES); ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="remove_technician">
                                    <input type="hidden" name="value" value="<?php echo htmlspecialchars($technician['name'], ENT_QUOTES); ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
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
                    <h2 class="h5">Vans</h2>
                    <form class="d-flex gap-2 mb-3" method="post">
                        <input type="hidden" name="action" value="add_van">
                        <input class="form-control" name="value" placeholder="Add van name/number" required>
                        <button class="btn btn-primary" type="submit">Add</button>
                    </form>
                    <ul class="list-group">
                        <?php foreach ($vans as $van) : ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($van['name'], ENT_QUOTES); ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="remove_van">
                                    <input type="hidden" name="value" value="<?php echo htmlspecialchars($van['name'], ENT_QUOTES); ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
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
                    <h2 class="h5">Daily Parts</h2>
                    <form class="d-flex gap-2 mb-3" method="post">
                        <input type="hidden" name="action" value="add_part">
                        <input class="form-control" name="value" placeholder="Add part name" required>
                        <button class="btn btn-primary" type="submit">Add</button>
                    </form>
                    <ul class="list-group">
                        <?php foreach ($parts as $part) : ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($part['name'], ENT_QUOTES); ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="remove_part">
                                    <input type="hidden" name="value" value="<?php echo htmlspecialchars($part['name'], ENT_QUOTES); ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
