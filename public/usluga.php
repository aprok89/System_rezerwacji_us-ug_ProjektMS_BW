<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/booking.php';
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$service = booking_get_service($pdo, $id);   // <--- o to tylko bedzie do aktynwych uslug 
if (!$service) {
    flash('Nie znaleziono usługi.', 'error');
    redirect('uslugi.php');
}

$employees = booking_get_employees($pdo, $id);
$dayShort = [1 => 'Pn', 2 => 'Wt', 3 => 'Śr', 4 => 'Cz', 5 => 'Pt', 6 => 'So', 7 => 'Nd'];

$hoursOf = function (int $employeeId) use ($pdo, $dayShort): string {
    $st = $pdo->prepare('SELECT day_of_week, start_time, end_time FROM employee_availability
                         WHERE employee_id = ? ORDER BY day_of_week, start_time');
    $st->execute([$employeeId]);
    $parts = [];
    foreach ($st->fetchAll() as $r) {
        $parts[] = $dayShort[(int)$r['day_of_week']] . ' ' . substr($r['start_time'], 0, 5) . '-' . substr($r['end_time'], 0, 5);
    }
    return $parts ? implode(', ', $parts) : 'brak ustalonych godzin';
};

$pageTitle = $service['name'];
$activeNav = 'offer';
require __DIR__ . '/includes/site_header.php';
?>
<div class="card">
    <dl class="summary">
        <dt>Kategoria</dt><dd><?= e($service['category_name']) ?></dd>
        <dt>Czas trwania</dt><dd><?= (int)$service['duration'] ?> min</dd>
        <dt>Cena</dt><dd><?= number_format((float)$service['price'], 2, ',', ' ') ?> zł</dd>
        <dt>Dostępność</dt><dd><?= $employees ? 'Dostępna (' . count($employees) . ' ' . (count($employees) === 1 ? 'pracownik' : 'pracowników') . ')' : 'Chwilowo niedostępna' ?></dd>
    </dl>
    <p><?= nl2br(e((string)$service['description'])) ?></p>
    <?php if ($employees): ?>
        <a class="btn" href="rezerwacja.php?service=<?= (int)$service['id'] ?>">Zarezerwuj termin</a>
    <?php endif; ?>
    <a class="btn btn-light" href="uslugi.php">Wróć do oferty</a>
</div>

<?php if ($employees): ?>
    <h2>Kto wykonuje tę usługę</h2>
    <div class="choices">
    <?php foreach ($employees as $emp): ?>
        <div class="choice">
            <strong><?= e($emp['name'] . ' ' . $emp['surname']) ?></strong>
            <div class="muted"><?= e($emp['description']) ?></div>
            <div class="muted">Godziny pracy: <?= e($hoursOf((int)$emp['id'])) ?></div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/site_footer.php'; ?>
