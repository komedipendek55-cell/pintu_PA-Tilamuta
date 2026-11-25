<?php
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$q = trim((string)($_GET['q'] ?? ''));
$pdo = Database::pdo();

$sql = "SELECT id, category, name, icon_path, short_description FROM perkara WHERE status='PUBLISHED'";
$params = [];
if ($q !== '') {
    $sql .= " AND (name LIKE ? OR short_description LIKE ? OR category LIKE ?)";
    $like = "%$q%"; $params = [$like,$like,$like];
}
$sql .= " ORDER BY category, name LIMIT 50";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$items = $stmt->fetchAll();

foreach ($items as &$it) {
    $it['category_label'] = CATEGORY_LABELS[$it['category']] ?? $it['category'];
    $it['url'] = base_url('detail.php?id=' . (int)$it['id']);
    $it['icon_url'] = $it['icon_path'] ? base_url($it['icon_path']) : null;
    unset($it['icon_path']);
}
echo json_encode(['success'=>true,'data'=>$items], JSON_UNESCAPED_UNICODE);