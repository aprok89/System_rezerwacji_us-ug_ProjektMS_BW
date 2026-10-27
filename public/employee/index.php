<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';

//Tylko dla pracownika jak klient wpisze to ręczne to wywali error chyba 403.
$user = require_role(['employee'], '../login.php');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel pracownika</title>
    <link rel="stylesheet" href="../admin/assets/admin.css">
</head>
<body>
<header class="topbar">
    <strong class="brand">Panel pracownika</strong>
    <nav class="menu"></nav>
    <span class="user"><?= e($user['name'] . ' ' . $user['surname']) ?>
        <a href="../profil.php">Moje konto</a>
        <a href="../logout.php">Wyloguj</a></span>
</header>
<main class="container">
    <h1>Panel pracownika</h1>
    <div class="card">
        <p>Witaj, <?= e($user['name']) ?>! Lista wizyt i zmiana statusów pojawią się tu w etapie 4.</p>
    </div>
</main>
</body>
</html>
