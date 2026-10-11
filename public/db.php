<?php
declare(strict_types=1);

const DB_DSN = 'mysql:host=localhost;dbname=masaz_rezerwacje;charset=utf8mb4';
const DB_USER = 'root';
const DB_PASS = '';

try {
    $pdo = new PDO (DB_DSN, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE    => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES      => false,
    ]);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    http_response_code(500);
    exit('Błąd połączenia z bazą danych.');
}
?>

