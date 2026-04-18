<?php
session_start();

require __DIR__ . '/db_connect.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $plainPassword = $_POST['password'] ?? '';

    if ($email === '' || $plainPassword === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid Email or Password';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, full_name, email, password, role FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($plainPassword, $user['password'])) {
            $error = 'Invalid Email or Password';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — Event Booking</title>
    <link rel="stylesheet" href="css/site_theme.css">
</head>
<body class="site-page">
    <?php include __DIR__ . '/includes/nav.php'; ?>
    <div class="site-main site-main--auth">
        <main class="panel">
            <h1>Sign in</h1>
            <p class="subtitle">Use your Event Booking account email and password.</p>

            <?php if ($error !== ''): ?>
                <p class="alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <form method="post" action="" novalidate>
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
                        autocomplete="current-password"
                        required
                    >
                </div>
                <button type="submit">Sign in</button>
            </form>
        </main>
    </div>
</body>
</html>
