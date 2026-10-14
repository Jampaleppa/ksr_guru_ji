<?php
// Simple redirect layer for tracking.php to support both track.php and tracking.php URLs.
$orderId = $_GET['order'] ?? $_GET['ref'] ?? '';
header("Location: tracking.php?order=" . urlencode($orderId));
exit;
?>
