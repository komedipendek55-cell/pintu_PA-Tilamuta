<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_ADMIN]);

$pdo = Database::pdo();
$page_title = 'Feedback Pengunjung';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $st = $_POST['status'] ?? '';
    if ($id && in_array($st, [FB_PENDING, FB_PUBLISHED, FB_ARCHIVED], true)) {
        $s = $pdo->prepare("UPDATE feedback SET status=? WHERE id=?");
        $s->execute([$st, $id]);
        audit_log('update_status','feedback',$id,null,['status'=>$st]);
        set_flash('success','Status feedback diperbarui.');
    }
    redirect(admin_url('feedback.php'));
}

$rows = $pdo->query("SELECT * FROM feedback ORDER BY created_at DESC LIMIT 200")->fetchAll();
include __DIR__ . '/../includes/admin_header.php';
?>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-bold"><i class="bi bi-chat-square-heart text-warning"></i> Daftar Feedback</div>
  <div class="card-body">
    <?php if (!$rows): ?>
      <p class="text-muted">Belum ada feedback.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light"><tr><th>Tanggal</th><th>Rating</th><th>Pengirim</th><th>Pesan</th><th>Status</th><th width="200">Ubah</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="small text-muted"><?= e(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
              <td>
                <?php for ($i=1;$i<=5;$i++): ?>
                  <i class="bi bi-star<?= $i <= (int)$r['rating'] ? '-fill text-warning' : ' text-muted' ?>"></i>
                <?php endfor; ?>
              </td>
              <td>
                <div class="fw-semibold"><?= e($r['name'] ?: 'Anonim') ?></div>
                <?php if ($r['email']): ?><small class="text-muted"><?= e($r['email']) ?></small><?php endif; ?>
              </td>
              <td><small><?= e(mb_strimwidth((string)$r['message'],0,150,'…')) ?></small></td>
              <td>
                <?php
                $map = [FB_PENDING=>['warning','Pending'], FB_PUBLISHED=>['success','Published'], FB_ARCHIVED=>['secondary','Archived']];
                [$cls,$lbl] = $map[$r['status']] ?? ['light','—'];
                ?>
                <span class="badge bg-<?= $cls ?>-subtle text-<?= $cls ?>"><?= e($lbl) ?></span>
              </td>
              <td>
                <form method="post" class="d-flex gap-1">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <select class="form-select form-select-sm" name="status">
                    <option value="PENDING" <?= $r['status']==='PENDING'?'selected':'' ?>>Pending</option>
                    <option value="PUBLISHED" <?= $r['status']==='PUBLISHED'?'selected':'' ?>>Published</option>
                    <option value="ARCHIVED" <?= $r['status']==='ARCHIVED'?'selected':'' ?>>Archived</option>
                  </select>
                  <button class="btn btn-sm btn-outline-primary"><i class="bi bi-check"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>