<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/booking.php';

$user = require_role(['client'], 'login.php');
$pdo  = db();

//Usuwanie rezerwacji ale tylko wlasnych
if (is_post()) {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);

    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT id, status, reservation_date, start_time
                             FROM reservations WHERE id = ? AND user_id = ? FOR UPDATE');
        $st->execute([$id, $user['id']]);
        $r = $st->fetch();

        if (!$r) {
            $pdo->rollBack();
            flash('Nie znaleziono rezerwacji.', 'error');
        } elseif (!in_array($r['status'], ACTIVE_STATUSES, true)) {
            $pdo->rollBack();
            flash('Tej rezerwacji nie można już anulować.', 'error');
        } elseif (strtotime($r['reservation_date'] . ' ' . $r['start_time']) <= time()) {
            $pdo->rollBack();
            flash('Można anulować tylko przyszłe rezerwacje.', 'error');
        } else {
            if (reservation_change_status($pdo, $id, $r['status'], 'anulowana', (int)$user['id'])) {
                $pdo->commit();
                flash('Rezerwacja została anulowana.');
            } else {
                $pdo->rollBack();
                flash('Status tej rezerwacji zdążył się zmienić. Odśwież stronę.', 'error');
            }
        }
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($ex->getMessage());
        flash('Nie udało się anulować rezerwacji.', 'error');
    }
    redirect('moje_rezerwacje.php');
}

//Lista rezerjawacji wlasynch
$st = $pdo->prepare(
    "SELECT r.id, r.reservation_date, r.start_time, r.end_time, r.status,
            s.name AS service_name, s.price,
            CONCAT(eu.name, ' ', eu.surname) AS employee_name
     FROM reservations r
     JOIN services s   ON s.id  = r.service_id
     JOIN employees em ON em.id = r.employee_id
     JOIN users eu     ON eu.id = em.user_id
     WHERE r.user_id = ?
     ORDER BY r.reservation_date, r.start_time");
$st->execute([$user['id']]);

$upcoming = [];
$past     = [];
foreach ($st->fetchAll() as $r) {
    $future = strtotime($r['reservation_date'] . ' ' . $r['start_time']) > time();
    if ($future && in_array($r['status'], ACTIVE_STATUSES, true)) {
        $upcoming[] = $r;
    } else {
        $past[] = $r;
    }
}
$past = array_reverse($past);

function render_reservations(array $rows, bool $withCancel): void
{
    ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Usługa</th><th>Pracownik</th><th>Data</th><th>Godzina</th><th>Cena</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">Brak rezerwacji.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['service_name']) ?></td>
                <td><?= e($r['employee_name']) ?></td>
                <td><?= e(date('d.m.Y', strtotime($r['reservation_date']))) ?></td>
                <td><?= e(substr($r['start_time'], 0, 5)) ?>-<?= e(substr($r['end_time'], 0, 5)) ?></td>
                <td><?= number_format((float)$r['price'], 2, ',', ' ') ?> zł</td>
                <td><?= status_badge($r['status']) ?></td>
                <td class="actions">
                    <a class="btn btn-sm btn-light" href="moja_rezerwacja.php?id=<?= (int)$r['id'] ?>">Szczegóły</a>
                    <?php if ($withCancel): ?>
                        <form class="inline" method="post" data-confirm="Na pewno anulować tę rezerwację?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Anuluj</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php
}

$pageTitle = 'Moje rezerwacje';
$activeNav = 'mine';
require __DIR__ . '/includes/site_header.php';
?>
<p><a class="btn" href="uslugi.php">+ Nowa rezerwacja</a></p>

<h2>Przyszłe rezerwacje</h2>
<?php render_reservations($upcoming, true); ?>

<h2>Zakończone i anulowane</h2>
<?php render_reservations($past, false); ?>
<?php require __DIR__ . '/includes/site_footer.php'; ?>
