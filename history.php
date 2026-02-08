<?php
require_once 'config.php';

$db = get_db_connection();
$stmt = $db->query('SELECT id, technician_name, van_name, checklist_date, created_at FROM checklists ORDER BY checklist_date DESC, created_at DESC');
$checklists = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Past Checklists</title>
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
            <h1 class="display-6 fw-bold">Past Checklists</h1>
            <p class="text-muted mb-0">Review previous tool and parts loadouts.</p>
        </div>
        <a class="btn btn-outline-primary mt-3 mt-md-0" href="index.php">Create New Checklist</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($checklists)) : ?>
                <p class="text-muted mb-0">No checklists have been saved yet.</p>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Checklist Date</th>
                                <th>Technician</th>
                                <th>Van</th>
                                <th>Created At</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checklists as $checklist) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($checklist['checklist_date'], ENT_QUOTES); ?></td>
                                    <td><?php echo htmlspecialchars($checklist['technician_name'], ENT_QUOTES); ?></td>
                                    <td><?php echo htmlspecialchars($checklist['van_name'], ENT_QUOTES); ?></td>
                                    <td><?php echo htmlspecialchars($checklist['created_at'], ENT_QUOTES); ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-secondary" href="view_checklist.php?id=<?php echo (int) $checklist['id']; ?>">View</a>
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
</body>
</html>
