<?php
require __DIR__ . '/_init.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$st = $pdo->prepare(
    "SELECT r.*, s.name AS service_name, s.price, s.duration,
            CONCAT(c.name, ' ', c.surname) AS client_name, c.email AS client_email, c.phone AS client_phone,
            CONCAT(eu.name, ' ', eu.surname) AS employee_name
     FROM reservations r
     JOIN services s   ON s.id  = r.service_id
     JOIN users c      ON c.id  = r.user_id
     JOIN employees em ON em.id = r.employee_id
     JOIN users eu     ON eu.id = em.user_id
     WHERE r.id = ?");
$st->execute([$id]);
$res = $st->fetch();
if (!$res) {
    flash('Nie znaleziono rezerwacji.', 'error');
    redirect('reservations.php');
}


if (is_post()) {
    csrf_verify();
    $newStatus = $_POST['status'] ?? '';
    $oldStatus = $res['status'];

    if (!isset(RESERVATION_STATUSES[$newStatus])) {                
        flash('Nieprawidłowy status.', 'error');
    } elseif ($newStatus === $oldStatus) {
        flash('Rezerwacja ma już ten status.', 'error');
    } else {

        $conflict = 0;
        if ($oldStatus === 'anulowana') {
            $c = $pdo->prepare(
                "SELECT COUNT(*) FROM reservations
                 WHERE employee_id = ? AND reservation_date = ? AND status <> 'anulowana' AND id <> ?
                   AND ? < end_time AND ? > start_time");
            $c->execute([$res['employee_id'], $res['reservation_date'], $id, $res['start_time'], $res['end_time']]);
            $conflict = (int)$c->fetchColumn();
        }

        if ($conflict > 0) {
            flash('Nie można przywrócić rezerwacji - termin koliduje z inną aktywną rezerwacją tego pracownika.', 'error');
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE reservations SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
                $pdo->prepare('INSERT INTO reservation_status_history (reservation_id, old_status, new_status, changed_by) VALUES (?,?,?,?)')
                    ->execute([$id, $oldStatus, $newStatus, $admin['id']]);
                $pdo->commit();
                log_admin("Zmiana statusu rezerwacji #$id: $oldStatus → $newStatus", 'reservations', $id);
                flash('Status rezerwacji został zmieniony na: ' . RESERVATION_STATUSES[$newStatus] . '.');
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log($ex->getMessage());
                flash('Nie udało się zmienić statusu.', 'error');
            }
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

$pageTitle = 'Rezerwacja #' . $id;
$activeNav = 'reservations';
require __DIR__ . '/_header.php';
?>
<div class="card">
    <dl class="details">
        <dt>Klient</dt><dd><?= e($res['client_name']) ?> (<?= e($res['client_email']) ?>, <?= e($res['client_phone'] ?? '') ?>)</dd>
        <dt>Usługa</dt><dd><?= e($res['service_name']) ?> (<?= (int)$res['duration'] ?> min)</dd>
        <dt>Pracownik</dt><dd><?= e($res['employee_name']) ?></dd>
        <dt>Termin</dt><dd><?= e($res['reservation_date']) ?>, <?= e(substr($res['start_time'], 0, 5)) ?>-<?= e(substr($res['end_time'], 0, 5)) ?></dd>
        <dt>Cena</dt><dd><?= number_format((float)$res['price'], 2, ',', ' ') ?> zł</dd>
        <dt>Komentarz klienta</dt><dd><?= e($res['comment'] ?? '') !== '' ? e($res['comment']) : '<span class="muted">brak</span>' ?></dd>
        <dt>Utworzono</dt><dd><?= e($res['created_at']) ?></dd>
        <dt>Status</dt><dd><?= status_badge($res['status']) ?></dd>
    </dl>
</div>

<form class="card form-narrow" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <label>Zmień status
        <select name="status">
            <?php foreach (RESERVATION_STATUSES as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= $res['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="btn" type="submit">Zapisz status</button>
    <a class="btn btn-light" href="reservations.php">Wróć do listy</a>
</form>

<?php if ($res['status'] !== 'anulowana'): ?>
<form class="inline" method="post" data-confirm="Na pewno anulować tę rezerwację?">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="status" value="anulowana">
    <button class="btn btn-danger" type="submit">Anuluj rezerwację</button>
</form>
<?php endif; ?>

<h2>Historia zmian statusu</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>Data</th><th>Z</th><th>Na</th><th>Zmienił</th></tr></thead>
    <tbody>
    <?php if (!$history): ?><tr><td colspan="4" class="empty">Brak zapisanych zmian.</td></tr><?php endif; ?>
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
