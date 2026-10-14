<?php
// ============================================================
// admin/inc/auth_check.php
// ------------------------------------------------------------
// Shared admin gatekeeper.
//
// Include this at the top of every admin page to:
//   1. Require authentication (redirect to login when absent)
//   2. Resolve the current admin's role + permissions
//   3. Optionally require a specific permission for the page
//
// Usage:
//   $currentPage = 'products';
//   $requiredPermission = 'products.view';   // optional
//   require_once __DIR__ . '/inc/auth_check.php';
//
// Model (per specification):
//   SUPER_ADMIN -> full system access (no restriction)
//   ADMIN       -> permission-based access; cannot create/modify
//                  Super Admins; cannot change security settings
//                  unless explicitly granted.
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/page_permissions.php';

$auth = admin_auth();

// ------------------------------------------------------------
// 0. RESOLVE THE PAGE'S REQUIRED PERMISSION
// ------------------------------------------------------------
// A page may declare its own $requiredPermission; otherwise it is
// resolved from the central page -> permission map.
if (!isset($currentPage) || $currentPage === '') {
    $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
}
if (empty($requiredPermission)) {
    $requiredPermission = admin_page_permission((string) $currentPage);
}

// ------------------------------------------------------------
// 1. AUTHENTICATION
// ------------------------------------------------------------
$auth->bootSession();

if (!$auth->check()) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? 'dashboard.php');
    header('Location: login.php?redirect=' . $redirect);
    exit;
}

// Expose convenient variables for the including page.
$userRole           = $_SESSION['user_role'] ?? 'admin';
$adminIsSuperAdmin  = $auth->isSuperAdmin();

// Super Admin has unrestricted access.
if ($adminIsSuperAdmin) {
    if (!empty($requiredPermission)) {
        // Always satisfied for super admin.
    }
    return;
}

// ------------------------------------------------------------
// 2. PAGE-LEVEL PERMISSION CHECK (for ADMIN role)
// ------------------------------------------------------------
if (!empty($requiredPermission) && !$auth->can($requiredPermission)) {

    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error'   => 'PERMISSION_DENIED',
            'message' => 'You do not have permission to perform this action.',
        ]);
        exit;
    }

    http_response_code(403);
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied</title>
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f4f7fb;
                font-family: Arial, Helvetica, sans-serif;
            }

            .access-card {
                width: min(460px, calc(100% - 40px));
                background: #ffffff;
                border-radius: 16px;
                padding: 35px;
                text-align: center;
                box-shadow: 0 15px 40px rgba(0, 0, 0, 0.10);
            }

            .icon {
                width: 70px;
                height: 70px;
                margin: 0 auto 20px;
                border-radius: 50%;
                background: #fff3cd;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 32px;
            }

            h1 {
                margin: 0 0 12px;
                color: #172033;
                font-size: 24px;
            }

            p {
                margin: 0 0 25px;
                color: #667085;
                line-height: 1.6;
            }

            .back-btn {
                display: inline-block;
                padding: 11px 22px;
                border-radius: 8px;
                background: #0d6efd;
                color: #ffffff;
                text-decoration: none;
                font-weight: 600;
            }

            .back-btn:hover {
                background: #0b5ed7;
            }
        </style>
    </head>

    <body>
        <div class="access-card">
            <div class="icon">🔒</div>
            <h1>Permission Denied</h1>
            <p>
                Your role (<strong><?= e($userRole) ?></strong>) does not include
                access to <strong><?= e(admin_page_label((string) $currentPage)) ?></strong>.
                <br><br>
                Missing permission: <code><?= e((string) $requiredPermission) ?></code>
                <br>Please contact a Super Admin if you believe this is an error.
            </p>
            <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
        </div>
    </body>

    </html>
<?php
    exit;
}

// ------------------------------------------------------------
// 3. HELPERS available to pages after inclusion
// ------------------------------------------------------------
if (!function_exists('current_admin_can')) {
    /**
     * Permission check helper for page templates.
     */
    function current_admin_can(string $permission): bool
    {
        return admin_auth()->can($permission);
    }
}

if (!function_exists('current_admin_is_super')) {
    function current_admin_is_super(): bool
    {
        return admin_auth()->isSuperAdmin();
    }
}

if (!function_exists('require_admin_permission')) {

    /**
     * Enforce a permission for a single action, exiting on denial.
     *
     * Usage at the top of a POST handler:
     *   require_admin_permission('products.edit');
     *
     * Honours JSON responses for AJAX callers and writes an audit
     * entry recording the denied attempt.
     */
    function require_admin_permission(string $permission): void
    {
        if (current_admin_can($permission)) {
            return;
        }

        admin_auth()->audit(
            'permission_denied',
            $GLOBALS['currentPage'] ?? 'unknown',
            null,
            null,
            ['required' => $permission]
        );

        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error'   => 'PERMISSION_DENIED',
                'permission' => $permission,
                'message' => 'You do not have permission to perform this action.',
            ]);
            exit;
        }

        http_response_code(403);
        exit('<h1>403 - Permission Denied</h1>'
            . '<p>Your role does not include the <code>' . htmlspecialchars($permission, ENT_QUOTES)
            . '</code> permission.</p>');
    }
}

if (!function_exists('admin_audit')) {

    /**
     * Record a page-level action in the audit trail.
     */
    function admin_audit(string $action, ?string $module = null, ?string $table = null, $recordId = null, $details = null): void
    {
        admin_auth()->audit($action, $module, $table, $recordId, $details);
    }
}

// ------------------------------------------------------------
// 4. ACTION-LEVEL PERMISSION GUARD
// ------------------------------------------------------------
// Viewing a page is not the same as being allowed to change it.
// Every mutating action name is mapped to the permission it needs,
// so an admin with read-only access to a module physically cannot
// perform the write even by posting the form directly.
//
// The mapping is declarative: adding a new action name to a page
// automatically makes it permission-checked.
// ------------------------------------------------------------

if (!function_exists('admin_guard_request_actions')) {
    /**
     * Inspect the current request and enforce the permission that the
     * requested action needs.
     */
    function admin_guard_request_actions(string $page): void
    {
        $action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');

        // Pages where every non-GET request is a write.
        if ($action === ''
            && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'
            && admin_page_requires_edit_for_post($page)
        ) {
            $action = 'edit';
        }

        // Also honour submit-button style verbs (save_product, etc.).
        if ($action === '') {
            foreach (array_keys($_POST) as $key) {
                $key = (string) $key;
                if (str_starts_with($key, 'save_')
                    || str_starts_with($key, 'delete_')
                    || str_starts_with($key, 'update_')
                    || str_starts_with($key, 'approve_')
                    || str_starts_with($key, 'add_')
                ) {
                    $action = $key;
                    break;
                }
            }
        }

        $permission = admin_action_permission($action, $page);
        if ($permission !== null) {
            require_admin_permission($permission);
        }
    }
}

// Enforce now that the page permission has already been validated.
admin_guard_request_actions((string) $currentPage);