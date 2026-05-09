<?php

declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET,POST,PATCH,DELETE,OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$listType = isset($_GET['type']) && in_array($_GET['type'], ['daily', 'weekly'], true) ? $_GET['type'] : 'daily';
$body = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];

if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT id, list_type, name, category, checked FROM checklist_items WHERE list_type = :list_type ORDER BY id DESC');
    $stmt->execute(['list_type' => $listType]);
    echo json_encode(['items' => $stmt->fetchAll()]);
    exit;
}

if ($method === 'POST') {
    $name = trim((string) ($body['name'] ?? ''));
    $category = trim((string) ($body['category'] ?? 'General'));
    $requestType = in_array(($body['list_type'] ?? ''), ['daily', 'weekly'], true) ? $body['list_type'] : $listType;
    if ($name === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Name is required']);
        exit;
    }
    $stmt = $pdo->prepare('INSERT INTO checklist_items (list_type, name, category, checked) VALUES (:list_type, :name, :category, 0)');
    $stmt->execute(['list_type' => $requestType, 'name' => $name, 'category' => $category]);
    echo json_encode(['id' => (int) $pdo->lastInsertId(), 'list_type' => $requestType, 'name' => $name, 'category' => $category, 'checked' => 0]);
    exit;
}

if ($method === 'PATCH' && $id) {
    $checked = isset($body['checked']) ? (int) ((bool) $body['checked']) : 0;
    $stmt = $pdo->prepare('UPDATE checklist_items SET checked = :checked WHERE id = :id');
    $stmt->execute(['checked' => $checked, 'id' => $id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($method === 'DELETE' && $id) {
    $stmt = $pdo->prepare('DELETE FROM checklist_items WHERE id = :id');
    $stmt->execute(['id' => $id]);
    http_response_code(204);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
