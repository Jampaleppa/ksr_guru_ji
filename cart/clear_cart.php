<?php
// Forwarder to root update_cart.php with clear action
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_POST['action'] = 'clear';
$_GET['action'] = 'clear';
require_once __DIR__ . '/../update_cart.php';
