<?php
require_once 'config.php';

$db = get_db_connection();
$stmt = $db->query('SELECT id, technician_name, van_name, checklist_date, created_at FROM checklists ORDER BY checklist_date DESC, created_at DESC');
$checklists = $stmt->fetchAll();
$grouped_checklists = [];

foreach ($checklists as $checklist) {
    $date = new DateTime($checklist['checklist_date']);
    $group_key = $date->format('F Y');
    $grouped_checklists[$group_key][] = $checklist;
}
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
            <?php if (empty($grouped_checklists)) : ?>
                <p class="text-muted mb-0">No checklists have been saved yet.</p>
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
                                                    <th>Checklist Date</th>
                                                    <th>Technician</th>
                                                    <th>Van</th>
                                                    <th>Created At</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($group_items as $checklist) : ?>
                                                    <?php
                                                    $date = new DateTime($checklist['checklist_date']);
                                                    $created = new DateTime($checklist['created_at']);
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($date->format('d/m/Y'), ENT_QUOTES); ?></td>
                                                        <td><?php echo htmlspecialchars($checklist['technician_name'], ENT_QUOTES); ?></td>
                                                        <td><?php echo htmlspecialchars($checklist['van_name'], ENT_QUOTES); ?></td>
                                                        <td><?php echo htmlspecialchars($created->format('d/m/Y'), ENT_QUOTES); ?></td>
                                                        <td class="text-end">
                                                            <a class="btn btn-sm btn-outline-secondary" href="view_checklist.php?id=<?php echo (int) $checklist['id']; ?>">View</a>
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
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
