<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
require_once __DIR__ . '/database.php';


const RESERVATION_STATUSES = [
    'oczekująca'   => 'Oczekująca',
    'potwierdzona' => 'Potwierdzona',
    'zrealizowana' => 'Zrealizowana',
    'anulowana'    => 'Anulowana',
];


const STATUS_CLASSES = [
    'oczekująca'   => 'pending',
    'potwierdzona' => 'confirmed',
    'zrealizowana' => 'completed',
    'anulowana'    => 'cancelled',
];

function status_badge(string $status): string
{
    $class = STATUS_CLASSES[$status] ?? 'off';
    $label = RESERVATION_STATUSES[$status] ?? $status;
    return '<span class="badge badge-' . $class . '">' . e($label) . '</span>';
}

const DAYS = [
    1 => 'Poniedziałek', 2 => 'Wtorek', 3 => 'Środa', 4 => 'Czwartek',
    5 => 'Piątek', 6 => 'Sobota', 7 => 'Niedziela',
];


function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}



function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function get_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}



function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}


function csrf_verify(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(403);
        exit('Nieprawidłowy token formularza. Odśwież stronę i spróbuj ponownie.');
    }
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}




function require_role(array $roles, string $loginUrl = '../login.php'): array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        redirect($loginUrl);
    }

    $st = db()->prepare('SELECT id, name, surname, email, phone, role, active FROM users WHERE id = ?');
    $st->execute([(int)$id]);
    $user = $st->fetch();

    if (!$user || !(int)$user['active']) {
        $_SESSION = [];
        redirect($loginUrl);
    }
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('<!DOCTYPE html><meta charset="utf-8"><title>403</title>'
           . '<p>Brak dostępu. Twoje konto nie ma uprawnień do tej strony.</p>'
           . '<p><a href="' . e($loginUrl) . '">Wróć do logowania</a></p>');
    }
    $_SESSION['role'] = $user['role'];
    return $user;
}


function require_login(string $loginUrl = 'login.php'): array
{
    return require_role(['client', 'employee', 'admin'], $loginUrl);
}


function require_admin(): array
{
    return require_role(['admin']);
}



const LOGIN_MAX_ATTEMPTS   = 5;
const LOGIN_WINDOW_MINUTES = 15;

function login_is_blocked(string $email): bool
{
    try {
        $st = db()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = ? AND attempted_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE)');
        $st->execute([mb_substr(mb_strtolower($email), 0, 100)]);
        return (int)$st->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
    } catch (PDOException $ex) {
        error_log('login_attempts: ' . $ex->getMessage()); // brak tabeli nie może zablokować logowania
        return false;
    }
}

function login_register_failure(string $email): void
{
    try {
        $pdo = db();
        $pdo->prepare('INSERT INTO login_attempts (email, ip) VALUES (?, ?)')
            ->execute([mb_substr(mb_strtolower($email), 0, 100), substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45)]);
        $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)'); // sprzątanie
    } catch (PDOException $ex) {
        error_log('login_attempts: ' . $ex->getMessage());
    }
}

function login_clear_failures(string $email): void
{
    try {
        db()->prepare('DELETE FROM login_attempts WHERE email = ?')
            ->execute([mb_substr(mb_strtolower($email), 0, 100)]);
    } catch (PDOException $ex) {
        error_log('login_attempts: ' . $ex->getMessage());
    }
}



function log_admin(string $action, ?string $table = null, ?int $targetId = null): void
{
    try {
        $st = db()->prepare('INSERT INTO admin_logs (admin_id, action, target_table, target_id) VALUES (?, ?, ?, ?)');
        $st->execute([(int)($_SESSION['user_id'] ?? 0), mb_substr($action, 0, 255), $table, $targetId]);
    } catch (PDOException $ex) {
        error_log('admin_logs: ' . $ex->getMessage()); 
    }
}


function row_name(string $table, int $id): string
{
    if (!in_array($table, ['service_categories', 'services'], true)) {
        return '#' . $id;
    }
    $st = db()->prepare("SELECT name FROM $table WHERE id = ?");
    $st->execute([$id]);
    $name = $st->fetchColumn();
    return $name !== false ? (string)$name : '#' . $id;
}



function paginate(int $total, int $perPage, int $page): array
{
    $pages  = max(1, (int)ceil($total / $perPage));
    $page   = min(max(1, $page), $pages);
    $offset = ($page - 1) * $perPage;
    return [$page, $pages, $offset];
}

function pagination_links(int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $url = '?' . http_build_query(array_merge($_GET, ['page' => $i]));
        $cls = $i === $page ? ' class="current"' : '';
        $html .= '<a href="' . e($url) . '"' . $cls . '>' . $i . '</a>';
    }
    return $html . '</nav>';
}

function fetch_page(PDO $pdo, string $sql, array $params, int $perPage, int $offset): array
{
    $st = $pdo->prepare($sql . ' LIMIT ? OFFSET ?');
    $i = 1;
    foreach ($params as $p) {
        $st->bindValue($i++, $p);
    }
    $st->bindValue($i++, $perPage, PDO::PARAM_INT);
    $st->bindValue($i, $offset, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}
