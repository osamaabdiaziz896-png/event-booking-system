<?php
/**
 * Admin-only booking report. Requires $_SESSION['user_role'] === 'admin'.
 * Uses bookings + users + events via INNER JOINs.
 */
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

require __DIR__ . '/db_connect.php';

$rows = [];
$queryError = null;

try {
    $sql = '
        SELECT
            u.full_name AS attendee_name,
            u.email AS attendee_email,
            e.title AS event_booked,
            COALESCE(b.created_at, e.event_date) AS booking_date
        FROM bookings AS b
        INNER JOIN users AS u ON u.id = b.user_id
        INNER JOIN events AS e ON e.id = b.event_id
        ORDER BY COALESCE(b.created_at, e.event_date) DESC, b.id DESC
    ';
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'created_at')) {
        try {
            $sqlFallback = '
                SELECT
                    u.full_name AS attendee_name,
                    u.email AS attendee_email,
                    e.title AS event_booked,
                    e.event_date AS booking_date
                FROM bookings AS b
                INNER JOIN users AS u ON u.id = b.user_id
                INNER JOIN events AS e ON e.id = b.event_id
                ORDER BY e.event_date DESC, b.id DESC
            ';
            $rows = $pdo->query($sqlFallback)->fetchAll();
        } catch (PDOException $e2) {
            $queryError = 'Could not load bookings. Check the `bookings`, `users`, and `events` tables.';
        }
    } else {
        $queryError = 'Could not load bookings. Check the `bookings`, `users`, and `events` tables.';
    }
}

function format_booking_date(?string $raw): string
{
    if ($raw === null || $raw === '') {
        return '—';
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return $raw;
    }
    $timePart = date('H:i:s', $ts);
    if ($timePart === '00:00:00') {
        return date('M j, Y', $ts);
    }
    return date('M j, Y · g:i A', $ts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin booking report — Event Booking</title>
    <link rel="stylesheet" href="css/site_theme.css">
</head>
<body class="site-page">
    <?php include __DIR__ . '/includes/nav.php'; ?>
    <div class="site-main">
        <div class="report-page-toolbar">
            <a href="dashboard.php" class="btn btn-outline">Back to Dashboard</a>
            <a href="logout.php" class="btn btn-primary">Logout</a>
        </div>
        <div class="report-shell">
            <main class="dashboard-panel">
                <h1>Booking report</h1>
                <p class="hint">All bookings in the system (admin only).</p>

                <?php if ($queryError !== null): ?>
                    <p class="alert-error" role="alert"><?= htmlspecialchars($queryError, ENT_QUOTES, 'UTF-8') ?></p>
                <?php elseif ($rows === []): ?>
                    <p class="report-empty">No bookings in the database yet.</p>
                <?php else: ?>
                    <div class="report-table-wrap">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th scope="col">Attendee Name</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Event Booked</th>
                                    <th scope="col">Booking Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['attendee_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($r['attendee_email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($r['event_booked'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(format_booking_date($r['booking_date'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
