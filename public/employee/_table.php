<?php

function render_visits(array $rows): void
{
    ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Data</th><th>Godzina</th><th>Klient</th><th>Telefon</th><th>Usługa</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">Brak wizyt.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e(date('d.m.Y', strtotime($r['reservation_date']))) ?></td>
                <td><?= e(substr($r['start_time'], 0, 5)) ?>-<?= e(substr($r['end_time'], 0, 5)) ?></td>
                <td><?= e($r['client_name']) ?></td>
                <td><?= e($r['client_phone'] ?? '') ?></td>
                <td><?= e($r['service_name']) ?></td>
                <td><?= status_badge($r['status']) ?></td>
                <td class="actions"><a class="btn btn-sm btn-light" href="reservation_view.php?id=<?= (int)$r['id'] ?>">Szczegóły</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php
}


const VISITS_SELECT =
    "SELECT r.id, r.reservation_date, r.start_time, r.end_time, r.status,
            CONCAT(c.name, ' ', c.surname) AS client_name, c.phone AS client_phone,
            s.name AS service_name
     FROM reservations r
     JOIN users c    ON c.id = r.user_id
     JOIN services s ON s.id = r.service_id";
