<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
$pdo = db();

$category = (int)($_GET['category'] ?? 0);

$categories = $pdo->query(
    'SELECT DISTINCT c.id, c.name
     FROM service_categories c JOIN services s ON s.category_id = c.id AND s.active = 1
     ORDER BY c.name')->fetchAll();

// Publicznie widoczne są wyłącznie AKTYWNE usługi.
$sql = 'SELECT s.id, s.name, s.description, s.duration, s.price, c.name AS category_name,
               (SELECT COUNT(*) FROM employee_services es
                  JOIN employees em ON em.id = es.employee_id
                  JOIN users u ON u.id = em.user_id
                WHERE es.service_id = s.id AND em.active = 1 AND u.active = 1) AS staff_count
        FROM services s JOIN service_categories c ON c.id = s.category_id
        WHERE s.active = 1';
$params = [];
if ($category > 0) {
    $sql .= ' AND s.category_id = ?';
    $params[] = $category;
}
$st = $pdo->prepare($sql . ' ORDER BY c.name, s.name');
$st->execute($params);

$grouped = [];
foreach ($st->fetchAll() as $row) {
    $grouped[$row['category_name']][] = $row;
}

$pageTitle = 'Oferta usług';
$activeNav = 'offer';
require __DIR__ . '/includes/site_header.php';
?>
<div class="filters-bar">
    <a class="chip<?= $category === 0 ? ' active' : '' ?>" href="uslugi.php">Wszystkie</a>
    <?php foreach ($categories as $c): ?>
        <a class="chip<?= $category === (int)$c['id'] ? ' active' : '' ?>" href="uslugi.php?category=<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
</div>

<?php if (!$grouped): ?>
    <div class="card"><p class="muted">Brak usług w wybranej kategorii.</p></div>
<?php endif; ?>

<?php foreach ($grouped as $catName => $services): ?>
    <h2><?= e($catName) ?></h2>
    <div class="cards">
    <?php foreach ($services as $s): ?>
        <article class="service-card">
            <h3><?= e($s['name']) ?></h3>
            <div class="meta"><?= (int)$s['duration'] ?> min</div>
            <p class="grow muted"><?= e(mb_strimwidth((string)$s['description'], 0, 110, '…')) ?></p>
            <div class="price"><?= number_format((float)$s['price'], 2, ',', ' ') ?> zł</div>
            <div class="buttons">
                <a class="btn btn-light btn-sm" href="usluga.php?id=<?= (int)$s['id'] ?>">Szczegóły</a>
                <?php if ((int)$s['staff_count'] > 0): ?>
                    <a class="btn btn-sm" href="rezerwacja.php?service=<?= (int)$s['id'] ?>">Zarezerwuj</a>
                <?php else: ?>
                    <span class="muted">Chwilowo niedostępna</span>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/site_footer.php'; ?>
