<?php
/**
 * Admin checks. Uses $_SESSION['user_role'] set in login.php from users.role.
 */

function current_user_is_admin(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['user_id'])) {
        return false;
    }

    return ($_SESSION['user_role'] ?? '') === 'admin';
}
