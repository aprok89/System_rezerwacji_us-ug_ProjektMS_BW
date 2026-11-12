<?php

$nav = [
    'today'    => ['index.php',                 'Dzisiaj'],
    'upcoming' => ['visits.php?view=upcoming',  'Przyszłe wizyty'],
    'history'  => ['visits.php?view=history',   'Historia'],
];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - Panel pracownika</title>
    <link rel="stylesheet" href="../admin/assets/admin.css">
</head>
<body>
<header class="topbar">
    <strong class="brand">Panel pracownika</strong>
    <nav class="menu">
        <?php foreach ($nav as $key => [$href, $label]): ?>
            <a href="<?= e($href) ?>"<?= $key === $activeNav ? ' class="active"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <span class="user">
        <?= e($user['name'] . ' ' . $user['surname']) ?>
        <a href="../profil.php">Moje konto</a>
        <a href="../logout.php">Wyloguj</a>
    </span>
</header>
<main class="container">
    <h1><?= e($pageTitle) ?></h1>
    <?php foreach (get_flash() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
