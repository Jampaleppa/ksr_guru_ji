<?php
// ============================================================
// admin/inc/sidebar.php
// Shared Sidebar Navigation Menu
// ============================================================

if (!isset($currentPage)) {
    $currentPage = '';
}

// Sidebar may be included from a page that has not yet pulled in the
// permission map, so make sure the helpers exist.
require_once __DIR__ . '/page_permissions.php';

$adminName = $_SESSION['user_name']
    ?? $_SESSION['admin_name']
    ?? 'Admin';

$adminRole = $_SESSION['user_role'] ?? 'admin';

$isSuperAdmin = ($adminRole === 'super_admin');

// Can the current admin reach a given page? Falls back to a closed
// deny when admin_auth() is not available (e.g. during bootstrap).
$adminCan = static function (string $page): bool {
    if (!function_exists('current_admin_can')) {
        return !empty($_SESSION['admin_auth_id']);
    }
    return current_admin_can(admin_page_permission($page));
};

if (!function_exists('current_admin_can')) {
    /**
     * Sidebar-safe fallback used only when auth_check.php has not
     * been included yet.
     */
    function current_admin_can(string $permission): bool
    {
        return !empty($_SESSION['admin_auth_id']);
    }
}

/**
 * Render a nav link only when the admin has access to the page.
 *
 * Usage:
 *   <?php $adminCan('products') && nav_link('products', 'products.php',
 *            'fa-box', 'Products'); ?>
 */
function nav_link(string $page, string $href, string $icon, string $label, ?string $currentPage = null, string $extra = ''): void
{
    $currentPage = $currentPage ?? (string) ($GLOBALS['currentPage'] ?? '');
    echo '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '"'
        . ' class="nav-item ' . sidebarActive($page, $currentPage) . '"'
        . ' data-admin-page="' . htmlspecialchars($page, ENT_QUOTES) . '"'
        . $extra . '>'
        . '<i class="' . htmlspecialchars($icon, ENT_QUOTES) . '"></i>'
        . '<span>' . htmlspecialchars($label, ENT_QUOTES) . '</span>'
        . '</a>';
}

/**
 * Safe active-page helper.
 */
function sidebarActive(string $page, string $currentPage): string
{
    return ($page === $currentPage) ? 'active' : '';
}

/**
 * Safe HTML output.
 */
if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}
?>

<!-- =========================================================
     SIDEBAR
========================================================= -->
<aside class="sidebar" id="sidebar">

    <!-- =====================================================
         HEADER / LOGO
    ====================================================== -->
    <div class="sidebar-header">

        <div
            class="admin-logo"
            onclick="window.location.href='dashboard.php'"
            style="
                cursor:pointer;
                display:flex;
                align-items:center;
                gap:10px;
            ">

            <div
                class="logo-image"
                style="
                    width:32px;
                    height:32px;
                    background:linear-gradient(
                        135deg,
                        #0f7b3a,
                        #1ea85a
                    );
                    border-radius:50%;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    box-shadow:0 4px 8px rgba(15,123,58,0.20);
                    flex-shrink:0;
                    border:1.5px solid rgba(255,255,255,0.80);
                    overflow:hidden;
                ">
                <img
                    src="../company_logo.jpg"
                    alt="KSR GURU JI"
                    style="
                        max-width:88%;
                        max-height:88%;
                        object-fit:contain;
                        border-radius:50%;
                    "
                    onerror="
                        this.style.display='none';
                        this.parentElement.innerHTML='🛡️';
                    ">
            </div>

            <div>
                <h2
                    style="
                        font-size:1.1rem;
                        font-weight:800;
                        color:#ffffff;
                        margin:0;
                        line-height:1.1;
                    ">
                    KSR GURU JI
                </h2>

                <p
                    style="
                        font-size:0.68rem;
                        color:#10b981;
                        margin:2px 0 0;
                        line-height:1;
                        letter-spacing:0.3px;
                    ">
                    Admin Control Panel
                </p>
            </div>

        </div>

        <button
            class="sidebar-toggle"
            id="sidebarToggle"
            title="Toggle sidebar"
            type="button">
            <i class="fas fa-bars"></i>
        </button>

    </div>


    <!-- =====================================================
         ADMIN PROFILE
    ====================================================== -->
    <div class="admin-profile">

        <div class="profile-image">
            <i class="fas fa-user-circle"></i>
        </div>

        <div class="profile-info">

            <h3><?= e($adminName) ?></h3>

            <p>

                <?php if ($isSuperAdmin): ?>

                    Super Admin

                    <span
                        class="badge"
                        style="
                            background:#10b981;
                            color:#ffffff;
                            font-size:0.6rem;
                            padding:2px 5px;
                            margin-left:4px;
                        ">
                        FULL ACCESS
                    </span>

                <?php else: ?>

                    Admin

                    <span
                        class="badge"
                        style="
                            background:#e85d2c;
                            color:#ffffff;
                            font-size:0.6rem;
                            padding:2px 5px;
                            margin-left:4px;
                        ">
                        <?= current_admin_can('settings.edit') ? 'EDIT' : 'READ-ONLY' ?>
                    </span>

                <?php endif; ?>

            </p>

        </div>

        <div
            class="status-indicator"
            title="Online"></div>

    </div>


    <!-- =====================================================
         NAVIGATION
    ====================================================== -->
    <nav class="sidebar-nav">


        <!-- =================================================
             MAIN
        ================================================== -->
        <div class="nav-section">

            <h4>MAIN</h4>

            <a
                href="dashboard.php"
                class="nav-item <?= sidebarActive('dashboard', $currentPage) ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>


            <?php if ($adminCan('products')): ?>
                <a
                    href="products.php"
                    class="nav-item <?= sidebarActive('products', $currentPage) ?>">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
            <?php endif; ?>


            <?php if ($adminCan('categories')): ?>
                <a
                    href="categories.php"
                    class="nav-item <?= sidebarActive('categories', $currentPage) ?>">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                </a>
            <?php endif; ?>

        </div>


        <!-- =================================================
             COMMERCE
        ================================================== -->
        <div class="nav-section">

            <h4>COMMERCE</h4>


            <?php if ($adminCan('orders')): ?>
            <a
                href="orders.php"
                class="nav-item <?= sidebarActive('orders', $currentPage) ?>">
                <i class="fas fa-shopping-cart"></i>

                <span>Orders</span>

                <?php
                /*
                 * Read-only SELECT is allowed for Maintenance Admin.
                 */
                try {

                    if (isset($pdo)) {

                        $pendingCount = (int) $pdo
                            ->query(
                                "SELECT COUNT(*)
                                 FROM orders
                                 WHERE status = 'pending'"
                            )
                            ->fetchColumn();

                        if ($pendingCount > 0) {

                            echo '<span class="badge badge-primary">'
                                . e($pendingCount)
                                . '</span>';
                        }
                    }
                } catch (Throwable $e) {
                    // Do not break sidebar if order table/database is unavailable.
                }
                ?>

            </a>
            <?php endif; ?>


            <?php if ($adminCan('delivery')): ?>
            <a
                href="delivery.php"
                class="nav-item <?= sidebarActive('delivery', $currentPage) ?>">
                <i class="fas fa-truck"></i>
                <span>Delivery</span>
            </a>
            <?php endif; ?>


            <?php if ($adminCan('payments')): ?>
            <a
                href="payments.php"
                class="nav-item <?= sidebarActive('payments', $currentPage) ?>">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>
            <?php endif; ?>


            <?php if ($adminCan('management')): ?>
            <a
                href="management.php"
                class="nav-item <?= sidebarActive('management', $currentPage) ?>">
                <i class="fas fa-layer-group"></i>
                <span>Inventory</span>
            </a>
            <?php endif; ?>

        </div>


        <!-- =================================================
             USERS & REPORTS
        ================================================== -->
        <div class="nav-section">

            <h4>USERS & REPORTS</h4>


            <?php if ($adminCan('users')): ?>
            <a
                href="users.php"
                class="nav-item <?= sidebarActive('users', $currentPage) ?>">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <?php endif; ?>


            <?php if ($adminCan('customers')): ?>
            <a
                href="customers.php"
                class="nav-item <?= sidebarActive('customers', $currentPage) ?>">
                <i class="fas fa-user-tag"></i>
                <span>Customers</span>
            </a>
            <?php endif; ?>


            <?php if ($adminCan('notification')): ?>
            <a
                href="notification.php"
                class="nav-item <?= sidebarActive('notification', $currentPage) ?>">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
            </a>
            <?php endif; ?>


            <?php if ($adminCan('reports')): ?>
            <a
                href="reports.php"
                class="nav-item <?= sidebarActive('reports', $currentPage) ?>">
                <i class="fas fa-chart-line"></i>
                <span>Reports & Analytics</span>
            </a>
            <?php endif; ?>

        </div>


        <!-- =================================================
             DOCTOR ASSIST
        ================================================== -->
        <div class="nav-section">

            <h4>DOCTOR ASSIST</h4>


            <?php if ($adminCan('doctors')): ?>
            <a
                href="dashboard.php?page=doctors"
                class="nav-item <?= sidebarActive('doctors', $currentPage) ?>">
                <i class="fas fa-user-md"></i>
                <span>Doctor Management</span>
            </a>
            <?php endif; ?>


            <?php if ($adminCan('patients')): ?>
            <a
                href="dashboard.php?page=patients"
                class="nav-item <?= sidebarActive('patients', $currentPage) ?>">
                <i class="fas fa-procedures"></i>
                <span>Patient Records</span>
            </a>
            <?php endif; ?>


            <?php if ($adminCan('consultations')): ?>
            <a
                href="dashboard.php?page=consultations"
                class="nav-item <?= sidebarActive('consultations', $currentPage) ?>">
                <i class="fas fa-stethoscope"></i>
                <span>Consultations</span>
            </a>
            <?php endif; ?>


            <?php if ($adminCan('policies')): ?>
            <a
                href="dashboard.php?page=policies"
                class="nav-item <?= sidebarActive('policies', $currentPage) ?>">
                <i class="fas fa-sliders-h"></i>
                <span>Clinical Policies</span>
            </a>
            <?php endif; ?>

        </div>


        <!-- =================================================
             WHATSAPP SUPER-APP
        ================================================== -->
        <div class="nav-section">

            <h4>WHATSAPP SUPER-APP</h4>

            <?php if ($adminCan('whatsapp')): ?>
            <a
                href="whatsapp.php"
                class="nav-item <?= sidebarActive('whatsapp', $currentPage) ?>">
                <i class="fab fa-whatsapp"></i>
                <span>Command Center</span>
            </a>
            <?php endif; ?>

        </div>


        <!-- =================================================
             SYSTEM
        ================================================== -->
        <div class="nav-section">

            <h4>SYSTEM</h4>


            <?php if ($isSuperAdmin): ?>
                <a
                    href="admins.php"
                    class="nav-item <?= sidebarActive('admins', $currentPage) ?>">
                    <i class="fas fa-user-shield"></i>
                    <span>Admin Management</span>
                    <span
                        class="badge"
                        style="background:#0f7b3a;color:#fff;font-size:0.6rem;padding:2px 6px;margin-left:auto;">
                        SUPER
                    </span>
                </a>
            <?php endif; ?>


            <?php if ($adminCan('audit')): ?>
            <a
                href="audit_logs.php"
                class="nav-item <?= sidebarActive('audit', $currentPage) ?>">
                <i class="fas fa-shield-alt"></i>
                <span>Audit Logs</span>
            </a>
            <?php endif; ?>


            <?php if ($adminCan('settings')): ?>
            <a
                href="settings.php"
                class="nav-item <?= sidebarActive('settings', $currentPage) ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>

                <?php if (!$isSuperAdmin && !current_admin_can('settings.edit')): ?>
                    <span
                        class="nav-lock"
                        title="View only"
                        aria-label="View only">
                        <i class="fas fa-lock"></i>
                    </span>
                <?php endif; ?>

            </a>
            <?php endif; ?>


            <?php if ($adminCan('maintenance')): ?>
            <a
                href="maintenance.php"
                class="nav-item <?= sidebarActive('maintenance', $currentPage) ?>">
                <i class="fas fa-tools"></i>
                <span>Maintenance</span>
            </a>
            <?php endif; ?>


            <?php if ($adminCan('medical_verification')): ?>
            <a
                href="medical_verification.php"
                class="nav-item <?= sidebarActive('medical_verification', $currentPage) ?>">
                <i class="fas fa-clipboard-check"></i>
                <span>Medical Verification</span>
            </a>
            <?php endif; ?>


            <a
                href="../index.php"
                class="nav-item"
                target="_blank"
                rel="noopener noreferrer">
                <i class="fas fa-store"></i>
                <span>View Store</span>
            </a>

        </div>

    </nav>


    <!-- =====================================================
         SIDEBAR FOOTER
    ====================================================== -->
    <div class="sidebar-footer">

        <div class="system-status">

            <div class="status-item">

                <span>
                    <span class="status-dot"></span>
                    <span class="status-text">Database</span>
                </span>

                <span
                    style="
                        color:#10b981;
                        font-weight:600;
                    ">
                    Online
                </span>

            </div>


            <div class="status-item">

                <span>
                    <span class="status-dot"></span>
                    <span class="status-text">PHP</span>
                </span>

                <span
                    style="
                        color:#10b981;
                        font-weight:600;
                    ">
                    <?= e(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION) ?>
                </span>

            </div>


            <div class="status-item">

                <span>
                    <span class="status-dot"></span>
                    <span class="status-text">Access</span>
                </span>

                <?php if ($isSuperAdmin): ?>

                    <span
                        style="
                            color:#10b981;
                            font-weight:600;
                        ">
                        Full

                    </span>

                <?php else: ?>

                    <span
                        style="
                            color:<?= current_admin_can('settings.edit') ? '#f59e0b' : '#94a3b8' ?>;
                            font-weight:600;
                        ">
                        <?= current_admin_can('settings.edit') ? 'Limited' : 'Read-Only' ?>
                    </span>

                <?php endif; ?>

            </div>

        </div>


        <!-- =================================================
             LOGOUT
        ================================================== -->
        <button
            class="logout-btn"
            type="button"
            onclick="window.location.href='login.php?logout=1'">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </button>

    </div>
</aside>