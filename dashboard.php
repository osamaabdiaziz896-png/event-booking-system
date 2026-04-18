<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require __DIR__ . '/db_connect.php';

$name = $_SESSION['user_name'] ?? 'User';
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

$events = [];
$eventsLoadError = null;
try {
    $stmt = $pdo->query(
        'SELECT id, title, description, event_date, location, price
         FROM events
         ORDER BY event_date ASC, id ASC'
    );
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    $eventsLoadError = 'Events could not be loaded. Check that the `events` table exists and includes columns: id, title, description, event_date, location, price.';
}

$bookedEventIds = [];
if ($eventsLoadError === null && $events !== []) {
    try {
        $bookStmt = $pdo->prepare('SELECT event_id FROM bookings WHERE user_id = :uid');
        $bookStmt->execute([':uid' => (int) $_SESSION['user_id']]);
        $bookedEventIds = array_map('intval', array_column($bookStmt->fetchAll(), 'event_id'));
    } catch (PDOException $e) {
        // Bookings table missing or different schema — cards still show without "Book Now" state
    }
}

$myRegisteredEvents = [];
$myRegisteredLoadError = null;
try {
    $regStmt = $pdo->prepare(
        'SELECT e.title, e.event_date
         FROM bookings b
         INNER JOIN events e ON e.id = b.event_id
         WHERE b.user_id = :uid
         ORDER BY e.event_date ASC, e.id ASC'
    );
    $regStmt->execute([':uid' => (int) $_SESSION['user_id']]);
    $myRegisteredEvents = $regStmt->fetchAll();
} catch (PDOException $e) {
    $myRegisteredLoadError = 'Your registered events could not be loaded.';
}

$justAdded = isset($_GET['added']) && $_GET['added'] === '1';
$justBooked = isset($_GET['booked']) && $_GET['booked'] === '1';
$justDeleted = isset($_GET['deleted']) && $_GET['deleted'] === '1';
$bookError = isset($_GET['book_error']) ? (string) $_GET['book_error'] : '';
$deleteError = isset($_GET['delete_error']) ? (string) $_GET['delete_error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Event Booking</title>
    <link rel="stylesheet" href="css/site_theme.css">
</head>
<body class="site-page">
    <?php include __DIR__ . '/includes/nav.php'; ?>
    <div class="site-main">
    <div class="shell">
        <main class="dashboard-panel">
            <div class="head">
                <h1>Welcome to the Event Booking Dashboard, <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>!</h1>
                <div class="actions">
                    <?php if ($isAdmin): ?>
                        <a class="btn btn-primary" href="add_event.php">Add event</a>
                        <a class="btn btn-primary" href="admin_report.php">View All Bookings</a>
                    <?php endif; ?>
                </div>
            </div>
            <p class="hint">Browse upcoming events below or add a new one.</p>

            <?php if ($justAdded): ?>
                <p class="alert-success" role="status">Event added successfully.</p>
            <?php endif; ?>
            <?php if ($justBooked): ?>
                <p class="alert-success" role="status">Your booking was saved.</p>
            <?php endif; ?>
            <?php if ($justDeleted): ?>
                <p class="alert-success" role="status">Event deleted successfully.</p>
            <?php endif; ?>
            <?php
            $bookErrorMessages = [
                'already_booked' => 'You have already booked this event.',
                'not_found' => 'That event is no longer available.',
                'invalid' => 'Invalid booking request.',
                'failed' => 'Booking could not be saved. Please try again.',
            ];
            $bookErrMsg = $bookErrorMessages[$bookError] ?? '';
            ?>
            <?php if ($bookErrMsg !== ''): ?>
                <p class="alert-error" role="alert"><?= htmlspecialchars($bookErrMsg, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php
            $deleteErrorMessages = [
                'invalid' => 'Invalid event id.',
                'not_found' => 'That event was not found or was already removed.',
                'failed' => 'The event could not be deleted. Please try again.',
            ];
            $deleteErrMsg = $deleteErrorMessages[$deleteError] ?? '';
            ?>
            <?php if ($deleteErrMsg !== ''): ?>
                <p class="alert-error" role="alert"><?= htmlspecialchars($deleteErrMsg, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </main>

        <section class="dashboard-panel" aria-labelledby="events-heading">
            <h2 id="events-heading" class="section-title">All events</h2>

            <?php if ($eventsLoadError !== null): ?>
                <p class="alert-error" role="alert"><?= htmlspecialchars($eventsLoadError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif ($events === []): ?>
                <p class="empty-events">
                    <?php if ($isAdmin): ?>
                        No events yet. Use <strong>Add event</strong> to create your first one.
                    <?php else: ?>
                        No events are listed yet. Check back later.
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <div class="events-grid">
                    <?php foreach ($events as $ev): ?>
                        <?php
                        $rawDate = $ev['event_date'] ?? '';
                        $formattedDate = $rawDate;
                        if ($rawDate !== '') {
                            $ts = strtotime($rawDate);
                            if ($ts !== false) {
                                $formattedDate = date('M j, Y', $ts);
                            }
                        }
                        $priceVal = isset($ev['price']) ? (float) $ev['price'] : 0;
                        $evId = isset($ev['id']) ? (int) $ev['id'] : 0;
                        $alreadyBooked = $evId > 0 && in_array($evId, $bookedEventIds, true);
                        ?>
                        <article class="event-card">
                            <h2><?= htmlspecialchars($ev['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>
                            <div class="event-meta">
                                <span><strong>Date:</strong> <?= htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8') ?></span>
                                <span><strong>Location:</strong> <?= htmlspecialchars($ev['location'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <p class="event-desc"><?= nl2br(htmlspecialchars($ev['description'] ?? '', ENT_QUOTES, 'UTF-8')) ?></p>
                            <p class="event-price"><strong>Price:</strong> <?= htmlspecialchars('KES ' . number_format($priceVal, 2, '.', ','), ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="event-card-footer">
                                <div class="event-card-footer-main">
                                    <?php if ($evId > 0): ?>
                                        <?php if ($alreadyBooked): ?>
                                            <span class="booked-badge" role="status">Already booked</span>
                                        <?php else: ?>
                                            <form class="book-form" method="post" action="book_event.php">
                                                <input type="hidden" name="event_id" value="<?= $evId ?>">
                                                <button type="submit">Book Now</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($isAdmin && $evId > 0): ?>
                                    <a
                                        href="delete_event.php?id=<?= $evId ?>"
                                        class="event-delete-btn"
                                        onclick="return confirm('Are you sure you want to delete this event?');"
                                    >Delete</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="dashboard-panel" aria-labelledby="registered-heading">
            <h2 id="registered-heading" class="section-title">My Registered Events</h2>

            <?php if ($myRegisteredLoadError !== null): ?>
                <p class="alert-error" role="alert"><?= htmlspecialchars($myRegisteredLoadError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif ($myRegisteredEvents === []): ?>
                <div class="registered-empty-box" role="status">
                    You haven't registered for any events yet. Browse the list above to get started!
                </div>
            <?php else: ?>
                <ul class="registered-list">
                    <?php foreach ($myRegisteredEvents as $row): ?>
                        <?php
                        $regRawDate = $row['event_date'] ?? '';
                        $regFormatted = $regRawDate;
                        if ($regRawDate !== '') {
                            $regTs = strtotime($regRawDate);
                            if ($regTs !== false) {
                                $regFormatted = date('M j, Y', $regTs);
                            }
                        }
                        ?>
                        <li class="registered-item">
                            <span class="registered-title"><?= htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="registered-date"><?= htmlspecialchars($regFormatted, ENT_QUOTES, 'UTF-8') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
    </div>
</body>
</html>
