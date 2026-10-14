<?php
// ============================================================
// admin/inc/functions.php
// Helper Functions for Admin Portal
// ============================================================

require_once __DIR__ . '/page_permissions.php';

/**
 * Escape HTML safely.
 *
 * The canonical definition now lives in inc/page_permissions.php so
 * that the auth gatekeeper can escape output before this file is
 * loaded. It is guarded here to avoid a redeclaration.
 *
 * @param mixed $value
 * @return string
 */
if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


/**
 * Returns HTML badge for order/system status.
 *
 * Supported statuses:
 *   pending
 *   processing
 *   shipped
 *   delivered
 *   cancelled / canceled
 *   failed
 *   rejected
 *   expired
 *   suspended
 *   draft
 *   in_progress
 *   active
 *   completed
 *   review_required
 *   clarification_required
 *   verified
 *   finalized
 *   escalated
 *
 * @param string|null $status
 * @return string
 */
function getStatusBadge(?string $status): string
{
    $status = trim((string) $status);

    if ($status === '') {
        $status = 'pending';
    }

    $statusLower = strtolower($status);

    /*
     * Normalize common variations.
     */
    $normalizedStatus = str_replace(
        ['-', ' '],
        '_',
        $statusLower
    );

    /*
     * CSS class mapping.
     */
    $badgeClassMap = [

        // Order states
        'pending'                  => 'pending',
        'processing'               => 'processing',
        'shipped'                  => 'shipped',
        'delivered'                => 'delivered',
        'cancelled'                => 'cancelled',
        'canceled'                 => 'cancelled',

        // General workflow states
        'draft'                    => 'draft',
        'in_progress'              => 'processing',
        'active'                   => 'active',
        'completed'                => 'completed',
        'failed'                   => 'failed',
        'rejected'                 => 'rejected',
        'expired'                  => 'expired',
        'suspended'                => 'suspended',

        // Review / verification states
        'review_required'          => 'review-required',
        'clarification_required'   => 'clarification-required',
        'verified'                 => 'verified',
        'finalized'                => 'finalized',
        'escalated'                => 'escalated',
    ];

    /*
     * Use mapped CSS class.
     * Unknown statuses receive "pending" styling instead
     * of generating an unsafe/uncontrolled CSS class.
     */
    $badgeClass = $badgeClassMap[$normalizedStatus] ?? 'pending';

    /*
     * Human-readable label.
     */
    $label = str_replace(
        '_',
        ' ',
        $normalizedStatus
    );

    $label = ucwords($label);

    return sprintf(
        '<span class="status-badge %s">%s</span>',
        e($badgeClass),
        e($label)
    );
}


/**
 * Returns a CSS status class only.
 *
 * Useful when the HTML needs to be constructed separately.
 *
 * @param string|null $status
 * @return string
 */
function getStatusClass(?string $status): string
{
    $status = trim((string) $status);

    if ($status === '') {
        return 'pending';
    }

    $statusLower = strtolower($status);

    $statusLower = str_replace(
        ['-', ' '],
        '_',
        $statusLower
    );

    $classes = [
        'pending'                => 'pending',
        'processing'             => 'processing',
        'shipped'                => 'shipped',
        'delivered'              => 'delivered',
        'cancelled'              => 'cancelled',
        'canceled'               => 'cancelled',

        'draft'                  => 'draft',
        'in_progress'            => 'processing',
        'active'                 => 'active',
        'completed'              => 'completed',
        'failed'                 => 'failed',
        'rejected'               => 'rejected',
        'expired'                => 'expired',
        'suspended'              => 'suspended',

        'review_required'        => 'review-required',
        'clarification_required' => 'clarification-required',
        'verified'               => 'verified',
        'finalized'              => 'finalized',
        'escalated'              => 'escalated',
    ];

    return $classes[$statusLower] ?? 'pending';
}


/**
 * Convert a status value into a human-readable label.
 *
 * @param string|null $status
 * @return string
 */
function getStatusLabel(?string $status): string
{
    $status = trim((string) $status);

    if ($status === '') {
        return 'Pending';
    }

    $status = str_replace(
        ['-', '_'],
        ' ',
        strtolower($status)
    );

    return ucwords($status);
}


/**
 * Check whether the current user is a super administrator.
 *
 * @return bool
 */
function isSuperAdmin(): bool
{
    return isset($_SESSION['user_role']) &&
        $_SESSION['user_role'] === 'super_admin';
}


/**
 * Check whether the current user is a maintenance administrator.
 *
 * @return bool
 */
function isMaintenanceAdmin(): bool
{
    return isset($_SESSION['user_role']) &&
        $_SESSION['user_role'] === 'admin';
}


/**
 * Check whether the current user has administrator privileges.
 *
 * @return bool
 */
function isAdmin(): bool
{
    return isset($_SESSION['user_role']) &&
        in_array(
            $_SESSION['user_role'],
            ['admin', 'super_admin'],
            true
        );
}


/**
 * Prevent non-admin users from accessing an admin page.
 *
 * @return void
 */
function requireAdmin(): void
{
    if (!isAdmin()) {
        header(
            'Location: ../login.php?redirect=' .
                urlencode($_SERVER['REQUEST_URI'] ?? '')
        );
        exit;
    }
}


/**
 * Prevent maintenance admin from performing
 * operations that require super administrator access.
 *
 * @return void
 */
function requireSuperAdmin(): void
{
    if (!isSuperAdmin()) {

        if (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) ===
            'xmlhttprequest'
        ) {
            http_response_code(403);

            header(
                'Content-Type: application/json; charset=utf-8'
            );

            echo json_encode([
                'success' => false,
                'error'   => 'SUPER_ADMIN_REQUIRED',
                'message' => 'Super Admin permission is required.'
            ]);

            exit;
        }

        http_response_code(403);

        die('Super Admin permission is required.');
    }
}
