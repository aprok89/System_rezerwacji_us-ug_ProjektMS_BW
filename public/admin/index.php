<?php
require __DIR__ . '/_init.php';

$one = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();

$stats = [
    'Klienci'              => $one("SELECT COUNT(*) FROM users WHERE role = 'client'"),
    'Aktywni pracownicy'   => $one('SELECT COUNT(*) FROM employees WHERE active = 1'),
    'Aktywne usługi'       => $one('SELECT COUNT(*) FROM services WHERE active = 1'),
    'Wszystkie rezerwacje' => $one('SELECT COUNT(*) FROM reservations'),
    'Rezerwacje na dziś'   => $one('SELECT COUNT(*) FROM reservations WHERE reservation_date = CURDATE()'),
];


$byStatus = array_fill_keys(array_keys(RESERVATION_STATUSES), 0);
foreach ($pdo->query('SELECT status, COUNT(*) AS c FROM reservations GROUP BY status') as $row) {
    $byStatus[$row['status']] = (int)$row['c'];
}


$today = $pdo->query(
    "SELECT r.id, r.start_time, r.end_time, r.status, s.name AS service_name,
            CONCAT(c.name, ' ', c.surname) AS client_name,
            CONCAT(eu.name, ' ', eu.surname) AS employee_name
     FROM reservations r
     JOIN services s   ON s.id  = r.service_id
     JOIN users c      ON c.id  = r.user_id
     JOIN employees em ON em.id = r.employee_id
     JOIN users eu     ON eu.id = em.user_id
     WHERE r.reservation_date = CURDATE()
     ORDER BY r.start_time"
)->fetchAll();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/_header.php';
?>
<div class="stats">
    <?php foreach ($stats as $label => $value): ?>
        <div class="stat"><div class="num"><?= $value ?></div><div class="lbl"><?= e($label) ?></div></div>
    <?php endforeach; ?>
</div>

<h2>Rezerwacje według statusu</h2>
<div class="stats">
    <?php foreach (RESERVATION_STATUSES as $key => $label): ?>
        <a class="stat" href="reservations.php?status=<?= e(rawurlencode($key)) ?>">
            <div class="num"><?= $byStatus[$key] ?></div><div class="lbl"><?= e($label) ?></div>
        </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <?php $maxStatus = max(1, max($byStatus)); ?>
    <?php foreach (RESERVATION_STATUSES as $key => $label): ?>
        <div class="bar-row">
            <span class="bar-label"><?= e($label) ?></span>
            <div class="bar"><div class="bar-fill badge-<?= e(STATUS_CLASSES[$key]) ?>" style="width: <?= (int)round($byStatus[$key] / $maxStatus * 100) ?>%"></div></div>
            <span class="bar-num"><?= $byStatus[$key] ?></span>
        </div>
    <?php endforeach; ?>
</div>

<h2>Dzisiejsze wizyty</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>Godzina</th><th>Klient</th><th>Usługa</th><th>Pracownik</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php if (!$today): ?>
        <tr><td colspan="6" class="empty">Brak rezerwacji na dziś.</td></tr>
    <?php endif; ?>
    <?php foreach ($today as $r): ?>
        <tr>
            <td><?= e(substr($r['start_time'], 0, 5)) ?>-<?= e(substr($r['end_time'], 0, 5)) ?></td>
            <td><?= e($r['client_name']) ?></td>
            <td><?= e($r['service_name']) ?></td>
            <td><?= e($r['employee_name']) ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td><a href="reservation_view.php?id=<?= (int)$r['id'] ?>">Szczegóły</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
