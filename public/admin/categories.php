<?php
require __DIR__ . '/_init.php';


if (is_post()) {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);

    $st = $pdo->prepare('SELECT COUNT(*) FROM services WHERE category_id = ?');
    $st->execute([$id]);
    if ((int)$st->fetchColumn() > 0) {
        flash('Nie można usunąć kategorii, która ma przypisane usługi.', 'error');
    } else {
        $catName = row_name('service_categories', $id);
        $pdo->prepare('DELETE FROM service_categories WHERE id = ?')->execute([$id]);
        log_admin('Usunięto kategorię: ' . $catName, 'service_categories', $id);
        flash('Kategoria została usunięta.');
    }
    redirect('categories.php');
}


$q       = trim($_GET['q'] ?? '');
$perPage = 10;

$st = $pdo->prepare('SELECT COUNT(*) FROM service_categories WHERE name LIKE ?');
$st->execute(['%' . $q . '%']);
[$page, $pages, $offset] = paginate((int)$st->fetchColumn(), $perPage, (int)($_GET['page'] ?? 1));

$rows = fetch_page($pdo,
    'SELECT c.id, c.name, c.description,
            (SELECT COUNT(*) FROM services s WHERE s.category_id = c.id) AS services_count
     FROM service_categories c WHERE c.name LIKE ? ORDER BY c.name',
    ['%' . $q . '%'], $perPage, $offset);

$pageTitle = 'Kategorie usług';
$activeNav = 'categories';
require __DIR__ . '/_header.php';
?>
<div class="toolbar">
    <form class="filters grow" method="get">
        <label>Szukaj <input type="text" name="q" value="<?= e($q) ?>" placeholder="Nazwa kategorii"></label>
        <button class="btn btn-light" type="submit">Szukaj</button>
    </form>
    <a class="btn" href="category_form.php">+ Dodaj kategorię</a>
</div>

<div class="table-wrap">
<table>
    <thead><tr><th>Nazwa</th><th>Opis</th><th>Usługi</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="4" class="empty">Brak kategorii.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e($r['name']) ?></td>
            <td><?= e($r['description']) ?></td>
            <td><?= (int)$r['services_count'] ?></td>
            <td class="actions">
                <a class="btn btn-sm btn-light" href="category_form.php?id=<?= (int)$r['id'] ?>">Edytuj</a>
                <form class="inline" method="post" data-confirm="Na pewno usunąć kategorię „<?= e($r['name']) ?>”?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
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
