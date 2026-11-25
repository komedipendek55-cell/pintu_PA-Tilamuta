<?php
require_once __DIR__ . '/../includes/bootstrap.php';
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { set_flash('danger','Metode tidak diizinkan.'); redirect(base_url()); }

$rating = (int)($_POST['rating'] ?? 0);
$message = trim((string)($_POST['message'] ?? ''));
$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));

if ($rating < 1 || $rating > 5) { set_flash('danger','Rating wajib diisi 1–5.'); redirect(base_url()); }
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { set_flash('danger','Format email tidak valid.'); redirect(base_url()); }

try {
    $pdo = Database::pdo();
    $pdo->prepare("INSERT INTO feedback (rating, message, name, email, status) VALUES (?,?,?,?, 'PENDING')")
        ->execute([$rating, mb_substr($message,0,1000), mb_substr($name,0,100) ?: null, $email ?: null]);
    set_flash('success','Terima kasih! Feedback Anda telah kami terima.');
} catch (Throwable $e) {
    error_log('[PINTU] feedback: '.$e->getMessage());
    set_flash('danger','Gagal mengirim feedback.');
}
redirect(base_url());