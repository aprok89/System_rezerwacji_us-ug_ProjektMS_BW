<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';

// Przekierowanie do właściwego panelu zależnie od roli.
// Panel klienta powstanie w etapie 4
function redirect_by_role(string $role): void
{
    switch ($role) {
        case 'admin':    redirect('admin/index.php');
        case 'employee': redirect('employee/index.php');
        default:         redirect('profil.php'); 
    }
}

$error = '';
$email = '';

if (is_post()) {
    csrf_verify();
    $email    = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (login_is_blocked($email)) {
        $error = 'Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za ' . LOGIN_WINDOW_MINUTES . ' minut.';
    } else {
        $st = db()->prepare('SELECT id, name, password, role, active FROM users WHERE email = ?');
        $st->execute([$email]);
        $user = $st->fetch();


        if ($user && (int)$user['active'] && password_verify($password, $user['password'])) {
            login_clear_failures($email);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['name']    = $user['name'];
            redirect_by_role($user['role']);
        }
        login_register_failure($email);
        $error = 'Nieprawidłowy e-mail lub hasło.';
    }
}

if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    redirect_by_role($_SESSION['role']);
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Logowanie</title>
    <link rel="stylesheet" href="admin/assets/admin.css">
</head>
<body class="login-page">
<main class="login-box">
    <h1>Logowanie</h1>
    <?php foreach (get_flash() as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <label>E-mail
            <input type="email" name="email" value="<?= e($email) ?>" required autofocus>
        </label>
        <label>Hasło
            <input type="password" name="password" required>
        </label>
        <button class="btn" type="submit">Zaloguj się</button>
    </form>
    <p class="muted"><a href="rejestracja.php">Nie masz konta? Zarejestruj się</a></p>
</main>
</body>
</html>
