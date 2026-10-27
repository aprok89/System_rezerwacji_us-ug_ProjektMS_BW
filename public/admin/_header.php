<?php

$nav = [
    'dashboard'    => ['index.php',        'Dashboard'],
    'categories'   => ['categories.php',   'Kategorie'],
    'services'     => ['services.php',     'Usługi'],
    'employees'    => ['employees.php',    'Pracownicy'],
    'reservations' => ['reservations.php', 'Rezerwacje'],
    'logs'         => ['logs.php',         'Log operacji'],
];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - Panel administratora</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<header class="topbar">
    <strong class="brand">Panel administratora</strong>
    <nav class="menu">
        <?php foreach ($nav as $key => [$href, $label]): ?>
            <a href="<?= e($href) ?>"<?= $key === $activeNav ? ' class="active"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <span class="user">
        <?= e($admin['name'] . ' ' . $admin['surname']) ?>
        <a href="../profil.php">Moje konto</a>
        <a href="../logout.php">Wyloguj</a>
    </span>
</header>
<main class="container">
    <h1><?= e($pageTitle) ?></h1>
    <?php foreach (get_flash() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
