<?php
/**
 * Expects `events` columns: id, title, description, event_date, location, price DECIMAL(10,2), created_at
 * (id and created_at are set by the database.)
 */
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth_helpers.php';

if (!current_user_is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$todayYmd = (new DateTime('today'))->format('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $price = trim($_POST['price'] ?? '');

    if ($title === '') {
        $errors[] = 'Title is required.';
    } elseif (mb_strlen($title) > 255) {
        $errors[] = 'Title is too long.';
    }

    if ($description === '') {
        $errors[] = 'Description is required.';
    }

    if ($event_date === '') {
        $errors[] = 'Date is required.';
    } else {
        $dt = DateTime::createFromFormat('Y-m-d', $event_date);
        if (!$dt || $dt->format('Y-m-d') !== $event_date) {
            $errors[] = 'Please enter a valid date.';
        } else {
            $today = new DateTime('today');
            if ($dt < $today) {
                $errors[] = 'Event date cannot be in the past.';
            }
        }
    }

    if ($location === '') {
        $errors[] = 'Location is required.';
    } elseif (mb_strlen($location) > 255) {
        $errors[] = 'Location is too long.';
    }

    $price_normalized = str_replace([' ', ','], ['', '.'], $price);
    if ($price === '') {
        $errors[] = 'Price is required.';
    } elseif ($price_normalized === '' || !is_numeric($price_normalized)) {
        $errors[] = 'Price must be a valid number.';
    } elseif ((float) $price_normalized < 0) {
        $errors[] = 'Price cannot be negative.';
    } elseif (!is_finite((float) $price_normalized)) {
        $errors[] = 'Price must be a valid number.';
    }

    if ($errors === []) {
        $price_number = round((float) $price_normalized, 2);
        // DECIMAL(10,2): max 99,999,999.99 (8 digits before decimal)
        if ($price_number > 99999999.99) {
            $errors[] = 'Price is too large for this system (maximum 99,999,999.99).';
        }
    }

    if ($errors === []) {
        $price_decimal = number_format($price_number, 2, '.', '');

        try {
            $sql = 'INSERT INTO `events` (`title`, `description`, `event_date`, `location`, `price`) '
                . 'VALUES (:title, :description, :event_date, :location, :price)';
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':title', $title, PDO::PARAM_STR);
            $stmt->bindValue(':description', $description, PDO::PARAM_STR);
            $stmt->bindValue(':event_date', $event_date, PDO::PARAM_STR);
            $stmt->bindValue(':location', $location, PDO::PARAM_STR);
            $stmt->bindValue(':price', $price_decimal, PDO::PARAM_STR);
            $stmt->execute();
            header('Location: dashboard.php?added=1');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Could not save the event: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add event — Event Booking</title>
    <link rel="stylesheet" href="css/site_theme.css">
</head>
<body class="site-page">
    <?php include __DIR__ . '/includes/nav.php'; ?>
    <div class="site-main">
        <div class="form-shell">
        <main class="panel">
            <h1>Add new event</h1>

            <?php if ($errors !== []): ?>
                <div class="alert-error" role="alert">
                    <ul>
                        <?php foreach ($errors as $msg): ?>
                            <li><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <p class="subtitle">Enter the details for your event.</p>

            <form method="post" action="">
                <div>
                    <label for="title">Title</label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="255"
                        value="<?= htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>
                <div>
                    <label for="description">Description</label>
                    <textarea id="description" name="description" required><?= htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div>
                    <label for="event_date">Date</label>
                    <input
                        type="date"
                        id="event_date"
                        name="event_date"
                        min="<?= htmlspecialchars($todayYmd, ENT_QUOTES, 'UTF-8') ?>"
                        value="<?= htmlspecialchars($_POST['event_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>
                <div>
                    <label for="location">Location</label>
                    <input
                        type="text"
                        id="location"
                        name="location"
                        maxlength="255"
                        value="<?= htmlspecialchars($_POST['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>
                <div>
                    <label for="price">Price (KES)</label>
                    <input
                        type="number"
                        id="price"
                        name="price"
                        min="0"
                        max="99999999.99"
                        step="0.01"
                        inputmode="decimal"
                        value="<?= htmlspecialchars($_POST['price'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>
                <button type="submit">Save event</button>
            </form>
        </main>
        </div>
    </div>
</body>
</html>
