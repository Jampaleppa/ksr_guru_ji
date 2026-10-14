<?php
// ============================================================
// admin/users.php - User / Customer management
// Access: admins with customers.view (Super Admin has full access)
// ============================================================
$currentPage        = 'users';
$requiredPermission = 'customers.view';
require_once __DIR__ . '/inc/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - KSR GURU Admin</title>
    <link rel="stylesheet" href="css/dashboard-script.css">
    <link rel="stylesheet" href="css/users-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin-theme.css?v=1.6">
    <script src="js/admin-theme.js?v=1.6" defer></script>
</head>
<body>
    <!-- Sidebar Navigation -->
    <?php $currentPage = 'users'; include 'inc/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <h1>User Management</h1>
                <div class="breadcrumb">
                    <span>Admin</span>
                    <i class="fas fa-chevron-right"></i>
                    <span>Customers</span>
                    <i class="fas fa-chevron-right"></i>
                    <span class="active">User Management</span>
                </div>
            </div>

            <div class="header-right">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search users..." id="userSearch">
                </div>

                <div class="header-actions">
                    <button class="header-btn notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-count">5</span>
                    </button>
                    <button class="header-btn theme-toggle">
                        <i class="fas fa-moon"></i>
                    </button>
                    <button class="header-btn fullscreen-btn">
                        <i class="fas fa-expand"></i>
                    </button>
                </div>

                <div class="user-menu">
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="user-info">
                        <span class="user-name">Admin User</span>
                        <span class="user-role">Super Admin</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </header>

        <!-- User Statistics -->
        <section class="user-stats">
            <div class="stats-card total-users">
                <div class="stats-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-content">
                    <h3>2,847</h3>
                    <p>Total Users</p>
                    <span class="stats-change positive">
                        <i class="fas fa-arrow-up"></i> 15.2%
                    </span>
                </div>
            </div>

            <div class="stats-card active-users">
                <div class="stats-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stats-content">
                    <h3>2,421</h3>
                    <p>Active Users</p>
                    <span class="stats-change positive">
                        <i class="fas fa-arrow-up"></i> 8.7%
                    </span>
                </div>
            </div>

            <div class="stats-card new-users">
                <div class="stats-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stats-content">
                    <h3>156</h3>
                    <p>New This Month</p>
                    <span class="stats-change positive">
                        <i class="fas fa-arrow-up"></i> 24.5%
                    </span>
                </div>
            </div>

            <div class="stats-card premium-users">
                <div class="stats-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="stats-content">
                    <h3>324</h3>
                    <p>Premium Members</p>
                    <span class="stats-change positive">
                        <i class="fas fa-arrow-up"></i> 12.3%
                    </span>
                </div>
            </div>

            <div class="stats-card orders-per-user">
                <div class="stats-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="stats-content">
                    <h3>4.2</h3>
                    <p>Avg Orders/User</p>
                    <span class="stats-change positive">
                        <i class="fas fa-arrow-up"></i> 5.6%
                    </span>
                </div>
            </div>
        </section>

        <!-- User Management Tabs -->
        <section class="user-tabs">
            <div class="tab-buttons">
                <button class="tab-btn active" data-tab="all-users">
                    <i class="fas fa-users"></i> All Users
                    <span class="tab-count">2,847</span>
                </button>
                <button class="tab-btn" data-tab="customers">
                    <i class="fas fa-user"></i> Customers
                    <span class="tab-count">2,421</span>
                </button>
                <button class="tab-btn" data-tab="staff">
                    <i class="fas fa-user-tie"></i> Staff
                    <span class="tab-count">18</span>
                </button>
                <button class="tab-btn" data-tab="admins">
                    <i class="fas fa-user-shield"></i> Administrators
                    <span class="tab-count">8</span>
                </button>
                <button class="tab-btn" data-tab="inactive">
                    <i class="fas fa-user-slash"></i> Inactive
                    <span class="tab-count">426</span>
                </button>
            </div>

            <div class="tab-content active" id="all-users">
                <div class="tab-header">
                    <div class="tab-title">
                        <h3>All Users</h3>
                        <p>Manage all user accounts in the system</p>
                    </div>
                    <div class="tab-actions">
                        <button class="btn btn-primary" id="addUserBtn">
                            <i class="fas fa-user-plus"></i> Add User
                        </button>
                        <button class="btn btn-secondary" id="exportUsers">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- User Filters -->
        <section class="user-filters">
            <div class="filter-group">
                <div class="filter-item">
                    <label for="roleFilter">Role</label>
                    <select id="roleFilter" class="filter-select">
                        <option value="">All Roles</option>
                        <option value="customer">Customer</option>
                        <option value="delivery">Delivery Staff</option>
                        <option value="support">Support Staff</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter" class="filter-select">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label for="dateFilter">Registration Date</label>
                    <select id="dateFilter" class="filter-select">
                        <option value="all-time">All Time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="quarter">This Quarter</option>
                        <option value="year">This Year</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label for="sortBy">Sort By</label>
                    <select id="sortBy" class="filter-select">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="name-asc">Name (A-Z)</option>
                        <option value="name-desc">Name (Z-A)</option>
                        <option value="orders-high">Orders (High to Low)</option>
                        <option value="orders-low">Orders (Low to High)</option>
                    </select>
                </div>
            </div>

            <div class="filter-actions">
                <button class="btn btn-primary" id="applyFilters">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <button class="btn btn-outline" id="resetFilters">
                    <i class="fas fa-redo"></i> Reset
                </button>
                <div class="view-toggle">
                    <button class="view-btn active" data-view="list">
                        <i class="fas fa-list"></i>
                    </button>
                    <button class="view-btn" data-view="grid">
                        <i class="fas fa-th-large"></i>
                    </button>
                </div>
            </div>

            <div class="custom-date-range" id="customDateRange" style="display: none;">
                <input type="date" id="startDate" class="date-input">
                <span>to</span>
                <input type="date" id="endDate" class="date-input">
            </div>
        </section>

        <!-- Bulk Actions Bar -->
        <div class="bulk-actions-bar" id="bulkActionsBar">
            <div class="bulk-selection">
                <span id="selectedCount">0 users selected</span>
                <button class="btn btn-sm btn-outline" id="selectAll">Select All</button>
                <button class="btn btn-sm btn-outline" id="clearSelection">Clear Selection</button>
            </div>
            <div class="bulk-actions">
                <select class="bulk-action-select">
                    <option value="">Bulk Actions</option>
                    <option value="activate">Activate Users</option>
                    <option value="deactivate">Deactivate Users</option>
                    <option value="assign-role">Assign Role</option>
                    <option value="send-email">Send Email</option>
                    <option value="delete">Delete Users</option>
                </select>
                <button class="btn btn-sm btn-primary" id="applyBulkAction">
                    <i class="fas fa-check"></i> Apply
                </button>
            </div>
        </div>

        <!-- Users List View -->
        <section class="users-list" id="usersListView">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th width="40">
                                <input type="checkbox" id="selectAllUsers">
                            </th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Joined</th>
                            <th>Last Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <!-- Users will be populated here -->
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Users Grid View -->
        <section class="users-grid" id="usersGridView" style="display: none;">
            <div class="grid-container" id="usersGridContainer">
                <!-- User cards will be populated here -->
            </div>
        </section>

        <!-- Pagination -->
        <section class="users-pagination">
            <div class="pagination-info">
                Showing <span id="showingFrom">1</span>-<span id="showingTo">20</span> of <span id="totalUsers">2,847</span> users
            </div>
            <div class="pagination-controls">
                <button class="pagination-btn" id="firstPage">
                    <i class="fas fa-angle-double-left"></i>
                </button>
                <button class="pagination-btn" id="prevPage">
                    <i class="fas fa-angle-left"></i>
                </button>
                <div class="page-numbers">
                    <button class="page-btn active">1</button>
                    <button class="page-btn">2</button>
                    <button class="page-btn">3</button>
                    <span class="page-dots">...</span>
                    <button class="page-btn">142</button>
                </div>
                <button class="pagination-btn" id="nextPage">
                    <i class="fas fa-angle-right"></i>
                </button>
                <button class="pagination-btn" id="lastPage">
                    <i class="fas fa-angle-double-right"></i>
                </button>
            </div>
            <div class="pagination-perpage">
                <label for="perPage">Per page:</label>
                <select id="perPage" class="perpage-select">
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                </select>
            </div>
        </section>

        <!-- User Analytics -->
        <section class="user-analytics">
            <div class="analytics-card">
                <h4>User Growth</h4>
                <div class="chart-placeholder">
                    <i class="fas fa-chart-line"></i>
                    <p>User growth chart will appear here</p>
                </div>
            </div>
            <div class="analytics-card">
                <h4>Top Users by Orders</h4>
                <div class="top-users-list">
                    <!-- Top users will be populated here -->
                </div>
            </div>
            <div class="analytics-card">
                <h4>User Activity</h4>
                <div class="activity-stats">
                    <div class="activity-item">
                        <i class="fas fa-shopping-cart"></i>
                        <div>
                            <span class="activity-count">1,246</span>
                            <span class="activity-label">Orders Today</span>
                        </div>
                    </div>
                    <div class="activity-item">
                        <i class="fas fa-user-plus"></i>
                        <div>
                            <span class="activity-count">42</span>
                            <span class="activity-label">New Users Today</span>
                        </div>
                    </div>
                    <div class="activity-item">
                        <i class="fas fa-comment"></i>
                        <div>
                            <span class="activity-count">89</span>
                            <span class="activity-label">Reviews Today</span>
                        </div>
                    </div>
                    <div class="activity-item">
                        <i class="fas fa-star"></i>
                        <div>
                            <span class="activity-count">156</span>
                            <span class="activity-label">Ratings Today</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="dashboard-footer">
            <div class="footer-content">
                <div class="footer-info">
                    <p>© 2024 KSR GURU Admin Dashboard v2.1.0</p>
                    <p>User Management System | <span id="userLastSync">Last sync: Just now</span></p>
                </div>
                <div class="footer-links">
                    <a href="#" id="syncUsers">
                        <i class="fas fa-sync-alt"></i> Sync Users
                    </a>
                    <a href="#">User Reports</a>
                    <a href="#">Activity Logs</a>
                </div>
            </div>
        </footer>
    </main>

    <!-- Add/Edit User Modal -->
    <div class="modal" id="userModal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3 id="modalTitle">Add New User</h3>
                <button class="close-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="userForm">
                <div class="modal-body">
                    <div class="form-tabs">
                        <button type="button" class="tab-btn active" data-tab="basic">Basic Info</button>
                        <button type="button" class="tab-btn" data-tab="address">Address</button>
                        <button type="button" class="tab-btn" data-tab="permissions">Permissions</button>
                        <button type="button" class="tab-btn" data-tab="preferences">Preferences</button>
                    </div>

                    <div class="tab-content active" id="basicTab">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstName">First Name *</label>
                                <input type="text" id="firstName" required>
                            </div>
                            <div class="form-group">
                                <label for="lastName">Last Name *</label>
                                <input type="text" id="lastName" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number *</label>
                                <input type="tel" id="phone" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="userRole">Role *</label>
                                <select id="userRole" required>
                                    <option value="">Select Role</option>
                                    <option value="customer">Customer</option>
                                    <option value="delivery">Delivery Staff</option>
                                    <option value="support">Support Staff</option>
                                    <option value="manager">Manager</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="userStatus">Status</label>
                                <select id="userStatus">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="pending">Pending Verification</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <div class="password-field">
                                <input type="password" id="password" required>
                                <button type="button" class="show-password" id="showPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength" id="passwordStrength">
                                <div class="strength-bar"></div>
                                <span class="strength-text">Password strength</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm Password *</label>
                            <input type="password" id="confirmPassword" required>
                        </div>
                        <div class="form-group">
                            <label for="profilePicture">Profile Picture</label>
                            <div class="profile-upload">
                                <div class="profile-preview" id="profilePreview">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <input type="file" id="profilePicture" accept="image/*">
                                <label for="profilePicture" class="btn btn-outline btn-sm">
                                    <i class="fas fa-upload"></i> Upload Photo
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="tab-content" id="addressTab">
                        <div class="form-group">
                            <label for="addressLine1">Address Line 1 *</label>
                            <input type="text" id="addressLine1" required>
                        </div>
                        <div class="form-group">
                            <label for="addressLine2">Address Line 2</label>
                            <input type="text" id="addressLine2">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City *</label>
                                <input type="text" id="city" required>
                            </div>
                            <div class="form-group">
                                <label for="state">State *</label>
                                <input type="text" id="state" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="zipCode">ZIP Code *</label>
                                <input type="text" id="zipCode" required>
                            </div>
                            <div class="form-group">
                                <label for="country">Country *</label>
                                <select id="country" required>
                                    <option value="IN">India</option>
                                    <option value="US">United States</option>
                                    <option value="UK">United Kingdom</option>
                                    <option value="CA">Canada</option>
                                    <option value="AU">Australia</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="sameAsBilling">
                                <span>Shipping address same as billing</span>
                            </label>
                        </div>
                    </div>

                    <div class="tab-content" id="permissionsTab">
                        <div class="permissions-section">
                            <h5>System Permissions</h5>
                            <div class="permissions-grid">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="permissionDashboard">
                                    <span>Access Dashboard</span>
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" id="permissionOrders">
                                    <span>Manage Orders</span>
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" id="permissionProducts">
                                    <span>Manage Products</span>
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" id="permissionUsers">
                                    <span>Manage Users</span>
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" id="permissionReports">
                                    <span>View Reports</span>
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" id="permissionSettings">
                                    <span>System Settings</span>
                                </label>
                            </div>
                        </div>
                        <div class="permissions-section">
                            <h5>Customer Features</h5>
                            <div class="permissions-grid">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="featurePremium" checked>
                                    <span>Premium Features</span>
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" id="featureDiscount" checked>
                                    <span>Special Discounts</span>
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" id="featureEarlyAccess" checked>
                                    <span>Early Access</span>
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" id="featurePrioritySupport">
                                    <span>Priority Support</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="tab-content" id="preferencesTab">
                        <div class="form-group">
                            <label for="language">Preferred Language</label>
                            <select id="language">
                                <option value="en">English</option>
                                <option value="hi">Hindi</option>
                                <option value="ta">Tamil</option>
                                <option value="te">Telugu</option>
                                <option value="kn">Kannada</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="currency">Preferred Currency</label>
                            <select id="currency">
                                <option value="INR">Indian Rupee (₹)</option>
                                <option value="USD">US Dollar ($)</option>
                                <option value="EUR">Euro (€)</option>
                                <option value="GBP">British Pound (£)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="timezone">Timezone</label>
                            <select id="timezone">
                                <option value="IST">India Standard Time (IST)</option>
                                <option value="UTC">Coordinated Universal Time (UTC)</option>
                                <option value="EST">Eastern Standard Time (EST)</option>
                                <option value="PST">Pacific Standard Time (PST)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="notificationEmail">Email Notifications</label>
                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="notifyOrders" checked>
                                    <span>Order Updates</span>
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" id="notifyPromotions" checked>
                                    <span>Promotions & Offers</span>
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" id="notifyNewsletter">
                                    <span>Newsletter</span>
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="communicationPref">Communication Preference</label>
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input type="radio" name="communication" value="email" checked>
                                    <span>Email Only</span>
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="communication" value="sms">
                                    <span>SMS Only</span>
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="communication" value="both">
                                    <span>Email & SMS</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="modal-actions">
                        <button type="button" class="btn btn-outline close-modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveUser">
                            <i class="fas fa-save"></i> Save User
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal" id="userDetailsModal">
        <div class="modal-content modal-xl">
            <div class="modal-header">
                <h3>User Details - <span id="userNameDisplay">John Doe</span></h3>
                <button class="close-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="user-details-tabs">
                    <button type="button" class="tab-btn active" data-tab="profile">Profile</button>
                    <button type="button" class="tab-btn" data-tab="orders">Orders</button>
                    <button type="button" class="tab-btn" data-tab="activity">Activity</button>
                    <button type="button" class="tab-btn" data-tab="notes">Notes</button>
                    <button type="button" class="tab-btn" data-tab="security">Security</button>
                </div>

                <div class="user-details-content">
                    <div class="tab-content active" id="profileTab">
                        <div class="profile-header">
                            <div class="profile-avatar-large">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="profile-info">
                                <h4 id="detailUserName">John Doe</h4>
                                <p class="user-email" id="detailUserEmail">john@example.com</p>
                                <div class="user-meta">
                                    <span class="user-role customer" id="detailUserRole">Customer</span>
                                    <span class="user-status active" id="detailUserStatus">Active</span>
                                    <span class="user-id" id="detailUserId">ID: U-001</span>
                                </div>
                            </div>
                            <div class="profile-stats">
                                <div class="stat-item">
                                    <span class="stat-value" id="detailTotalOrders">42</span>
                                    <span class="stat-label">Total Orders</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value" id="detailTotalSpent">₹85,420</span>
                                    <span class="stat-label">Total Spent</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value" id="detailAvgOrder">₹2,034</span>
                                    <span class="stat-label">Avg. Order</span>
                                </div>
                            </div>
                        </div>

                        <div class="profile-details">
                            <div class="detail-section">
                                <h5>Contact Information</h5>
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <span class="detail-label">Phone:</span>
                                        <span class="detail-value" id="detailUserPhone">+91 98765 43210</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Email:</span>
                                        <span class="detail-value" id="detailUserEmailFull">john@example.com</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Joined:</span>
                                        <span class="detail-value" id="detailJoinDate">Jan 15, 2023</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Last Active:</span>
                                        <span class="detail-value" id="detailLastActive">Today, 14:30</span>
                                    </div>
                                </div>
                            </div>

                            <div class="detail-section">
                                <h5>Address</h5>
                                <div class="address-card">
                                    <h6>Billing Address</h6>
                                    <p id="detailBillingAddress">123, Main Street, Bangalore - 560001, Karnataka, India</p>
                                </div>
                                <div class="address-card">
                                    <h6>Shipping Address</h6>
                                    <p id="detailShippingAddress">Same as billing address</p>
                                </div>
                            </div>

                            <div class="detail-section">
                                <h5>Preferences</h5>
                                <div class="preferences-grid">
                                    <div class="preference-item">
                                        <span class="pref-label">Language:</span>
                                        <span class="pref-value" id="detailLanguage">English</span>
                                    </div>
                                    <div class="preference-item">
                                        <span class="pref-label">Currency:</span>
                                        <span class="pref-value" id="detailCurrency">Indian Rupee (₹)</span>
                                    </div>
                                    <div class="preference-item">
                                        <span class="pref-label">Timezone:</span>
                                        <span class="pref-value" id="detailTimezone">IST</span>
                                    </div>
                                    <div class="preference-item">
                                        <span class="pref-label">Notifications:</span>
                                        <span class="pref-value" id="detailNotifications">Email & SMS</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-content" id="ordersTab">
                        <div class="user-orders">
                            <div class="orders-header">
                                <h5>Order History</h5>
                                <button class="btn btn-sm btn-outline" id="viewAllOrders">
                                    View All Orders
                                </button>
                            </div>
                            <div class="orders-table">
                                <!-- User orders will be loaded here -->
                            </div>
                        </div>
                    </div>

                    <div class="tab-content" id="activityTab">
                        <div class="user-activity">
                            <div class="activity-header">
                                <h5>Recent Activity</h5>
                                <div class="activity-filters">
                                    <select class="activity-period">
                                        <option value="7">Last 7 Days</option>
                                        <option value="30">Last 30 Days</option>
                                        <option value="90">Last 90 Days</option>
                                        <option value="365">Last Year</option>
                                    </select>
                                </div>
                            </div>
                            <div class="activity-timeline">
                                <!-- Activity timeline will be loaded here -->
                            </div>
                        </div>
                    </div>

                    <div class="tab-content" id="notesTab">
                        <div class="user-notes">
                            <div class="notes-header">
                                <h5>Internal Notes</h5>
                                <button class="btn btn-sm btn-primary" id="addNoteBtn">
                                    <i class="fas fa-plus"></i> Add Note
                                </button>
                            </div>
                            <div class="notes-list">
                                <!-- Notes will be loaded here -->
                            </div>
                            <div class="add-note-form" id="addNoteForm" style="display: none;">
                                <textarea placeholder="Add a note about this user..." rows="3"></textarea>
                                <div class="note-actions">
                                    <button class="btn btn-sm btn-outline" id="cancelNote">Cancel</button>
                                    <button class="btn btn-sm btn-primary" id="saveNote">Save Note</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-content" id="securityTab">
                        <div class="user-security">
                            <div class="security-section">
                                <h5>Account Security</h5>
                                <div class="security-info">
                                    <div class="security-item">
                                        <i class="fas fa-key"></i>
                                        <div>
                                            <span class="security-label">Last Password Change</span>
                                            <span class="security-value" id="lastPasswordChange">2 weeks ago</span>
                                        </div>
                                    </div>
                                    <div class="security-item">
                                        <i class="fas fa-shield-alt"></i>
                                        <div>
                                            <span class="security-label">Two-Factor Authentication</span>
                                            <span class="security-value" id="twoFactorStatus">Not Enabled</span>
                                        </div>
                                    </div>
                                    <div class="security-item">
                                        <i class="fas fa-history"></i>
                                        <div>
                                            <span class="security-label">Login History</span>
                                            <span class="security-value" id="lastLogin">Today, 14:30</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="security-section">
                                <h5>Security Actions</h5>
                                <div class="security-actions">
                                    <button class="btn btn-outline" id="resetPasswordBtn">
                                        <i class="fas fa-key"></i> Reset Password
                                    </button>
                                    <button class="btn btn-outline" id="forceLogoutBtn">
                                        <i class="fas fa-sign-out-alt"></i> Force Logout
                                    </button>
                                    <button class="btn btn-outline" id="enable2FABtn">
                                        <i class="fas fa-shield-alt"></i> Enable 2FA
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="user-actions">
                    <button class="btn btn-outline" id="editUserBtn">
                        <i class="fas fa-edit"></i> Edit User
                    </button>
                    <button class="btn btn-secondary" id="sendMessageBtn">
                        <i class="fas fa-envelope"></i> Send Message
                    </button>
                    <button class="btn btn-primary" id="updateStatusBtn">
                        <i class="fas fa-sync-alt"></i> Update Status
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="close-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="delete-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p id="deleteMessage">Are you sure you want to delete this user?</p>
                </div>
                <div class="delete-details" id="deleteDetails">
                    <p><strong>User:</strong> <span id="deleteUserName">John Doe</span></p>
                    <p><strong>Email:</strong> <span id="deleteUserEmail">john@example.com</span></p>
                    <p><strong>Orders:</strong> <span id="deleteUserOrders">42</span></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline close-modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete User</button>
            </div>
        </div>
    </div>

    <!-- Import Users Modal -->
    <div class="modal" id="importModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Import Users</h3>
                <button class="close-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="import-steps">
                    <div class="step active">
                        <span class="step-number">1</span>
                        <span class="step-label">Download Template</span>
                    </div>
                    <div class="step">
                        <span class="step-number">2</span>
                        <span class="step-label">Upload CSV</span>
                    </div>
                    <div class="step">
                        <span class="step-number">3</span>
                        <span class="step-label">Map Fields</span>
                    </div>
                    <div class="step">
                        <span class="step-number">4</span>
                        <span class="step-label">Import</span>
                    </div>
                </div>
                <div class="import-content">
                    <p>Download the CSV template and fill in user data, then upload it here.</p>
                    <div class="import-actions">
                        <button class="btn btn-outline" id="downloadTemplate">
                            <i class="fas fa-download"></i> Download Template
                        </button>
                        <div class="file-upload">
                            <input type="file" id="importFile" accept=".csv,.xlsx">
                            <label for="importFile" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Choose File
                            </label>
                            <span id="fileName">No file chosen</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline close-modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="startImport" disabled>
                    Start Import
                </button>
            </div>
        </div>
    </div>

    <!-- Send Email Modal -->
    <div class="modal" id="emailModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Send Email to Users</h3>
                <button class="close-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="emailForm">
                    <div class="form-group">
                        <label>Recipients</label>
                        <div class="recipients-info">
                            <span id="recipientsCount">0 users selected</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="emailSubject">Subject *</label>
                        <input type="text" id="emailSubject" required placeholder="Enter email subject">
                    </div>
                    <div class="form-group">
                        <label for="emailTemplate">Template</label>
                        <select id="emailTemplate">
                            <option value="">Custom Message</option>
                            <option value="welcome">Welcome Email</option>
                            <option value="promotion">Promotion Email</option>
                            <option value="newsletter">Newsletter</option>
                            <option value="update">System Update</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="emailMessage">Message *</label>
                        <textarea id="emailMessage" rows="6" required placeholder="Enter your message..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="sendCopy">
                            <span>Send a copy to myself</span>
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline close-modal">Cancel</button>
                <button type="submit" form="emailForm" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Email
                </button>
            </div>
        </div>
    </div>

    <script src="js/dashboard-script.js"></script>
    <script src="js/users-script.js"></script>
</body>
</html>