<?php
require __DIR__ . '/_init.php';

$id  = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$emp = ['user_id' => 0, 'name' => '', 'surname' => '', 'email' => '', 'phone' => '', 'description' => '', 'active' => 1];
$selected = [];

if ($id) {
    $st = $pdo->prepare(
        'SELECT em.id, em.user_id, em.description, em.active, u.name, u.surname, u.email, u.phone
         FROM employees em JOIN users u ON u.id = em.user_id WHERE em.id = ?');
    $st->execute([$id]);
    $emp = $st->fetch();
    if (!$emp) {
        flash('Nie znaleziono pracownika.', 'error');
        redirect('employees.php');
    }
    $st = $pdo->prepare('SELECT service_id FROM employee_services WHERE employee_id = ?');
    $st->execute([$id]);
    $selected = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

$services = $pdo->query(
    'SELECT s.id, s.name, c.name AS category_name
     FROM services s JOIN service_categories c ON c.id = s.category_id
     ORDER BY c.name, s.name')->fetchAll();
$validServiceIds = array_map('intval', array_column($services, 'id'));

$errors = [];
if (is_post()) {
    csrf_verify();
    $emp['name']        = trim($_POST['name'] ?? '');
    $emp['surname']     = trim($_POST['surname'] ?? '');
    $emp['email']       = trim($_POST['email'] ?? '');
    $emp['phone']       = trim($_POST['phone'] ?? '');
    $emp['description'] = trim($_POST['description'] ?? '');
    $emp['active']      = isset($_POST['active']) ? 1 : 0;
    $password           = (string)($_POST['password'] ?? '');
    // tylko istniejące identyfikatory usług
    $selected = array_values(array_intersect(array_map('intval', (array)($_POST['services'] ?? [])), $validServiceIds));

    if (mb_strlen($emp['name']) < 2 || mb_strlen($emp['name']) > 50)       $errors[] = 'Imię musi mieć od 2 do 50 znaków.';
    if (mb_strlen($emp['surname']) < 2 || mb_strlen($emp['surname']) > 50) $errors[] = 'Nazwisko musi mieć od 2 do 50 znaków.';
    if (!filter_var($emp['email'], FILTER_VALIDATE_EMAIL))                  $errors[] = 'Podaj poprawny adres e-mail.';
    if (!preg_match('/^[0-9 +\-]{9,20}$/', $emp['phone']))                  $errors[] = 'Podaj poprawny numer telefonu (9-20 znaków: cyfry, spacje, +, -).';
    if (mb_strlen($emp['description']) > 100)                               $errors[] = 'Opis może mieć maksymalnie 100 znaków.';
    if (mb_strlen($emp['email']) > 100)                                     $errors[] = 'E-mail może mieć maksymalnie 100 znaków.';
    if (!$id && strlen($password) < 8)                                      $errors[] = 'Hasło musi mieć co najmniej 8 znaków.';
    if ($id && $password !== '' && strlen($password) < 8)                   $errors[] = 'Nowe hasło musi mieć co najmniej 8 znaków.';

    if (!$errors) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
        $st->execute([$emp['email'], (int)$emp['user_id']]);
        if ($st->fetchColumn() > 0) $errors[] = 'Ten adres e-mail jest już zajęty.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            if ($id) {
                $pdo->prepare('UPDATE users SET name=?, surname=?, email=?, phone=?, active=? WHERE id=?')
                    ->execute([$emp['name'], $emp['surname'], $emp['email'], $emp['phone'], $emp['active'], $emp['user_id']]);
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET password=? WHERE id=?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $emp['user_id']]);
                }
                $pdo->prepare('UPDATE employees SET description=?, active=? WHERE id=?')
                    ->execute([$emp['description'], $emp['active'], $id]);
                $empId = $id;
            } else {
                $pdo->prepare("INSERT INTO users (name, surname, email, password, phone, role, active) VALUES (?,?,?,?,?, 'employee', ?)")
                    ->execute([$emp['name'], $emp['surname'], $emp['email'], password_hash($password, PASSWORD_DEFAULT), $emp['phone'], $emp['active']]);
                $userId = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO employees (user_id, description, active) VALUES (?,?,?)')
                    ->execute([$userId, $emp['description'], $emp['active']]);
                $empId = (int)$pdo->lastInsertId();
            }

            // przypisanie wielu usług (relacja N:M)
            $pdo->prepare('DELETE FROM employee_services WHERE employee_id = ?')->execute([$empId]);
            $ins = $pdo->prepare('INSERT INTO employee_services (employee_id, service_id) VALUES (?, ?)');
            foreach ($selected as $sid) {
                $ins->execute([$empId, $sid]);
            }
            $pdo->commit();
            log_admin(($id ? 'Zmieniono dane pracownika: ' : 'Dodano pracownika: ') . $emp['name'] . ' ' . $emp['surname'], 'employees', $empId);

            flash($id ? 'Dane pracownika zostały zapisane.' : 'Pracownik został dodany. Ustaw jeszcze jego dostępność.');
            redirect($id ? 'employees.php' : 'employee_availability.php?id=' . $empId);
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($ex->getMessage());
            $errors[] = 'Nie udało się zapisać danych. Spróbuj ponownie.';
        }
    }
}

$pageTitle = $id ? 'Edycja pracownika' : 'Nowy pracownik';
$activeNav = 'employees';
require __DIR__ . '/_header.php';
?>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form class="card form-narrow" method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="grid2">
        <label>Imię <input type="text" name="name" maxlength="50" value="<?= e($emp['name']) ?>" required></label>
        <label>Nazwisko <input type="text" name="surname" maxlength="50" value="<?= e($emp['surname']) ?>" required></label>
        <label>E-mail (login) <input type="email" name="email" value="<?= e($emp['email']) ?>" required></label>
        <label>Telefon <input type="text" name="phone" value="<?= e($emp['phone']) ?>" required></label>
    </div>
    <label><?= $id ? 'Nowe hasło (zostaw puste, aby nie zmieniać)' : 'Hasło (min. 8 znaków)' ?>
        <input type="password" name="password" autocomplete="new-password"<?= $id ? '' : ' required' ?>>
    </label>
    <label>Opis <textarea name="description" maxlength="100"><?= e($emp['description']) ?></textarea></label>

    <label>Wykonywane usługi</label>
    <?php if (!$services): ?><p class="muted">Najpierw dodaj usługi.</p><?php endif; ?>
    <?php foreach ($services as $s): ?>
        <label class="check">
            <input type="checkbox" name="services[]" value="<?= (int)$s['id'] ?>"<?= in_array((int)$s['id'], $selected, true) ? ' checked' : '' ?>>
            <?= e($s['name']) ?> <span class="muted">(<?= e($s['category_name']) ?>)</span>
        </label>
    <?php endforeach; ?>

    <label class="check"><input type="checkbox" name="active" value="1"<?= $emp['active'] ? ' checked' : '' ?>> Pracownik aktywny</label>
    <button class="btn" type="submit">Zapisz</button>
    <a class="btn btn-light" href="employees.php">Anuluj</a>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
