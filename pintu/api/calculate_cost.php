<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false,'Metode tidak diizinkan.',null,405);
verify_csrf();

$costType = $_POST['cost_type'] ?? '';
$qtyPenggugat = max(1, (int)($_POST['qty_penggugat'] ?? 1));
$qtyTergugat  = max(1, (int)($_POST['qty_tergugat'] ?? 1));
$qtyPemohon   = max(1, (int)($_POST['qty_pemohon'] ?? 1));

if (!cost_type_exists($costType, true)) json_response(false,'Jenis biaya tidak valid.',null,422);

$pdo = Database::pdo();
$stmt = $pdo->prepare("SELECT description, amount, calculation_type FROM biaya_perkara WHERE cost_type=? AND status='ACTIVE' ORDER BY id");
$stmt->execute([$costType]);
$rows = $stmt->fetchAll();

$details = [];
$total = 0.0;
foreach ($rows as $r) {
    $amt = (float)$r['amount'];
    $qty = 1;
    switch ($r['calculation_type']) {
        case 'PER_PENGGUGAT': $qty = $qtyPenggugat; break;
        case 'PER_TERGUGAT':  $qty = $qtyTergugat;  break;
        case 'PER_PEMOHON':   $qty = $qtyPemohon;   break;
    }
    $sub = $amt * $qty;
    $total += $sub;
    $details[] = [
        'description' => $r['description'],
        'calculation_type' => $r['calculation_type'],
        'qty' => $qty,
        'amount' => $amt,
        'subtotal' => $sub,
    ];
}
json_response(true, 'OK', ['details'=>$details, 'total'=>$total, 'total_formatted'=>format_rupiah($total)]);