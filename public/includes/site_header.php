<?php
$siteRole = $_SESSION['role'] ?? null;
$siteName = $_SESSION['name'] ?? null;
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/site.css">
</head>
<body>
<header class="topbar">
    <strong class="brand">Gabinet masażu</strong>
    <nav class="menu">
        <a href="index.html">Strona główna</a>
        <a href="uslugi.php"<?= $activeNav === 'offer' ? ' class="active"' : '' ?>>Oferta</a>
        <?php if ($siteRole === 'client'): ?>
            <a href="moje_rezerwacje.php"<?= $activeNav === 'mine' ? ' class="active"' : '' ?>>Moje rezerwacje</a>
        <?php elseif ($siteRole === 'employee'): ?>
            <a href="employee/index.php">Panel pracownika</a>
        <?php elseif ($siteRole === 'admin'): ?>
            <a href="admin/index.php">Panel administratora</a>
        <?php endif; ?>
    </nav>
    <span class="user">
        <?php if ($siteRole): ?>
            <?= e((string)$siteName) ?>
            <a href="profil.php">Moje konto</a>
            <a href="logout.php">Wyloguj</a>
        <?php else: ?>
            <a href="login.php">Zaloguj</a>
            <a href="rejestracja.php">Zarejestruj</a>
        <?php endif; ?>
    </span>
</header>
<main class="container">
    <h1><?= e($pageTitle) ?></h1>
    <?php foreach (get_flash() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
