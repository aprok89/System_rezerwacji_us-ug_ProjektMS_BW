<?php
declare(strict_types=1);
require DIR . '/_init.php';
require DIR . '/_table.php';

$view  = ($_GET['view'] ?? 'upcoming') === 'history' ? 'history' : 'upcoming';
$today = date('Y-m-d');

$cond  = $view === 'history' ? 'r.reservation_date < ?' : 'r.reservation_date > ?';
$order = $view === 'history' ? 'r.reservation_date DESC, r.start_time DESC' : 'r.reservation_date, r.start_time';
$perPage = 15;

$st = $pdo->prepare("SELECT COUNT(*) FROM reservations r WHERE r.employee_id = ? AND $cond");
$st->execute([$empId, $today]);
[$page, $pages, $offset] = paginate((int)$st->fetchColumn(), $perPage, (int)($_GET['page'] ?? 1));

$rows = fetch_page($pdo, VISITS_SELECT . " WHERE r.employee_id = ? AND $cond ORDER BY $order",
                   [$empId, $today], $perPage, $offset);

$pageTitle = $view === 'history' ? 'Historia wizyt' : 'Przyszłe wizyty';
$activeNav = $view;
require DIR . '/_header.php';
render_visits($rows);
echo pagination_links($page, $pages);
require DIR . '/_footer.php';
