<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require __DIR__ . '/talbe.php';

$today = date('Y-m-d');

$st = $pdo->prepare(VISITS_SELECT . ' WHERE r.employee_id = ? AND r.reservation_date = ? ORDER BY r.start_time');
$st->execute([$empId, $today]);
$visits = $st->fetchAll();

$st = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE employee_id = ? AND reservation_date > ? AND status <> 'anulowana'");
$st->execute([$empId, $today]);
$upcomingCount = (int)$st->fetchColumn();

$pageTitle = 'Wizyty na dzisiaj';
$activeNav = 'today';
require __DIR__ . '/_header.php';
?>
<div class="stats">
    <div class="stat"><div class="num"><?= count($visits) ?></div><div class="lbl">Wizyty dzisiaj (<?= e(date('d.m.Y')) ?>)</div></div>
    <a class="stat" href="visits.php?view=upcoming"><div class="num"><?= $upcomingCount ?></div><div class="lbl">Nadchodzące wizyty</div></a>
</div>
<?php render_visits($visits); ?>
<?php require __DIR__ . '/_footer.php'; ?>
