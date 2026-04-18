<?php

$host = 'localhost';
$dbname = 'event_booking_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $dbConnectScript = realpath(__FILE__);
    $entryScript = isset($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : false;
    if ($entryScript && $entryScript === $dbConnectScript) {
        echo 'System Ready';
    }
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
