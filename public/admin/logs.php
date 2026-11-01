<?php
require __DIR__ . '/_init.php';

$q = trim($_GET['q'] ?? '');
$perPage = 20;

$st = $pdo->prepare('SELECT COUNT(*) FROM admin_logs WHERE action LIKE ?');
$st->execute(['%' . $q . '%']);
[$page, $pages, $offset] = paginate((int)$st->fetchColumn(), $perPage, (int)($_GET['page'] ?? 1));

$rows = fetch_page($pdo,
    "SELECT l.created_at, l.action, l.target_table, l.target_id, CONCAT(u.name, ' ', u.surname) AS admin_name
     FROM admin_logs l JOIN users u ON u.id = l.admin_id
     WHERE l.action LIKE ? ORDER BY l.created_at DESC, l.id DESC",
    ['%' . $q . '%'], $perPage, $offset);

$pageTitle = 'Log operacji administratora';
$activeNav = 'logs';
require __DIR__ . '/_header.php';
?>
<form class="toolbar filters" method="get">
    <label>Szukaj w opisie <input type="text" name="q" value="<?= e($q) ?>"></label>
    <button class="btn btn-light" type="submit">Szukaj</button>
</form>
<div class="table-wrap">
<table>
    <thead><tr><th>Data</th><th>Administrator</th><th>Operacja</th><th>Tabela / ID</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="4" class="empty">Brak wpisów.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e($r['created_at']) ?></td>
            <td><?= e($r['admin_name']) ?></td>
            <td><?= e($r['action']) ?></td>
            <td class="muted"><?= e($r['target_table'] ?? '-') ?><?= $r['target_id'] !== null ? ' #' . (int)$r['target_id'] : '' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?= pagination_links($page, $pages) ?>
<?php require __DIR__ . '/_footer.php'; ?>
