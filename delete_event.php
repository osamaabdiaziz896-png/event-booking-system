<?php
/**
 * Admin-only: DELETE FROM events (and related bookings) for ?id= event id.
 * Redirects to dashboard.php with ?deleted=1 or ?delete_error=...
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

$eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($eventId === null || $eventId === false || $eventId < 1) {
    header('Location: dashboard.php?delete_error=invalid');
    exit;
}

try {
    $pdo->beginTransaction();

    try {
        $pdo->prepare('DELETE FROM bookings WHERE event_id = :id')->execute([':id' => $eventId]);
    } catch (PDOException $e) {
        $mysqlErr = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
        if ($mysqlErr !== 1146) {
            throw $e;
        }
    }

    $stmt = $pdo->prepare('DELETE FROM events WHERE id = :id');
    $stmt->execute([':id' => $eventId]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        header('Location: dashboard.php?delete_error=not_found');
        exit;
    }

    $pdo->commit();
    header('Location: dashboard.php?deleted=1');
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: dashboard.php?delete_error=failed');
    exit;
}
