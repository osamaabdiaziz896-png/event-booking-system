<?php
/**
 * Expects table `bookings` with at least: user_id, event_id
 * Recommended: UNIQUE (user_id, event_id) so the same user cannot book the same event twice.
 *
 * Example:
 * CREATE TABLE bookings (
 *   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *   user_id INT UNSIGNED NOT NULL,
 *   event_id INT UNSIGNED NOT NULL,
 *   UNIQUE KEY uq_user_event (user_id, event_id)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

require __DIR__ . '/db_connect.php';

$userId = (int) $_SESSION['user_id'];
$eventId = filter_var($_POST['event_id'] ?? '', FILTER_VALIDATE_INT);

if ($eventId === false || $eventId < 1) {
    header('Location: dashboard.php?book_error=invalid');
    exit;
}

try {
    $check = $pdo->prepare('SELECT id FROM events WHERE id = :id LIMIT 1');
    $check->execute([':id' => $eventId]);
    if ($check->fetch() === false) {
        header('Location: dashboard.php?book_error=not_found');
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO bookings (user_id, event_id) VALUES (:user_id, :event_id)'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':event_id' => $eventId,
    ]);
    header('Location: dashboard.php?booked=1');
    exit;
} catch (PDOException $e) {
    $sqlState = $e->errorInfo[0] ?? '';
    $mysqlErr = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
    if ($sqlState === '23000' || $mysqlErr === 1062 || str_contains($e->getMessage(), 'Duplicate')) {
        header('Location: dashboard.php?book_error=already_booked');
        exit;
    }
    header('Location: dashboard.php?book_error=failed');
    exit;
}
