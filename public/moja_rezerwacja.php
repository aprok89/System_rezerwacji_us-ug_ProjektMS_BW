<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/booking.php';

$user = require_role(['client'], 'login.php');
$pdo  = db();

$id = (int)($_GET['id'] ?? 0);


$st = $pdo->prepare(
    "SELECT r.*, s.name AS service_name, s.price, s.duration,
            CONCAT(eu.name, ' ', eu.surname) AS employee_name
     FROM reservations r
     JOIN services s   ON s.id  = r.service_id
     JOIN employees em ON em.id = r.employee_id
     JOIN users eu     ON eu.id = em.user_id
     WHERE r.id = ? AND r.user_id = ?");
$st->execute([$id, $user['id']]);
$res = $st->fetch();
if (!$res) {
    flash('Nie znaleziono rezerwacji.', 'error');
    redirect('moje_rezerwacje.php');
}

$st = $pdo->prepare('SELECT old_status, new_status, changed_at FROM reservation_status_history
                     WHERE reservation_id = ? ORDER BY changed_at DESC, id DESC');
$st->execute([$id]);
$history = $st->fetchAll();

$canCancel = in_array($res['status'], ACTIVE_STATUSES, true)
          && strtotime($res['reservation_date'] . ' ' . $res['start_time']) > time();

$pageTitle = 'Rezerwacja #' . $id;
$activeNav = 'mine';
require __DIR__ . '/includes/site_header.php';
?>
<div class="card">
    <dl class="summary">
        <dt>Usługa</dt><dd><?= e($res['service_name']) ?> (<?= (int)$res['duration'] ?> min)</dd>
        <dt>Pracownik</dt><dd><?= e($res['employee_name']) ?></dd>
        <dt>Termin</dt><dd><?= e(date('d.m.Y', strtotime($res['reservation_date']))) ?>, <?= e(substr($res['start_time'], 0, 5)) ?>-<?= e(substr($res['end_time'], 0, 5)) ?></dd>
        <dt>Cena</dt><dd><?= number_format((float)$res['price'], 2, ',', ' ') ?> zł</dd>
        <dt>Status</dt><dd><?= status_badge($res['status']) ?></dd>
        <dt>Twój komentarz</dt><dd><?= e($res['comment'] ?? '') !== '' ? e($res['comment']) : '<span class="muted">brak</span>' ?></dd>
    </dl>
    <?php if ($canCancel): ?>
        <form class="inline" method="post" action="moje_rezerwacje.php" data-confirm="Na pewno anulować tę rezerwację?">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-danger" type="submit">Anuluj rezerwację</button>
        </form>
    <?php endif; ?>
    <a class="btn btn-light" href="moje_rezerwacje.php">Wróć do listy</a>
</div>

<h2>Historia statusu</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>Data</th><th>Z</th><th>Na</th></tr></thead>
    <tbody>
    <?php if (!$history): ?><tr><td colspan="3" class="empty">Brak wpisów.</td></tr><?php endif; ?>
    <?php foreach ($history as $h): ?>
        <tr>
            <td><?= e($h['changed_at']) ?></td>
            <td><?= $h['old_status'] !== null ? status_badge($h['old_status']) : '<span class="muted">-</span>' ?></td>
            <td><?= status_badge($h['new_status']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/includes/site_footer.php'; ?>
