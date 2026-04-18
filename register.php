<?php
session_start();
/**
 * One-time setup — run in MySQL (e.g. phpMyAdmin):
 *
 * CREATE TABLE IF NOT EXISTS users (
 *   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *   full_name VARCHAR(150) NOT NULL,
 *   email VARCHAR(255) NOT NULL UNIQUE,
 *   password VARCHAR(255) NOT NULL,
 *   role VARCHAR(32) NOT NULL DEFAULT 'user',
 *   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 *
 * Existing database: ALTER TABLE users ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT 'user';
 * Grant admin: UPDATE users SET role = 'admin' WHERE email = 'your@email.com';
 */

require __DIR__ . '/db_connect.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $plainPassword = $_POST['password'] ?? '';

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($fullName) > 150) {
        $errors[] = 'Full name is too long.';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($plainPassword === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($plainPassword) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($errors === []) {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (full_name, email, password) VALUES (:full_name, :email, :password)'
            );
            $stmt->execute([
                ':full_name' => $fullName,
                ':email' => $email,
                ':password' => $hash,
            ]);
            $success = true;
        } catch (PDOException $e) {
            $sqlState = $e->errorInfo[0] ?? '';
            $mysqlErr = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
            if ($sqlState === '23000' || $mysqlErr === 1062 || str_contains($e->getMessage(), 'Duplicate')) {
                $errors[] = 'An account with this email already exists.';
            } else {
                $errors[] = 'Registration could not be completed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Event Booking</title>
    <link rel="stylesheet" href="css/site_theme.css">
</head>
<body class="site-page">
    <?php include __DIR__ . '/includes/nav.php'; ?>
    <div class="site-main site-main--auth">
    <main class="panel">
        <h1>Create an account</h1>
        <p class="subtitle">Join Event Booking with your details below.</p>

        <?php if ($success): ?>
            <p class="alert alert-success" role="status">Registration successful. You can sign in when login is available.</p>
        <?php elseif ($errors !== []): ?>
            <div class="alert alert-error" role="alert">
                <ul>
                    <?php foreach ($errors as $msg): ?>
                        <li><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="post" action="" novalidate>
            <div>
                <label for="full_name">Full name</label>
                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    autocomplete="name"
                    maxlength="150"
                    value="<?= htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
            </div>
            <div>
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    autocomplete="email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
            </div>
            <div>
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    minlength="8"
                    required
                >
            </div>
            <button type="submit">Register</button>
        </form>
        <?php endif; ?>
    </main>
    </div>
</body>
</html>
