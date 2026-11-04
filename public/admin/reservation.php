<?php
require __DIR__ . '/_init.php';


$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';
$status   = $_GET['status'] ?? '';
$employee = (int)($_GET['employee'] ?? 0);
$q        = trim($_GET['q'] ?? '');

$validDate = fn(string $d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
if (!$validDate($dateFrom)) $dateFrom = '';
if (!$validDate($dateTo))   $dateTo = '';
if (!isset(RESERVATION_STATUSES[$status])) $status = '';

$where = []; $params = [];
if ($dateFrom !== '') { $where[] = 'r.reservation_date >= ?'; $params[] = $dateFrom; }
if ($dateTo !== '')   { $where[] = 'r.reservation_date <= ?'; $params[] = $dateTo; }
if ($status !== '')   { $where[] = 'r.status = ?';            $params[] = $status; }
if ($employee > 0)    { $where[] = 'r.employee_id = ?';       $params[] = $employee; }
if ($q !== '') {
    $where[] = "(CONCAT(c.name, ' ', c.surname) LIKE ? OR c.email LIKE ? OR s.name LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$from = 'FROM reservations r
         JOIN services s   ON s.id  = r.service_id
         JOIN users c      ON c.id  = r.user_id
         JOIN employees em ON em.id = r.employee_id
         JOIN users eu     ON eu.id = em.user_id';

$perPage = 15;
$st = $pdo->prepare("SELECT COUNT(*) $from $whereSql");
$st->execute($params);
[$page, $pages, $offset] = paginate((int)$st->fetchColumn(), $perPage, (int)($_GET['page'] ?? 1));

$rows = fetch_page($pdo,
    "SELECT r.id, r.reservation_date, r.start_time, r.end_time, r.status,
            s.name AS service_name, s.price,
            CONCAT(c.name, ' ', c.surname) AS client_name,
            CONCAT(eu.name, ' ', eu.surname) AS employee_name
     $from $whereSql ORDER BY r.reservation_date DESC, r.start_time DESC",
    $params, $perPage, $offset);

$employees = $pdo->query(
    'SELECT em.id, CONCAT(u.name, " ", u.surname) AS full_name
     FROM employees em JOIN users u ON u.id = em.user_id ORDER BY u.surname, u.name')->fetchAll();

$pageTitle = 'Rezerwacje';
$activeNav = 'reservations';
require __DIR__ . '/_header.php';
?>
<form class="card filters" method="get">
    <label>Od daty <input type="date" name="date_from" value="<?= e($dateFrom) ?>"></label>
    <label>Do daty <input type="date" name="date_to" value="<?= e($dateTo) ?>"></label>
    <label>Status
        <select name="status">
            <option value="">Wszystkie</option>
            <?php foreach (RESERVATION_STATUSES as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Pracownik
        <select name="employee">
            <option value="0">Wszyscy</option>
            <?php foreach ($employees as $emp): ?>
                <option value="<?= (int)$emp['id'] ?>"<?= $employee === (int)$emp['id'] ? ' selected' : '' ?>><?= e($emp['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Szukaj <input type="text" name="q" value="<?= e($q) ?>" placeholder="Klient lub usługa"></label>
    <button class="btn" type="submit">Filtruj</button>
    <a class="btn btn-light" href="reservations.php">Wyczyść</a>
</form>

<div class="table-wrap">
<table>
    <thead><tr><th>#</th><th>Data</th><th>Godzina</th><th>Klient</th><th>Usługa</th><th>Pracownik</th><th>Cena</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="9" class="empty">Brak rezerwacji dla wybranych filtrów.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['reservation_date']) ?></td>
            <td><?= e(substr($r['start_time'], 0, 5)) ?>-<?= e(substr($r['end_time'], 0, 5)) ?></td>
            <td><?= e($r['client_name']) ?></td>
            <td><?= e($r['service_name']) ?></td>
            <td><?= e($r['employee_name']) ?></td>
            <td><?= number_format((float)$r['price'], 2, ',', ' ') ?> zł</td>
            <td><?= status_badge($r['status']) ?></td>
            <td class="actions"><a class="btn btn-sm btn-light" href="reservation_view.php?id=<?= (int)$r['id'] ?>">Szczegóły</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?= pagination_links($page, $pages) ?>
<?php require __DIR__ . '/_footer.php'; ?>
