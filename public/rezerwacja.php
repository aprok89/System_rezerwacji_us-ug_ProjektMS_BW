<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/booking.php';


if (empty($_SESSION['user_id'])) {
    flash('Zaloguj się lub załóż konto, aby zarezerwować wizytę.', 'error');
    redirect('login.php');
}
$user = require_role(['client'], 'login.php');
$pdo  = db();


if (is_post()) {
    csrf_verify();
    $serviceId  = (int)($_POST['service'] ?? 0);
    $employeeId = (int)($_POST['employee'] ?? 0);
    $date       = is_string($_POST['date'] ?? null) ? trim($_POST['date']) : '';
    $time       = is_string($_POST['time'] ?? null) ? trim($_POST['time']) : '';
    $comment    = is_string($_POST['comment'] ?? null) ? trim($_POST['comment']) : '';

    $back = 'rezerwacja.php?' . http_build_query(['service' => $serviceId, 'employee' => $employeeId, 'date' => $date]);

    if (mb_strlen($comment) > 500) {
        flash('Komentarz może mieć maksymalnie 500 znaków.', 'error');
        redirect($back);
    }

    [$ok, $message] = booking_create($pdo, (int)$user['id'], $serviceId, $employeeId, $date, $time, $comment);
    if ($ok) {
        flash($message);
        redirect('moje_rezerwacje.php');
    }
    flash($message, 'error');
    redirect($back);
}

// KRok pierwszy potwierzdeznie
$serviceId = (int)($_GET['service'] ?? 0);
$service   = booking_get_service($pdo, $serviceId);
if (!$service) {
    flash('Nie znaleziono usługi.', 'error');
    redirect('uslugi.php');
}

$employees  = booking_get_employees($pdo, $serviceId);
$employeeId = (int)($_GET['employee'] ?? 0);
$employee   = $employeeId > 0 ? booking_get_employee($pdo, $employeeId, $serviceId) : false;

$date = is_string($_GET['date'] ?? null) ? trim($_GET['date']) : '';
$time = is_string($_GET['time'] ?? null) ? trim($_GET['time']) : '';
$slots = [];
if (!$employee || !valid_date($date)) {
    $date = '';
    $time = '';
} else {
    $slots = booking_slots($pdo, $employeeId, (int)$service['duration'], $date);
    if (!in_array($time, $slots, true)) {
        $time = '';
    }
}

$stepNow   = !$employee ? 2 : ($date === '' ? 3 : ($time === '' ? 4 : 5));
$stepNames = [1 => 'Usługa', 2 => 'Pracownik', 3 => 'Data', 4 => 'Godzina', 5 => 'Potwierdzenie'];

$link = fn(array $extra) => 'rezerwacja.php?' . http_build_query(array_merge(['service' => $serviceId], $extra));

$pageTitle = 'Rezerwacja: ' . $service['name'];
$activeNav = 'offer';
require __DIR__ . '/includes/site_header.php';
?>
<ol class="steps">
    <?php foreach ($stepNames as $n => $label): ?>
        <li class="<?= $n < $stepNow ? 'done' : ($n === $stepNow ? 'current' : '') ?>"><?= $n ?>. <?= e($label) ?></li>
    <?php endforeach; ?>
</ol>

<div class="card">
    <dl class="summary">
        <dt>Usługa</dt><dd><?= e($service['name']) ?> <a href="uslugi.php" class="muted">(zmień)</a></dd>
        <dt>Czas i cena</dt><dd><?= (int)$service['duration'] ?> min, <?= number_format((float)$service['price'], 2, ',', ' ') ?> zł</dd>
        <?php if ($employee): ?>
            <dt>Pracownik</dt><dd><?= e($employee['name'] . ' ' . $employee['surname']) ?> <a href="<?= e($link([])) ?>" class="muted">(zmień)</a></dd>
        <?php endif; ?>
        <?php if ($date !== ''): ?>
            <dt>Data</dt><dd><?= e(date('d.m.Y', strtotime($date))) ?> <a href="<?= e($link(['employee' => $employeeId])) ?>" class="muted">(zmień)</a></dd>
        <?php endif; ?>
        <?php if ($time !== ''): ?>
            <dt>Godzina</dt><dd><?= e($time) ?>-<?= e(min_to_time(time_to_min($time) + (int)$service['duration'])) ?>
                <a href="<?= e($link(['employee' => $employeeId, 'date' => $date])) ?>" class="muted">(zmień)</a></dd>
        <?php endif; ?>
    </dl>
</div>

<?php if (!$employee): // Krok 2 częsc od pracownika  ?>
    <h2>Wybierz pracownika</h2>
    <?php if (!$employees): ?>
        <div class="alert alert-error">Brak dostępnych pracowników dla tej usługi.</div>
    <?php endif; ?>
    <div class="choices">
        <?php foreach ($employees as $emp): ?>
            <a class="choice" href="<?= e($link(['employee' => (int)$emp['id']])) ?>">
                <strong><?= e($emp['name'] . ' ' . $emp['surname']) ?></strong>
                <div class="muted"><?= e($emp['description']) ?></div>
            </a>
        <?php endforeach; ?>
    </div>

<?php elseif ($date === ''): //Krok trzy wybor dat,daty ?>
    <h2>Wybierz datę</h2>
    <form class="card form-narrow" method="get">
        <input type="hidden" name="service" value="<?= $serviceId ?>">
        <input type="hidden" name="employee" value="<?= $employeeId ?>">
        <label>Data wizyty
            <input type="date" name="date" min="<?= e(date('Y-m-d')) ?>" max="<?= e(booking_max_date()) ?>" required>
        </label>
        <button class="btn" type="submit">Pokaż wolne godziny</button>
    </form>

<?php elseif ($time === ''): // Quatro wybor godziny ?>
    <h2>Wybierz godzinę</h2>
    <?php if (!$slots): ?>
        <div class="alert alert-error">Brak wolnych terminów w tym dniu (pracownik nie pracuje, ma urlop albo wszystkie godziny są zajęte). Wybierz inną datę.</div>
        <a class="btn btn-light" href="<?= e($link(['employee' => $employeeId])) ?>">Zmień datę</a>
    <?php else: ?>
        <div class="slots">
            <?php foreach ($slots as $slot): ?>
                <a class="slot" href="<?= e($link(['employee' => $employeeId, 'date' => $date, 'time' => $slot])) ?>"><?= e($slot) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php else: //Piątka potwierdzenie rezerwacji ?>
    <h2>Potwierdź rezerwację</h2>
    <form class="card form-narrow" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="service" value="<?= $serviceId ?>">
        <input type="hidden" name="employee" value="<?= $employeeId ?>">
        <input type="hidden" name="date" value="<?= e($date) ?>">
        <input type="hidden" name="time" value="<?= e($time) ?>">
        <label>Komentarz do wizyty (opcjonalnie)
            <textarea name="comment" maxlength="500"></textarea>
        </label>
        <button class="btn" type="submit">Potwierdzam rezerwację</button>
        <a class="btn btn-light" href="uslugi.php">Anuluj</a>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/includes/site_footer.php'; ?>
