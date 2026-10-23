<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';

$errors = [];
$form = ['name' => '', 'surname' => '', 'email' => '', 'phone' => ''];

if (is_post()) {
    csrf_verify();
    foreach ($form as $k => $_) {
        $v = $_POST[$k] ?? '';
        $form[$k] = is_string($v) ? trim($v) : '';
    }
    $password  = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $password2 = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';

    if (mb_strlen($form['name']) < 2 || mb_strlen($form['name']) > 50)       $errors[] = 'Imię musi mieć od 2 do 50 znaków.';
    if (mb_strlen($form['surname']) < 2 || mb_strlen($form['surname']) > 50) $errors[] = 'Nazwisko musi mieć od 2 do 50 znaków.';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 100) $errors[] = 'Podaj poprawny adres e-mail.';
    if (!preg_match('/^[0-9 +\-]{9,20}$/', $form['phone']))                  $errors[] = 'Podaj poprawny numer telefonu.';
    if (strlen($password) < 8)                                               $errors[] = 'Hasło musi mieć co najmniej 8 znaków.';
    if ($password !== $password2)                                            $errors[] = 'Hasła nie są takie same.';

    if (!$errors) {
        $st = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $st->execute([$form['email']]);
        if ($st->fetchColumn() > 0) {
            $errors[] = 'Konto z tym adresem e-mail już istnieje.';
        }
    }

    if (!$errors) {
        try {
            db()->prepare("INSERT INTO users (name, surname, email, password, phone, role) VALUES (?,?,?,?,?, 'client')")
                ->execute([$form['name'], $form['surname'], $form['email'], password_hash($password, PASSWORD_DEFAULT), $form['phone']]);
            flash('Konto zostało utworzone. Możesz się zalogować.');
            redirect('login.php');
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            $errors[] = 'Nie udało się utworzyć konta. Spróbuj ponownie.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rejestracja</title>
    <link rel="stylesheet" href="admin/assets/admin.css">
</head>
<body class="login-page">
<main class="login-box">
    <h1>Rejestracja</h1>
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <label>Imię <input type="text" name="name" maxlength="50" value="<?= e($form['name']) ?>" required></label>
        <label>Nazwisko <input type="text" name="surname" maxlength="50" value="<?= e($form['surname']) ?>" required></label>
        <label>E-mail <input type="email" name="email" maxlength="100" value="<?= e($form['email']) ?>" required></label>
        <label>Telefon <input type="text" name="phone" maxlength="20" value="<?= e($form['phone']) ?>" required></label>
        <label>Hasło (min. 8 znaków) <input type="password" name="password" autocomplete="new-password" required></label>
        <label>Powtórz hasło <input type="password" name="password2" autocomplete="new-password" required></label>
        <button class="btn" type="submit">Załóż konto</button>
    </form>
    <p class="muted"><a href="login.php">Masz już konto? Zaloguj się</a></p>
</main>
</body>
</html>
