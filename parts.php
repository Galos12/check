<?php
require_once 'config.php';

$message = '';
$error = '';

function parse_xlsx_rows(string $file_path): array
{
    $rows = [];
    $zip = new ZipArchive();
    if ($zip->open($file_path) !== true) {
        return $rows;
    }

    $shared_strings = [];
    $shared_xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($shared_xml !== false) {
        $shared = simplexml_load_string($shared_xml);
        if ($shared !== false) {
            foreach ($shared->si as $si) {
                $shared_strings[] = trim((string) $si->t);
            }
        }
    }

    $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheet_xml === false) {
        $zip->close();
        return $rows;
    }

    $sheet = simplexml_load_string($sheet_xml);
    if ($sheet === false || !isset($sheet->sheetData->row)) {
        $zip->close();
        return $rows;
    }

    foreach ($sheet->sheetData->row as $row) {
        $line = [];
        foreach ($row->c as $cell) {
            $value = '';
            if ((string) $cell['t'] === 's') {
                $index = (int) $cell->v;
                $value = $shared_strings[$index] ?? '';
            } else {
                $value = (string) $cell->v;
            }
            $line[] = trim($value);
        }
        if ($line) {
            $rows[] = $line;
        }
    }

    $zip->close();
    return $rows;
}

function parse_csv_rows(string $file_path): array
{
    $rows = [];
    if (($handle = fopen($file_path, 'r')) === false) {
        return $rows;
    }

    while (($data = fgetcsv($handle)) !== false) {
        $clean = array_map(static fn($value) => trim((string) $value), $data);
        if ($clean) {
            $rows[] = $clean;
        }
    }

    fclose($handle);
    return $rows;
}

try {
    $db = get_db_connection();

    // Ensure schema compatibility for hosted environments.
    $db->query("CREATE TABLE IF NOT EXISTS parts_library (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        part_type VARCHAR(150) NOT NULL DEFAULT '',
        stock INT NOT NULL DEFAULT 0,
        UNIQUE KEY uniq_part_name_type (name, part_type)
    )");

    $columns = [];
    $columns_result = $db->query("SHOW COLUMNS FROM parts_library");
    if ($columns_result) {
        while ($column = $columns_result->fetch_assoc()) {
            $columns[] = $column['Field'];
        }
    }
    if (!in_array('part_type', $columns, true)) {
        $db->query("ALTER TABLE parts_library ADD COLUMN part_type VARCHAR(150) NOT NULL DEFAULT ''");
    }
    if (!in_array('stock', $columns, true)) {
        $db->query("ALTER TABLE parts_library ADD COLUMN stock INT NOT NULL DEFAULT 0");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['parts_excel'])) {
        $upload = $_FILES['parts_excel'];
        if (!empty($upload['tmp_name']) && (int) $upload['error'] === UPLOAD_ERR_OK) {
            $name = strtolower((string) $upload['name']);
            $rows = [];

            if (str_ends_with($name, '.xlsx')) {
                $rows = parse_xlsx_rows($upload['tmp_name']);
            } elseif (str_ends_with($name, '.csv')) {
                $rows = parse_csv_rows($upload['tmp_name']);
            }

            if (!$rows) {
                $error = 'No se pudo leer el archivo. Usa .xlsx o .csv.';
            } else {
                $db->begin_transaction();
                $db->query('TRUNCATE TABLE parts_library');
                $insert = $db->prepare('INSERT INTO parts_library (name, part_type, stock) VALUES (?, ?, ?)');

                foreach ($rows as $index => $row) {
                    // Skip header row if present.
                    if ($index === 0) {
                        $header = strtolower(implode(' ', $row));
                        if (str_contains($header, 'name') || str_contains($header, 'nombre')) {
                            continue;
                        }
                    }

                    $part_name = $row[0] ?? '';
                    $part_type = $row[1] ?? '';
                    $stock = isset($row[2]) ? (int) $row[2] : 0;

                    if ($part_name === '') {
                        continue;
                    }

                    $insert->bind_param('ssi', $part_name, $part_type, $stock);
                    $insert->execute();
                }

                $db->commit();
                $message = 'Inventario cargado correctamente.';
            }
        } else {
            $error = 'Error al subir archivo.';
        }
    }

    $parts_result = $db->query('SELECT id, name, part_type, stock FROM parts_library ORDER BY name');
    $parts = $parts_result ? $parts_result->fetch_all(MYSQLI_ASSOC) : [];
} catch (Throwable $exception) {
    $error = 'Error de base de datos: ' . $exception->getMessage();
    $parts = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sección de Repuestos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="display-6 fw-bold">Repuestos</h1>
            <p class="text-muted mb-0">Carga inventario desde Excel y busca por nombre.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="manage_options.php">Administrar opciones</a>
            <a class="btn btn-outline-primary" href="index.php">Checklist</a>
        </div>
    </div>

    <?php if ($message !== '') : ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
    <?php endif; ?>
    <?php if ($error !== '') : ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label" for="parts_excel">Archivo Excel (.xlsx) o CSV</label>
                    <input class="form-control" type="file" id="parts_excel" name="parts_excel" accept=".xlsx,.csv" required>
                </div>
                <div class="col-md-4 d-grid">
                    <button class="btn btn-primary" type="submit">Cargar inventario</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="parts-search">Buscar repuesto</label>
                <input class="form-control" id="parts-search" placeholder="Escribe el nombre del repuesto...">
            </div>
            <div class="table-responsive parts-results-table">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody id="parts-results-body">
                        <tr>
                            <td colspan="3" class="text-muted text-center py-4">Empieza a escribir para buscar repuestos.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script>
const PARTS_DATA = <?php echo json_encode($parts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

$(function () {
    const $search = $('#parts-search');
    const $body = $('#parts-results-body');

    const renderRows = (rows) => {
        if (!rows.length) {
            $body.html('<tr><td colspan="3" class="text-muted text-center py-4">Sin coincidencias.</td></tr>');
            return;
        }

        const html = rows.map((part) => `
            <tr>
                <td>${part.name}</td>
                <td>${part.part_type || '-'}</td>
                <td>${part.stock}</td>
            </tr>
        `).join('');
        $body.html(html);
    };

    $search.on('input', function () {
        const term = $(this).val().toLowerCase().trim();
        if (!term) {
            $body.html('<tr><td colspan="3" class="text-muted text-center py-4">Empieza a escribir para buscar repuestos.</td></tr>');
            return;
        }

        const filtered = PARTS_DATA.filter((part) => part.name.toLowerCase().includes(term));
        renderRows(filtered);
    });
});
</script>
</body>
</html>
