<?php
// ============================================================
// admin/index.php - Routing Gateway for Administrative Portal
// ------------------------------------------------------------
// Unauthenticated visitors are sent to the login screen; an
// authenticated admin is forwarded to the dashboard. The auth
// gatekeeper handles the redirect to login itself when no valid
// admin session exists.
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['admin_auth_id'])) {
    $currentPage = 'index';
    require_once __DIR__ . '/inc/auth_check.php';
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;