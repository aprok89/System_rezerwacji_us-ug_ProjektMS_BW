<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);


$st = $pdo->prepare(
    "SELECT r.*, s.name AS service_name, s.price, s.duration,
            CONCAT(c.name, ' ', c.surname) AS client_name, c.email AS client_email, c.phone AS client_phone
     FROM reservations r
     JOIN users c    ON c.id = r.user_id
     JOIN services s ON s.id = r.service_id
     WHERE r.id = ? AND r.employee_id = ?");
$st->execute([$id, $empId]);
$res = $st->fetch();
if (!$res) {
    flash('Nie znaleziono rezerwacji.', 'error');
    redirect('index.php');
}

$allowed = EMPLOYEE_TRANSITIONS[$res['status']] ?? [];

if (is_post()) {
    csrf_verify();
    $new = $_POST['status'] ?? '';
    if (!is_string($new) || !in_array($new, $allowed, true)) {   
        flash('Ta zmiana statusu nie jest dozwolona.', 'error');
    } else {
        try {
            if (reservation_change_status($pdo, $id, $res['status'], $new, (int)$user['id'])) {
                flash('Status wizyty został zmieniony na: ' . RESERVATION_STATUSES[$new] . '.');
            } else {
                flash('Status tej wizyty zdążył się już zmienić. Odśwież stronę.', 'error');
            }
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            flash('Nie udało się zmienić statusu.', 'error');
        }
    }
    redirect('reservation_view.php?id=' . $id);
}

$st = $pdo->prepare(
    "SELECT h.old_status, h.new_status, h.changed_at, CONCAT(u.name, ' ', u.surname) AS who
     FROM reservation_status_history h LEFT JOIN users u ON u.id = h.changed_by
     WHERE h.reservation_id = ? ORDER BY h.changed_at DESC, h.id DESC");
$st->execute([$id]);
$history = $st->fetchAll();

$pageTitle = 'Wizyta #' . $id;
$activeNav = '';
require __DIR__ . '/_header.php';
?>
<div class="card">
    <dl class="details">
        <dt>Klient</dt><dd><?= e($res['client_name']) ?></dd>
        <dt>Kontakt</dt><dd><?= e($res['client_email']) ?>, <?= e($res['client_phone'] ?? '') ?></dd>
        <dt>Usługa</dt><dd><?= e($res['service_name']) ?> (<?= (int)$res['duration'] ?> min, <?= number_format((float)$res['price'], 2, ',', ' ') ?> zł)</dd>
        <dt>Termin</dt><dd><?= e(date('d.m.Y', strtotime($res['reservation_date']))) ?>, <?= e(substr($res['start_time'], 0, 5)) ?>-<?= e(substr($res['end_time'], 0, 5)) ?></dd>
        <dt>Komentarz klienta</dt><dd><?= e($res['comment'] ?? '') !== '' ? e($res['comment']) : '<span class="muted">brak</span>' ?></dd>
        <dt>Status</dt><dd><?= status_badge($res['status']) ?></dd>
    </dl>
</div>

<?php if ($allowed): ?>
<form class="card form-narrow" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <label>Zmień status na
        <select name="status">
            <?php foreach ($allowed as $s): ?>
                <option value="<?= e($s) ?>"><?= e(RESERVATION_STATUSES[$s]) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="btn" type="submit">Zapisz status</button>
</form>
<?php else: ?>
    <div class="alert alert-error">Ta wizyta ma status końcowy. Zmianę może wprowadzić administrator.</div>
<?php endif; ?>
<p><a class="btn btn-light" href="index.php">Wróć</a></p>

<h2>Historia statusu</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>Data</th><th>Z</th><th>Na</th><th>Zmienił</th></tr></thead>
    <tbody>
    <?php if (!$history): ?><tr><td colspan="4" class="empty">Brak wpisów.</td></tr><?php endif; ?>
    <?php foreach ($history as $h): ?>
        <tr>
            <td><?= e($h['changed_at']) ?></td>
            <td><?= $h['old_status'] !== null ? status_badge($h['old_status']) : '<span class="muted">-</span>' ?></td>
            <td><?= status_badge($h['new_status']) ?></td>
            <td><?= e($h['who'] ?? 'nieznany') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
