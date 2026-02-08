<?php
require_once 'config.php';

$checklist_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($checklist_id <= 0) {
    header('Location: history.php');
    exit;
}

$db = get_db_connection();
$checklist_stmt = $db->prepare('SELECT * FROM checklists WHERE id = :id');
$checklist_stmt->execute([':id' => $checklist_id]);
$checklist = $checklist_stmt->fetch();

if (!$checklist) {
    header('Location: history.php');
    exit;
}

$item_stmt = $db->prepare('SELECT item_name, item_type, is_checked FROM checklist_items WHERE checklist_id = :id ORDER BY item_type, item_name');
$item_stmt->execute([':id' => $checklist_id]);
$items = $item_stmt->fetchAll();

$checklist_date = new DateTime($checklist['checklist_date']);
$created_at = new DateTime($checklist['created_at']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checklist Details</title>
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
            <h1 class="display-6 fw-bold">Checklist Details</h1>
            <p class="text-muted mb-0">Technician loadout from <?php echo htmlspecialchars($checklist_date->format('d/m/Y'), ENT_QUOTES); ?>.</p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <a class="btn btn-outline-secondary" href="history.php">Back to History</a>
            <a class="btn btn-outline-primary" href="index.php">New Checklist</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Checklist Info</h2>
                    <dl class="row mb-0">
                        <dt class="col-5">Technician</dt>
                        <dd class="col-7"><?php echo htmlspecialchars($checklist['technician_name'], ENT_QUOTES); ?></dd>
                        <dt class="col-5">Van</dt>
                        <dd class="col-7"><?php echo htmlspecialchars($checklist['van_name'], ENT_QUOTES); ?></dd>
                        <dt class="col-5">Date</dt>
                        <dd class="col-7"><?php echo htmlspecialchars($checklist_date->format('d/m/Y'), ENT_QUOTES); ?></dd>
                        <dt class="col-5">Type</dt>
                        <dd class="col-7"><?php echo htmlspecialchars($checklist['checklist_type'] ?? 'Daily', ENT_QUOTES); ?></dd>
                        <dt class="col-5">Created</dt>
                        <dd class="col-7"><?php echo htmlspecialchars($created_at->format('d/m/Y'), ENT_QUOTES); ?></dd>
                    </dl>
                    <?php if (!empty($checklist['notes'])) : ?>
                        <hr>
                        <h3 class="h6">Notes</h3>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($checklist['notes'], ENT_QUOTES)); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Tools & Parts</h2>
                    <?php if (empty($items)) : ?>
                        <p class="text-muted mb-0">No items were saved for this checklist.</p>
                    <?php else : ?>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Type</th>
                                        <th>Status</th>
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
                                            <td>
                                                <?php if ((int) $item['is_checked'] === 1) : ?>
                                                    <span class="badge text-bg-success">Taken</span>
                                                <?php else : ?>
                                                    <span class="badge text-bg-secondary">Not Taken</span>
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
