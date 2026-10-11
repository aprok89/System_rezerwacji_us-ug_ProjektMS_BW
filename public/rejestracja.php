<?php

require __DIR__ .'/db.php';

function e(string $tekst): string
{
    return htmlspecialchars($tekst, ENT_QUOTES, 'UTF-8');
}

function pole_tekstowe(string $nazwa): string
{
    $wartosc = $_POST[$nazwa] ?? '';
    return is_string($wartosc) ? trim($wartosc) : '';
}

$bledy = [];
$name = '';
$surname = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = pole_tekstowe('name');
    $surname = pole_tekstowe('surname');

    if ($name === '' || mb_strlen($name) > 100) {
        $bledy[] = 'Podaj imię (maksymalnie 100 znaków).';
    }
    if ($surname === '' || mb_strlen($surname) > 100) {
        $bledy[] = 'Podaj nazwisko (maksymalnie 100 znkaów).';
    }

    if (!$bledy) {
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, surname) VALUES (:name, :surname)'
        );
        $stmt->execute([':name' => $name, ':surname' => $surname]);

        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <?php foreach ($bledy as $blad): ?>
        <p style="color:red"><?= e($blad) ?></p>
    <?php endforeach; ?>

    <form method="POST">
        <label>Imię</label>
        <input type="text" name="name" value=<?= e($name) ?>>
        <label>Nazwisko</label>
        <input type="text" name="surname" value=<?= e($surname) ?>>
        <br>
        <button type="submit">Wyślij</button>
    </form>
</body>
</html>
