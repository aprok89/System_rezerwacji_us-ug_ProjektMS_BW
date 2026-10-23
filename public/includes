<?php
declare(strict_types=1);

function db(): PDO
{
    static $conn = null;
    if ($conn === null) {
        $load = static function (): PDO {
            require __DIR__ . '/../db.php';
            return $pdo;
        };
        $conn = $load();
    }
    return $conn;
}
