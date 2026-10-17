<?php
// ============================================================
// admin/inc/page_permissions.php
// ------------------------------------------------------------
// Single source of truth for admin page -> permission mapping.
//
// Every admin page declares its access requirement BEFORE
// including inc/auth_check.php, which reads this map when the
// page does not set $requiredPermission explicitly.
//
//   $currentPage = 'products';          // set by the page
//   require_once 'inc/auth_check.php';  // permission resolved here
//
// A page absent from the map defaults to requiring
// 'settings.view' so a forgotten page is never silently public.
// ============================================================

if (!function_exists('e')) {
    /**
     * Central HTML-escaping helper.
     *
     * Defined here (rather than in functions.php / sidebar.php) so the
     * auth gatekeeper and the permission maps can escape output even
     * when those files have not been included yet.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!defined('ADMIN_PAGE_PERMISSION_MAP')) {
    define('ADMIN_PAGE_PERMISSION_MAP', [

        // ---------------------------------------------------------
        // Main
        // ---------------------------------------------------------
        'dashboard'    => 'dashboard.view',
        'index'        => 'dashboard.view',

        // ---------------------------------------------------------
        // Catalogue
        // ---------------------------------------------------------
        'products'     => 'products.view',
        'categories'   => 'categories.view',
        'management'   => 'inventory.view',

        // ---------------------------------------------------------
        // Commerce
        // ---------------------------------------------------------
        'orders'       => 'orders.view',
        'payments'     => 'payments.view',
        'delivery'     => 'delivery.view',

        // ---------------------------------------------------------
        // People
        // ---------------------------------------------------------
        'users'        => 'customers.view',
        'customers'    => 'customers.view',

        // ---------------------------------------------------------
        // Doctor Assist / clinical
        // ---------------------------------------------------------
        'doctors'          => 'doctors.view',
        'patients'         => 'prescriptions.view',
        'consultations'    => 'appointments.view',
        'policies'         => 'pharmacy.view',
        'pharmacy'         => 'pharmacy.view',
        'medical'          => 'medical.view',
        'medical_verification' => 'medical.view',

        // ---------------------------------------------------------
        // Communication
        // ---------------------------------------------------------
        'notification' => 'notifications.view',
        'whatsapp'     => 'whatsapp.view',
        'reports'      => 'reports.view',

        // ---------------------------------------------------------
        // System
        // ---------------------------------------------------------
        'admins'       => 'admins.view',   // + hard Super Admin gate in page
        'audit'        => 'audit.view',
        'settings'     => 'settings.view',
        'maintenance'  => 'maintenance.view',
    ]);
}

if (!function_exists('admin_page_permission')) {

    /**
     * Resolve the permission required for an admin page.
     *
     * Fail-CLOSED: a page key that is not in the map resolves to
     * 'settings.view', which the ADMIN role does not hold. A page that
     * forgets to register itself is therefore never reachable by a
     * regular admin instead of silently inheriting dashboard access.
     *
     * @param string $page Page key (usually $currentPage).
     * @return string Permission key, or '' when the page needs none.
     */
    function admin_page_permission(string $page): string
    {
        $map = ADMIN_PAGE_PERMISSION_MAP;

        return $map[$page] ?? 'settings.view';
    }
}

if (!defined('ADMIN_ACTION_OVERRIDES')) {
    /**
     * Action names whose required permission cannot be derived from
     * the generic verb prefix rules.
     *
     *   'page' => [ 'action_name' => 'permission.key' ]
     *
     * An empty permission string means the action is read-only and
     * must never be permission-gated.
     */
    define('ADMIN_ACTION_OVERRIDES', [

        // Inventory page uses generic nouns for its write actions.
        'management' => [
            'generate_code'    => 'inventory.create',
            'add_inventory'    => 'inventory.create',
            'add_banner'       => 'inventory.create',
            'add_coupon'       => 'inventory.create',
            'update_inventory' => 'inventory.edit',
            'update_banner'    => 'inventory.edit',
            'update_coupon'    => 'inventory.edit',
        ],

        // Orders / payments: status transitions are edits, not deletes.
        'orders' => [
            'delete'        => 'orders.edit',
            'update_status' => 'orders.edit',
            'set_status'    => 'orders.edit',
            'cancel'        => 'orders.edit',
        ],
        'payments' => [
            'delete'      => 'payments.edit',
            'set_status'  => 'payments.edit',
            'refund'      => 'payments.approve',
            'approve'     => 'payments.approve',
        ],
        'delivery' => [
            'set_status'    => 'delivery.edit',
            'update_status' => 'delivery.edit',
            'assign'        => 'delivery.edit',
        ],

        // Notifications.
        'notification' => [
            'delete'        => 'notifications.delete',
            'clear_all'     => 'notifications.delete',
            'mark_read'     => 'notifications.edit',
            'mark_all_read' => 'notifications.edit',
            'send'          => 'notifications.create',
        ],
    ]);
}

if (!function_exists('admin_action_permission')) {

    /**
     * Verb prefixes mapped to a permission verb.
     *
     * Used to derive the required permission for an action name such
     * as "delete_banner" (delete) or "save_product" (create).
     */
    function admin_action_verb_map(): array
    {
        return [
            'create'  => 'create',
            'add'     => 'create',
            'new'     => 'create',
            'save'    => 'create',
            'import'  => 'create',
            'update'  => 'edit',
            'edit'    => 'edit',
            'toggle'  => 'edit',
            'set'     => 'edit',
            'change'  => 'edit',
            'assign'  => 'edit',
            'reset'   => 'edit',
            'approve' => 'approve',
            'reject'  => 'approve',
            'verify'  => 'approve',
            'delete'  => 'delete',
            'remove'  => 'delete',
            'clear'   => 'delete',
            'purge'   => 'delete',
            'export'  => 'export',
        ];
    }

    /**
     * Resolve the permission an action name requires.
     *
     * @param string $action e.g. "delete_banner", "save_product"
     * @param string $page   page key, used to resolve the module
     * @return string|null Permission key, or null when the action
     *                      is not a recognised write action.
     */
    function admin_action_permission(string $action, string $page): ?string
    {
        $action = strtolower(trim($action));
        if ($action === '') {
            return null;
        }

        // 1. Explicit override wins.
        [$found, $override] = admin_action_permission_override($action, $page);
        if ($found) {
            return $override;
        }

        $global = admin_page_permission($page);
        $module = strtok($global, '.') ?: $page;
        $verbs  = admin_action_verb_map();

        // 2. Match the longest verb prefix of the action name, so that
        //    "delete_banner" -> delete, "save_product" -> create and
        //    "update_status" -> update.
        $verbs = admin_action_verb_map();

        $parts = explode('_', $action);
        while ($parts) {
            $prefix = implode('_', $parts);
            if (isset($verbs[$prefix])) {
                return "{$module}.{$verbs[$prefix]}";
            }
            if (count($parts) === 1) {
                break;
            }
            array_pop($parts);
        }

        return null;
    }
}

if (!function_exists('admin_action_permission_override')) {

    /**
     * Look up an explicit action -> permission override.
     *
     * @return array{0:bool,1:?string} [found, permission]
     */
    function admin_action_permission_override(string $action, string $page): array
    {
        $overrides = ADMIN_ACTION_OVERRIDES;
        $key = strtolower(trim($action));

        if (isset($overrides[$page][$key])) {
            $permission = (string) $overrides[$page][$key];
            return [true, $permission === '' ? null : $permission];
        }

        return [false, null];
    }
}

if (!function_exists('admin_page_requires_edit_for_post')) {
    /**
     * Pages where ANY non-GET request is a write and therefore needs
     * the module's `edit` permission, regardless of the action name.
     */
    function admin_page_requires_edit_for_post(string $page): bool
    {
        return in_array($page, ['settings', 'maintenance'], true);
    }
}

if (!function_exists('admin_page_options')) {

    /**
     * Human-readable label for a page key (used by the Access Denied screen).
     */
    function admin_page_label(string $page): string
    {
        $labels = [
            'dashboard'    => 'Dashboard',
            'products'     => 'Products',
            'categories'   => 'Categories',
            'management'   => 'Inventory',
            'orders'       => 'Orders',
            'payments'     => 'Payments',
            'delivery'     => 'Delivery',
            'users'        => 'Users',
            'customers'    => 'Customers',
            'notification' => 'Notifications',
            'whatsapp'     => 'WhatsApp Command Center',
            'reports'      => 'Reports & Analytics',
            'admins'       => 'Admin Management',
            'audit'        => 'Audit Logs',
            'settings'     => 'Settings',
            'maintenance'  => 'Maintenance',
            'medical_verification' => 'Medical Verification',
        ];
        return $labels[$page] ?? ucfirst(str_replace('_', ' ', $page));
    }
}
