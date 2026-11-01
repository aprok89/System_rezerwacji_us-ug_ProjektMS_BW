<?php
require __DIR__ . '/_init.php';


if (is_post()) {
    csrf_verify();
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $svcName = row_name('services', $id);

    if ($action === 'toggle') {
        $pdo->prepare('UPDATE services SET active = 1 - active WHERE id = ?')->execute([$id]);
        log_admin('Zmieniono aktywność usługi: ' . $svcName, 'services', $id);
        flash('Zmieniono aktywność usługi.');
    } elseif ($action === 'delete') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE service_id = ?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            // usługa z historią rezerwacji - nie kasujemy, tylko dezaktywujemy
            $pdo->prepare('UPDATE services SET active = 0 WHERE id = ?')->execute([$id]);
            log_admin('Dezaktywowano usługę (ma rezerwacje): ' . $svcName, 'services', $id);
            flash('Usługa ma rezerwacje, dlatego została tylko dezaktywowana.', 'error');
        } else {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM employee_services WHERE service_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM services WHERE id = ?')->execute([$id]);
            $pdo->commit();
            log_admin('Usunięto usługę: ' . $svcName, 'services', $id);
            flash('Usługa została usunięta.');
        }
    }
    redirect('services.php?' . http_build_query(array_filter([
        'q' => $_POST['q'] ?? '', 'category' => $_POST['category'] ?? '', 'page' => $_POST['page'] ?? '',
    ])));
}


$q        = trim($_GET['q'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$state    = $_GET['state'] ?? '';

$where = ['s.name LIKE ?'];
$params = ['%' . $q . '%'];
if ($category > 0) { $where[] = 's.category_id = ?'; $params[] = $category; }
if ($state === 'active')   { $where[] = 's.active = 1'; }
if ($state === 'inactive') { $where[] = 's.active = 0'; }
$whereSql = implode(' AND ', $where);

$perPage = 10;
$st = $pdo->prepare("SELECT COUNT(*) FROM services s WHERE $whereSql");
$st->execute($params);
[$page, $pages, $offset] = paginate((int)$st->fetchColumn(), $perPage, (int)($_GET['page'] ?? 1));

$rows = fetch_page($pdo,
    "SELECT s.id, s.name, s.duration, s.price, s.active, c.name AS category_name
     FROM services s JOIN service_categories c ON c.id = s.category_id
     WHERE $whereSql ORDER BY c.name, s.name",
    $params, $perPage, $offset);

$categories = $pdo->query('SELECT id, name FROM service_categories ORDER BY name')->fetchAll();

$pageTitle = 'Usługi';
$activeNav = 'services';
require __DIR__ . '/_header.php';
?>
<div class="toolbar">
    <form class="filters grow" method="get">
        <label>Szukaj <input type="text" name="q" value="<?= e($q) ?>" placeholder="Nazwa usługi"></label>
        <label>Kategoria
            <select name="category">
                <option value="0">Wszystkie</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"<?= $category === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Stan
            <select name="state">
                <option value="">Wszystkie</option>
                <option value="active"<?= $state === 'active' ? ' selected' : '' ?>>Aktywne</option>
                <option value="inactive"<?= $state === 'inactive' ? ' selected' : '' ?>>Nieaktywne</option>
            </select>
        </label>
        <button class="btn btn-light" type="submit">Filtruj</button>
    </form>
    <a class="btn" href="service_form.php">+ Dodaj usługę</a>
</div>

<div class="table-wrap">
<table>
    <thead><tr><th>Nazwa</th><th>Kategoria</th><th>Czas</th><th>Cena</th><th>Stan</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="6" class="empty">Brak usług.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e($r['name']) ?></td>
            <td><?= e($r['category_name']) ?></td>
            <td><?= (int)$r['duration'] ?> min</td>
            <td><?= number_format((float)$r['price'], 2, ',', ' ') ?> zł</td>
            <td><span class="badge badge-<?= $r['active'] ? 'on' : 'off' ?>"><?= $r['active'] ? 'Aktywna' : 'Nieaktywna' ?></span></td>
            <td class="actions">
                <a class="btn btn-sm btn-light" href="service_form.php?id=<?= (int)$r['id'] ?>">Edytuj</a>
                <form class="inline" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="q" value="<?= e($q) ?>">
                    <input type="hidden" name="category" value="<?= $category ?: '' ?>">
                    <input type="hidden" name="page" value="<?= $page ?>">
                    <button class="btn btn-sm btn-light" type="submit"><?= $r['active'] ? 'Dezaktywuj' : 'Aktywuj' ?></button>
                </form>
                <form class="inline" method="post" data-confirm="Na pewno usunąć usługę „<?= e($r['name']) ?>”?">
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
