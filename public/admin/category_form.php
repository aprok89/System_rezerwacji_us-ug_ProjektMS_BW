<?php
require __DIR__ . '/_init.php';

$id  = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$cat = ['name' => '', 'description' => ''];

if ($id) {
    $st = $pdo->prepare('SELECT * FROM service_categories WHERE id = ?');
    $st->execute([$id]);
    $cat = $st->fetch();
    if (!$cat) {
        flash('Nie znaleziono kategorii.', 'error');
        redirect('categories.php');
    }
}

$errors = [];
if (is_post()) {
    csrf_verify();
    $cat['name']        = trim($_POST['name'] ?? '');
    $cat['description'] = trim($_POST['description'] ?? '');

    $len = mb_strlen($cat['name']);
    if ($len < 2 || $len > 100) {
        $errors[] = 'Nazwa musi mieć od 2 do 100 znaków.';
    }
    if (mb_strlen($cat['description']) > 500) {
        $errors[] = 'Opis może mieć maksymalnie 500 znaków.';
    }
    if (!$errors) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM service_categories WHERE name = ? AND id <> ?');
        $st->execute([$cat['name'], $id]);
        if ($st->fetchColumn() > 0) {
            $errors[] = 'Kategoria o takiej nazwie już istnieje.';
        }
    }

    if (!$errors) {
        try {
            if ($id) {
                $pdo->prepare('UPDATE service_categories SET name = ?, description = ? WHERE id = ?')
                    ->execute([$cat['name'], $cat['description'], $id]);
                log_admin('Zmieniono kategorię: ' . $cat['name'], 'service_categories', $id);
                flash('Kategoria została zaktualizowana.');
            } else {
                $pdo->prepare('INSERT INTO service_categories (name, description) VALUES (?, ?)')
                    ->execute([$cat['name'], $cat['description']]);
                $newId = (int)$pdo->lastInsertId();
                log_admin('Dodano kategorię: ' . $cat['name'], 'service_categories', $newId);
                flash('Kategoria została dodana.');
            }
            redirect('categories.php');
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            $errors[] = 'Nie udało się zapisać danych. Spróbuj ponownie.';
        }
    }
}

$pageTitle = $id ? 'Edycja kategorii' : 'Nowa kategoria';
$activeNav = 'categories';
require __DIR__ . '/_header.php';
?>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form class="card form-narrow" method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <label>Nazwa
        <input type="text" name="name" maxlength="100" value="<?= e($cat['name']) ?>" required>
    </label>
    <label>Opis
        <textarea name="description" maxlength="500"><?= e($cat['description']) ?></textarea>
    </label>
    <button class="btn" type="submit">Zapisz</button>
    <a class="btn btn-light" href="categories.php">Anuluj</a>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
