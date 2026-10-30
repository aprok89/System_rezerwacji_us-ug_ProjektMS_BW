<?php
require __DIR__ . '/_init.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$st = $pdo->prepare('SELECT em.id, u.name, u.surname FROM employees em JOIN users u ON u.id = em.user_id WHERE em.id = ?');
$st->execute([$id]);
$emp = $st->fetch();
if (!$emp) {
    flash('Nie znaleziono pracownika.', 'error');
    redirect('employees.php');
}

// aktualne godziny: [dzień => ['start' => 'HH:MM', 'end' => 'HH:MM']]
$hours = [];
$st = $pdo->prepare('SELECT day_of_week, start_time, end_time FROM employee_availability WHERE employee_id = ? ORDER BY day_of_week, start_time');
$st->execute([$id]);
foreach ($st->fetchAll() as $r) {
    $hours[(int)$r['day_of_week']] = ['start' => substr($r['start_time'], 0, 5), 'end' => substr($r['end_time'], 0, 5)];
}

$errors = [];
if (is_post()) {
    csrf_verify();
    $new = [];
    foreach (DAYS as $d => $dayName) {
        $start = trim($_POST['start'][$d] ?? '');
        $end   = trim($_POST['end'][$d] ?? '');
        if ($start === '' && $end === '') {
            continue; // dzień wolny
        }
        $okFormat = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end);
        if (!$okFormat) {
            $errors[] = "$dayName: podaj obie godziny w formacie GG:MM albo zostaw oba pola puste.";
        } elseif ($start >= $end) {
            $errors[] = "$dayName: godzina zakończenia musi być późniejsza niż rozpoczęcia.";
        }
        $new[$d] = ['start' => $start, 'end' => $end];
    }
    $hours = $new;

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM employee_availability WHERE employee_id = ?')->execute([$id]);
            $ins = $pdo->prepare('INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time) VALUES (?,?,?,?)');
            foreach ($hours as $d => $h) {
                $ins->execute([$id, $d, $h['start'] . ':00', $h['end'] . ':00']);
            }
            $pdo->commit();
            log_admin('Zmieniono dostępność pracownika: ' . $emp['name'] . ' ' . $emp['surname'], 'employee_availability', $id);
            flash('Dostępność pracownika została zapisana.');
            redirect('employees.php');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($ex->getMessage());
            $errors[] = 'Nie udało się zapisać danych. Spróbuj ponownie.';
        }
    }
}

$pageTitle = 'Dostępność: ' . $emp['name'] . ' ' . $emp['surname'];
$activeNav = 'employees';
require __DIR__ . '/_header.php';
?>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form class="card form-narrow" method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <p class="muted">Ustaw godziny pracy w poszczególne dni tygodnia. Puste pola = dzień wolny.</p>
    <?php foreach (DAYS as $d => $dayName): ?>
        <div class="grid2">
            <label><?= e($dayName) ?> - od <input type="time" name="start[<?= $d ?>]" value="<?= e($hours[$d]['start'] ?? '') ?>"></label>
            <label>do <input type="time" name="end[<?= $d ?>]" value="<?= e($hours[$d]['end'] ?? '') ?>"></label>
        </div>
    <?php endforeach; ?>
    <button class="btn" type="submit">Zapisz</button>
    <a class="btn btn-light" href="employees.php">Anuluj</a>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
