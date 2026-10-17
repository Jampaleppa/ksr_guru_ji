<?php
// delivery/logout.php — end the agent session
require_once __DIR__ . '/bootstrap.php';
delivery_logout();
header('Location: login.php');
exit;
