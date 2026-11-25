<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_ADMIN]);
header('Content-Type: application/json; charset=utf-8');

$pdo = Database::pdo();
$daily = $pdo->query("SELECT visit_date, COUNT(DISTINCT visitor_key) c
                      FROM visitor_logs
                      WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
                      GROUP BY visit_date ORDER BY visit_date")->fetchAll();
json_response(true, 'OK', ['daily'=>$daily]);