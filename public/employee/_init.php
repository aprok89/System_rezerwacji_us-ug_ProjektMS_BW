<?php
declare(strict_types=1);

require_once DIR . '/../includes/auth.php';
require_once DIR . '/../includes/booking.php';

$user = require_role(['employee'], '../login.php');
$pdo  = db();


$st = $pdo->prepare('SELECT id FROM employees WHERE user_id = ? AND active = 1');
$st->execute([$user['id']]);
$empId = (int)$st->fetchColumn();
if ($empId === 0) {
    http_response_code(403);
    exit('Twoje konto pracownika jest nieaktywne lub nie ma profilu pracownika. Skontaktuj się z administratorem.');
}
