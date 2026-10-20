<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';

$user = require_login('login.php'); 
$pdo  = db();

$homeByRole = ['admin' => 'admin/index.php', 'employee' => 'employee/index.php', 'client' => 'index.html'];
$home = $homeByRole[$user['role']] ?? 'index.html';
$roleLabels = ['admin' => 'Administrator', 'employee' => 'Pracownik', 'client' => 'Klient'];

$form = [
    'name' => $user['name'], 'surname' => $user['surname'],
    'email' => $user['email'], 'phone' => $user['phone'] ?? '',
];
$dataErrors = [];
$pwdErrors  = [];

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';


    if ($action === 'profile') {
        foreach ($form as $k => $_) {
            $v = $_POST[$k] ?? '';
            $form[$k] = is_string($v) ? trim($v) : '';
        }
        if (mb_strlen($form['name']) < 2 || mb_strlen($form['name']) > 50)       $dataErrors[] = 'Imię musi mieć od 2 do 50 znaków.';
        if (mb_strlen($form['surname']) < 2 || mb_strlen($form['surname']) > 50) $dataErrors[] = 'Nazwisko musi mieć od 2 do 50 znaków.';
        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 100) $dataErrors[] = 'Podaj poprawny adres e-mail.';
        if (!preg_match('/^[0-9 +\-]{9,20}$/', $form['phone']))                  $dataErrors[] = 'Podaj poprawny numer telefonu.';

        if (!$dataErrors) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
            $st->execute([$form['email'], $user['id']]);
            if ($st->fetchColumn() > 0) $dataErrors[] = 'Ten adres e-mail jest już zajęty.';
        }
        if (!$dataErrors) {
            try {
                $pdo->prepare('UPDATE users SET name = ?, surname = ?, email = ?, phone = ? WHERE id = ?')
                    ->execute([$form['name'], $form['surname'], $form['email'], $form['phone'], $user['id']]);
                $_SESSION['name'] = $form['name'];
                flash('Dane zostały zapisane.');
                redirect('profil.php');
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                $dataErrors[] = 'Nie udało się zapisać danych. Spróbuj ponownie.';
            }
        }
    }


    if ($action === 'password') {
        $old  = is_string($_POST['old_password'] ?? null) ? $_POST['old_password'] : '';
        $new  = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $new2 = is_string($_POST['new_password2'] ?? null) ? $_POST['new_password2'] : '';

        $st = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $st->execute([$user['id']]);
        $hash = (string)$st->fetchColumn();

        if (!password_verify($old, $hash)) $pwdErrors[] = 'Aktualne hasło jest nieprawidłowe.';
        if (strlen($new) < 8)              $pwdErrors[] = 'Nowe hasło musi mieć co najmniej 8 znaków.';
        if ($new !== $new2)                $pwdErrors[] = 'Nowe hasła nie są takie same.';
        if ($new !== '' && $new === $old)  $pwdErrors[] = 'Nowe hasło musi różnić się od obecnego.';

        if (!$pwdErrors) {
            try {
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                    ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
                session_regenerate_id(true);
                flash('Hasło zostało zmienione.');
                redirect('profil.php');
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                $pwdErrors[] = 'Nie udało się zmienić hasła. Spróbuj ponownie.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Moje konto</title>
    <link rel="stylesheet" href="admin/assets/admin.css">
</head>
<body>
<header class="topbar">
    <strong class="brand">Moje konto</strong>
    <nav class="menu"><a href="<?= e($home) ?>">Strona główna</a></nav>
    <span class="user"><?= e($user['name'] . ' ' . $user['surname']) ?> (<?= e($roleLabels[$user['role']] ?? $user['role']) ?>)
        <a href="logout.php">Wyloguj</a></span>
</header>
<main class="container">
    <h1>Moje konto</h1>
    <?php foreach (get_flash() as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>

    <h2>Dane podstawowe</h2>
    <?php if ($dataErrors): ?><div class="alert alert-error"><ul><?php foreach ($dataErrors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form class="card form-narrow" method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="profile">
        <div class="grid2">
            <label>Imię <input type="text" name="name" maxlength="50" value="<?= e($form['name']) ?>" required></label>
            <label>Nazwisko <input type="text" name="surname" maxlength="50" value="<?= e($form['surname']) ?>" required></label>
            <label>E-mail <input type="email" name="email" maxlength="100" value="<?= e($form['email']) ?>" required></label>
            <label>Telefon <input type="text" name="phone" maxlength="20" value="<?= e($form['phone']) ?>" required></label>
        </div>
        <button class="btn" type="submit">Zapisz dane</button>
    </form>

    <h2>Zmiana hasła</h2>
    <?php if ($pwdErrors): ?><div class="alert alert-error"><ul><?php foreach ($pwdErrors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form class="card form-narrow" method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <label>Aktualne hasło <input type="password" name="old_password" autocomplete="current-password" required></label>
        <label>Nowe hasło (min. 8 znaków) <input type="password" name="new_password" autocomplete="new-password" required></label>
        <label>Powtórz nowe hasło <input type="password" name="new_password2" autocomplete="new-password" required></label>
        <button class="btn" type="submit">Zmień hasło</button>
    </form>
</main>
</body>
</html>
