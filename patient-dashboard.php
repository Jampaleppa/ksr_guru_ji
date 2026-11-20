<?php
// ============================================================
// patient-dashboard.php - Root forwarder to Telehealth Patient Dashboard
// ============================================================
require_once __DIR__ . '/patient-auth.php';

pp_redirect(pp_dashboard_url());
