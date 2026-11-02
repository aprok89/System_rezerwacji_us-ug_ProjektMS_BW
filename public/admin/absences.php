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
$empName = $emp['name'] . ' ' . $emp['surname'];

$validDate = function (string $d): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
};

$errors = [];
$form = ['date_from' => '', 'date_to' => '', 'reason' => ''];

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $absId = (int)($_POST['absence_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM employee_absences WHERE id = ? AND employee_id = ?');
        $del->execute([$absId, $id]);
        if ($del->rowCount() > 0) {
            log_admin("Usunięto nieobecność pracownika: $empName", 'employee_absences', $absId);
            flash('Nieobecność została usunięta.');
        }
        redirect('absences.php?id=' . $id);
    }

    if ($action === 'add') {
        $form['date_from'] = trim($_POST['date_from'] ?? '');
        $form['date_to']   = trim($_POST['date_to'] ?? '');
        $form['reason']    = trim($_POST['reason'] ?? '');

        if (!$validDate($form['date_from']) || !$validDate($form['date_to'])) {
            $errors[] = 'Podaj poprawne daty.';
        } elseif ($form['date_to'] < $form['date_from']) {
            $errors[] = 'Data końcowa nie może być wcześniejsza niż początkowa.';
        }
        if (mb_strlen($form['reason']) > 255) {
            $errors[] = 'Powód może mieć maksymalnie 255 znaków.';
        }

        if (!$errors) {
            try {
                $pdo->prepare('INSERT INTO employee_absences (employee_id, date_from, date_to, reason) VALUES (?,?,?,?)')
                    ->execute([$id, $form['date_from'], $form['date_to'], $form['reason'] !== '' ? $form['reason'] : null]);
                $newId = (int)$pdo->lastInsertId();
                log_admin("Dodano nieobecność pracownika: $empName ({$form['date_from']} - {$form['date_to']})", 'employee_absences', $newId);

                $c = $pdo->prepare("SELECT COUNT(*) FROM reservations
                                    WHERE employee_id = ? AND status <> 'anulowana' AND reservation_date BETWEEN ? AND ?");
                $c->execute([$id, $form['date_from'], $form['date_to']]);
                $n = (int)$c->fetchColumn();

                flash('Nieobecność została dodana.');
                if ($n > 0) {
                    flash("Uwaga: w tym okresie pracownik ma jeszcze $n aktywnych rezerwacji - sprawdź je w zakładce Rezerwacje.", 'error');
                }
                redirect('absences.php?id=' . $id);
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                $errors[] = 'Nie udało się zapisać danych. Spróbuj ponownie.';
            }
        }
    }
}

$st = $pdo->prepare('SELECT id, date_from, date_to, reason FROM employee_absences WHERE employee_id = ? ORDER BY date_from DESC');
$st->execute([$id]);
$rows = $st->fetchAll();

$pageTitle = 'Nieobecności: ' . $empName;
$activeNav = 'employees';
require __DIR__ . '/_header.php';
?>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form class="card form-narrow" method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="action" value="add">
    <div class="grid2">
        <label>Od dnia <input type="date" name="date_from" value="<?= e($form['date_from']) ?>" required></label>
        <label>Do dnia <input type="date" name="date_to" value="<?= e($form['date_to']) ?>" required></label>
    </div>
    <label>Powód (opcjonalnie) <input type="text" name="reason" maxlength="255" value="<?= e($form['reason']) ?>"></label>
    <button class="btn" type="submit">Dodaj nieobecność</button>
    <a class="btn btn-light" href="employees.php">Wróć do pracowników</a>
</form>

<div class="table-wrap">
<table>
    <thead><tr><th>Od</th><th>Do</th><th>Powód</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="4" class="empty">Brak zapisanych nieobecności.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e($r['date_from']) ?></td>
            <td><?= e($r['date_to']) ?></td>
            <td><?= e($r['reason'] ?? '') ?></td>
            <td class="actions">
                <form class="inline" method="post" data-confirm="Usunąć ten wpis?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="absence_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-danger" type="submit">Usuń</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
