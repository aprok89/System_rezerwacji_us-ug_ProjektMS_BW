<?php
declare(strict_types=1);

const BOOKING_SLOT_STEP = 15;   // co ile minut może zaczynać się wizyta
const BOOKING_MAX_DAYS  = 90;   // na ile dni do przodu można rezerwować
const ACTIVE_STATUSES   = ['oczekująca', 'potwierdzona'];

const EMPLOYEE_TRANSITIONS = [
    'oczekująca'   => ['potwierdzona', 'anulowana'],
    'potwierdzona' => ['zrealizowana', 'anulowana'],
    'zrealizowana' => [],
    'anulowana'    => [],
];

function time_to_min(string $t): int
{
    [$h, $m] = array_map('intval', explode(':', $t));
    return $h * 60 + $m;
}

function min_to_time(int $minutes): string
{
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

function valid_date(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
}

function valid_time(string $t): bool
{
    return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);
}

function booking_max_date(): string
{
    return date('Y-m-d', strtotime('+' . BOOKING_MAX_DAYS . ' days'));
}

function booking_get_service(PDO $pdo, int $serviceId): array|false
{
    $st = $pdo->prepare(
        'SELECT s.*, c.name AS category_name
         FROM services s JOIN service_categories c ON c.id = s.category_id
         WHERE s.id = ? AND s.active = 1');
    $st->execute([$serviceId]);
    return $st->fetch();
}

function booking_get_employees(PDO $pdo, int $serviceId): array
{
    $st = $pdo->prepare(
        'SELECT em.id, em.description, u.name, u.surname
         FROM employees em
         JOIN users u ON u.id = em.user_id
         JOIN employee_services es ON es.employee_id = em.id
         WHERE es.service_id = ? AND em.active = 1 AND u.active = 1
         ORDER BY u.surname, u.name');
    $st->execute([$serviceId]);
    return $st->fetchAll();
}

function booking_get_employee(PDO $pdo, int $employeeId, int $serviceId): array|false
{
    foreach (booking_get_employees($pdo, $serviceId) as $e) {
        if ((int)$e['id'] === $employeeId) {
            return $e;
        }
    }
    return false;
}

function booking_intervals(PDO $pdo, int $employeeId, int $dayOfWeek): array
{
    $st = $pdo->prepare('SELECT start_time, end_time FROM employee_availability
                         WHERE employee_id = ? AND day_of_week = ? ORDER BY start_time');
    $st->execute([$employeeId, $dayOfWeek]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[] = [time_to_min($r['start_time']), time_to_min($r['end_time'])];
    }
    return $out;
}

function booking_is_absent(PDO $pdo, int $employeeId, string $date): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM employee_absences
                         WHERE employee_id = ? AND ? BETWEEN date_from AND date_to');
    $st->execute([$employeeId, $date]);
    return (int)$st->fetchColumn() > 0;
}

function booking_overlap(int $newStart, int $newEnd, int $oldStart, int $oldEnd): bool
{
    return $newStart < $oldEnd && $newEnd > $oldStart;
}

function booking_has_conflict(PDO $pdo, int $employeeId, string $date, int $startMin, int $endMin): bool
{
    // nowy_początek < istniejący_koniec  ORAZ  nowy_koniec > istniejący_początek
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM reservations
         WHERE employee_id = ? AND reservation_date = ? AND status <> 'anulowana'
           AND ? < end_time AND ? > start_time");
    $st->execute([$employeeId, $date, min_to_time($startMin) . ':00', min_to_time($endMin) . ':00']);
    return (int)$st->fetchColumn() > 0;
}
function booking_slots(PDO $pdo, int $employeeId, int $duration, string $date): array
{
    if (!valid_date($date) || $date < date('Y-m-d') || $date > booking_max_date()) {
        return [];
    }
    if (booking_is_absent($pdo, $employeeId, $date)) {
        return [];
    }

    $dayOfWeek = (int)date('N', strtotime($date));
    $intervals = booking_intervals($pdo, $employeeId, $dayOfWeek);

    $st = $pdo->prepare("SELECT start_time, end_time FROM reservations
                         WHERE employee_id = ? AND reservation_date = ? AND status <> 'anulowana'");
    $st->execute([$employeeId, $date]);
    $busy = [];
    foreach ($st->fetchAll() as $r) {
        $busy[] = [time_to_min($r['start_time']), time_to_min($r['end_time'])];
    }

    $nowMin = $date === date('Y-m-d') ? ((int)date('G') * 60 + (int)date('i')) : -1;

    $slots = [];
    foreach ($intervals as [$from, $to]) {
        for ($t = $from; $t + $duration <= $to; $t += BOOKING_SLOT_STEP) {
            if ($t <= $nowMin) {
                continue; // godzina już minęła
            }
            $free = true;
            foreach ($busy as [$bs, $be]) {
                if (booking_overlap($t, $t + $duration, $bs, $be)) {
                    $free = false;
                    break;
                }
            }
            if ($free) {
                $slots[$t] = min_to_time($t);
            }
        }
    }
    ksort($slots);
    return array_values($slots);
}

function booking_validate(PDO $pdo, int $serviceId, int $employeeId, string $date, string $time): array
{
    $service = booking_get_service($pdo, $serviceId);
    if (!$service) {
        return ['Wybrana usługa jest niedostępna.', null];
    }
    if (!booking_get_employee($pdo, $employeeId, $serviceId)) {
        return ['Wybrany pracownik nie wykonuje tej usługi lub jest nieaktywny.', $service];
    }
    if (!valid_date($date) || !valid_time($time)) {
        return ['Podano nieprawidłową datę lub godzinę.', $service];
    }

    $duration = (int)$service['duration'];
    $start    = time_to_min($time);
    $end      = $start + $duration;

    if (strtotime("$date $time") <= time()) {
        return ['Nie można rezerwować terminu z przeszłości.', $service];
    }
    if ($date > booking_max_date()) {
        return ['Rezerwacja jest możliwa maksymalnie ' . BOOKING_MAX_DAYS . ' dni do przodu.', $service];
    }
    if (booking_is_absent($pdo, $employeeId, $date)) {
        return ['Pracownik jest nieobecny w wybranym dniu.', $service];
    }

    $fits = false;
    foreach (booking_intervals($pdo, $employeeId, (int)date('N', strtotime($date))) as [$from, $to]) {
        if ($start >= $from && $end <= $to) {
            $fits = true;
        }
    }
    if (!$fits) {
        return ['Wybrany termin wykracza poza godziny pracy pracownika (usługa trwa ' . $duration . ' min).', $service];
    }
    if (booking_has_conflict($pdo, $employeeId, $date, $start, $end)) {
        return ['Wybrany termin został już zajęty. Wybierz inną godzinę.', $service];
    }
    if (!in_array($time, booking_slots($pdo, $employeeId, $duration, $date), true)) {
        return ['Wybrany termin jest niedostępny.', $service];
    }
    return [null, $service];
}

function booking_create(PDO $pdo, int $userId, int $serviceId, int $employeeId,
                        string $date, string $time, string $comment): array
{
    try {
        $pdo->beginTransaction();

        $lock = $pdo->prepare('SELECT id FROM employees WHERE id = ? FOR UPDATE');
        $lock->execute([$employeeId]);
        $lock->fetch();

        [$error, $service] = booking_validate($pdo, $serviceId, $employeeId, $date, $time);
        if ($error !== null) {
            $pdo->rollBack();
            return [false, $error, null];
        }

        $start = time_to_min($time);
        $end   = $start + (int)$service['duration'];

        $pdo->prepare(
            "INSERT INTO reservations (user_id, employee_id, service_id, reservation_date, start_time, end_time, status, comment)
             VALUES (?, ?, ?, ?, ?, ?, 'oczekująca', ?)")
            ->execute([$userId, $employeeId, $serviceId, $date,
                       min_to_time($start) . ':00', min_to_time($end) . ':00',
                       $comment !== '' ? $comment : null]);
        $id = (int)$pdo->lastInsertId();

        $pdo->prepare("INSERT INTO reservation_status_history (reservation_id, old_status, new_status, changed_by)
                       VALUES (?, NULL, 'oczekująca', ?)")
            ->execute([$id, $userId]);

        $pdo->commit();
        return [true, 'Rezerwacja została zapisana i oczekuje na potwierdzenie.', $id];
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($ex->getCode() === '45000') { // sygnał z triggera: kolizja terminów
            return [false, 'Wybrany termin został właśnie zajęty. Wybierz inną godzinę.', null];
        }
        error_log('booking_create: ' . $ex->getMessage());
        return [false, 'Nie udało się zapisać rezerwacji. Spróbuj ponownie.', null];
    }
}

function reservation_change_status(PDO $pdo, int $reservationId, string $old, string $new, int $changedBy): bool
{
    $own = !$pdo->inTransaction();
    try {
        if ($own) {
            $pdo->beginTransaction();
        }
        $st = $pdo->prepare('UPDATE reservations SET status = ? WHERE id = ? AND status = ?');
        $st->execute([$new, $reservationId, $old]);
        if ($st->rowCount() === 0) {
            if ($own) {
                $pdo->rollBack();
            }
            return false;
        }
        $pdo->prepare('INSERT INTO reservation_status_history (reservation_id, old_status, new_status, changed_by)
                       VALUES (?, ?, ?, ?)')
            ->execute([$reservationId, $old, $new, $changedBy]);
        if ($own) {
            $pdo->commit();
        }
        return true;
    } catch (PDOException $ex) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}
