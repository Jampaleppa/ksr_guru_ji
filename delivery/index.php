<?php
// delivery/index.php — entry point for the delivery module
require_once __DIR__ . '/bootstrap.php';
header('Location: ' . (delivery_is_logged_in() ? 'dashboard.php' : 'login.php'));
exit;