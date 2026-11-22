<?php
// Forwarder to root update_cart.php with remove action
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_POST['action'] = $_POST['action'] ?? 'remove';
$_GET['action'] = $_GET['action'] ?? 'remove';
require_once __DIR__ . '/../update_cart.php';
