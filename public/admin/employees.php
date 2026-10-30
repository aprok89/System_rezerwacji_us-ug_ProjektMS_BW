<?php
require __DIR__ . '/_init.php';

// ---- operacje POST: aktywność / usuwanie ----
if (is_post()) {
    csrf_verify();
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $st = $pdo->prepare('SELECT user_id FROM employees WHERE id = ?');
    $st->execute([$id]);
    $userId = $st->fetchColumn();
    $st = $pdo->prepare("SELECT CONCAT(name, ' ', surname) FROM users WHERE id = ?");
    $st->execute([(int)$userId]);
    $empName = (string)$st->fetchColumn();

    if ($userId === false) {
        flash('Nie znaleziono pracownika.', 'error');
    } elseif ($action === 'toggle') {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE employees SET active = 1 - active WHERE id = ?')->execute([$id]);
        $pdo->prepare('UPDATE users SET active = (SELECT active FROM employees WHERE id = ?) WHERE id = ?')
            ->execute([$id, $userId]);
        $pdo->commit();
        log_admin('Zmieniono aktywność pracownika: ' . $empName, 'employees', $id);
        flash('Zmieniono aktywność pracownika.');
    } elseif ($action === 'delete') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE employee_id = ?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE employees SET active = 0 WHERE id = ?')->execute([$id]);
            $pdo->prepare('UPDATE users SET active = 0 WHERE id = ?')->execute([$userId]);
            $pdo->commit();
            log_admin('Dezaktywowano pracownika (ma rezerwacje): ' . $empName, 'employees', $id);
            flash('Pracownik ma rezerwacje, dlatego został tylko dezaktywowany.', 'error');
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM employee_availability WHERE employee_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM employee_services WHERE employee_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM employees WHERE id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
                $pdo->commit();
                log_admin('Usunięto pracownika: ' . $empName, 'employees', $id);
                flash('Pracownik został usunięty.');
            } catch (PDOException $ex) {
                $pdo->rollBack();
                error_log($ex->getMessage());
                flash('Nie można usunąć pracownika (powiązane dane). Dezaktywuj go.', 'error');
            }
        }
    }
    redirect('employees.php');
}

// ---- lista ----
$q = trim($_GET['q'] ?? '');
$like = '%' . $q . '%';
$whereSql = "(u.name LIKE ? OR u.surname LIKE ? OR u.email LIKE ?)";
$params = [$like, $like, $like];
$perPage = 10;

$st = $pdo->prepare("SELECT COUNT(*) FROM employees em JOIN users u ON u.id = em.user_id WHERE $whereSql");
$st->execute($params);
[$page, $pages, $offset] = paginate((int)$st->fetchColumn(), $perPage, (int)($_GET['page'] ?? 1));

$rows = fetch_page($pdo,
    "SELECT em.id, em.active, u.name, u.surname, u.email, u.phone,
            (SELECT COUNT(*) FROM employee_services es WHERE es.employee_id = em.id) AS services_count,
            (SELECT COUNT(*) FROM employee_availability ea WHERE ea.employee_id = em.id) AS avail_count
     FROM employees em JOIN users u ON u.id = em.user_id
     WHERE $whereSql ORDER BY u.surname, u.name",
    $params, $perPage, $offset);

$pageTitle = 'Pracownicy';
$activeNav = 'employees';
require __DIR__ . '/_header.php';
?>
<div class="toolbar">
    <form class="filters grow" method="get">
        <label>Szukaj <input type="text" name="q" value="<?= e($q) ?>" placeholder="Imię, nazwisko lub e-mail"></label>
        <button class="btn btn-light" type="submit">Szukaj</button>
    </form>
    <a class="btn" href="employee_form.php">+ Dodaj pracownika</a>
</div>

<div class="table-wrap">
<table>
    <thead><tr><th>Pracownik</th><th>Kontakt</th><th>Usługi</th><th>Dostępność</th><th>Stan</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="6" class="empty">Brak pracowników.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e($r['name'] . ' ' . $r['surname']) ?></td>
            <td><?= e($r['email']) ?><br><span class="muted"><?= e($r['phone']) ?></span></td>
            <td><?= (int)$r['services_count'] ?></td>
            <td><?= (int)$r['avail_count'] ? (int)$r['avail_count'] . ' dni/bloków' : '<span class="muted">nie ustawiona</span>' ?></td>
            <td><span class="badge badge-<?= $r['active'] ? 'on' : 'off' ?>"><?= $r['active'] ? 'Aktywny' : 'Nieaktywny' ?></span></td>
            <td class="actions">
                <a class="btn btn-sm btn-light" href="employee_form.php?id=<?= (int)$r['id'] ?>">Edytuj</a>
                <a class="btn btn-sm btn-light" href="employee_availability.php?id=<?= (int)$r['id'] ?>">Dostępność</a>
                <a class="btn btn-sm btn-light" href="absences.php?id=<?= (int)$r['id'] ?>">Urlopy</a>
                <form class="inline" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="toggle">
                    <button class="btn btn-sm btn-light" type="submit"><?= $r['active'] ? 'Dezaktywuj' : 'Aktywuj' ?></button>
                </form>
                <form class="inline" method="post" data-confirm="Na pewno usunąć pracownika <?= e($r['name'] . ' ' . $r['surname']) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <button class="btn btn-sm btn-danger" type="submit">Usuń</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?= pagination_links($page, $pages) ?>
<?php require __DIR__ . '/_footer.php'; ?>
