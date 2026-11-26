<?php
// api/coupons/remove.php – Remove an applied coupon from the session
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

unset($_SESSION['applied_coupon']);
echo json_encode(['success' => true, 'message' => 'Coupon removed.']);
