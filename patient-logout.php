<?php
// ============================================================
// patient-logout.php - secure logout for the Patient Portal
// ============================================================
require_once __DIR__ . '/patient-auth.php';

if (!empty($_SESSION['user_id'])) {
    pp_log_attempt('logout:' . (int)$_SESSION['user_id'], true);
}

pp_destroy_session();
header('Location: patient-login.php?bye=1');
exit;
