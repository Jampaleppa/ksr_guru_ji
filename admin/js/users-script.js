/* ============================================================
   KSR GURU JI - DYNAMIC USER MANAGEMENT
   MySQL -> PHP API -> JavaScript
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    const tableBody = document.getElementById('usersTableBody');
    const searchInput = document.getElementById('userSearch');
    const selectAll = document.getElementById('selectAllUsers');

    if (!tableBody) {
        return;
    }

    let users = [];
    let currentPage = 1;
    const itemsPerPage = 5;

    /*
     * --------------------------------------------------------
     * CURRENT ADMIN ROLE
     * --------------------------------------------------------
     *
     * Set this from PHP in users.php:
     *
     * window.KSR_ADMIN_ROLE = <?= json_encode($_SESSION['user_role'] ?? 'admin') ?>;
     *
     */

    const currentRole = window.KSR_ADMIN_ROLE || 'admin';
    const isSuperAdmin = currentRole === 'super_admin';


    /* ========================================================
       LOAD USERS FROM DATABASE
    ======================================================== */

    async function loadUsers() {

        try {

            tableBody.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align:center;padding:30px;">
                        <i class="fas fa-spinner fa-spin"></i>
                        Loading users...
                    </td>
                </tr>
            `;

            const response = await fetch(
                'api/users.php?action=list',
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            );

            if (!response.ok) {
                throw new Error(
                    'HTTP error: ' + response.status
                );
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(
                    data.message || 'Unable to load users.'
                );
            }

            users = Array.isArray(data.users)
                ? data.users
                : [];

            currentPage = 1;

            renderUsers();

        } catch (error) {

            console.error(
                'User loading error:',
                error
            );

            tableBody.innerHTML = `
                <tr>
                    <td colspan="9"
                        style="
                            text-align:center;
                            padding:40px;
                            color:#dc2626;
                        "
                    >
                        <i class="fas fa-exclamation-triangle"></i>
                        <br><br>
                        Unable to load users.
                        <br>
                        <small>
                            ${escapeHtml(error.message)}
                        </small>
                    </td>
                </tr>
            `;
        }
    }


    /* ========================================================
       ESCAPE HTML
    ======================================================== */

    function escapeHtml(value) {

        const div = document.createElement('div');

        div.textContent =
            value === null || value === undefined
                ? ''
                : String(value);

        return div.innerHTML;
    }


    /* ========================================================
       ROLE BADGE
    ======================================================== */

    function getRoleBadge(role) {

        switch (role) {

            case 'super_admin':
                return `
                    <span class="status-badge paid">
                        Super Admin
                    </span>
                `;

            case 'admin':
                return `
                    <span class="status-badge paid">
                        Admin
                    </span>
                `;

            case 'staff':
                return `
                    <span class="status-badge shipped">
                        Staff
                    </span>
                `;

            case 'delivery_boy':
            case 'delivery':
                return `
                    <span class="status-badge processing">
                        Courier
                    </span>
                `;

            case 'doctor':
                return `
                    <span class="status-badge processing">
                        Doctor
                    </span>
                `;

            case 'pharmacist':
                return `
                    <span class="status-badge processing">
                        Pharmacist
                    </span>
                `;

            case 'customer':
            default:
                return `
                    <span class="status-badge pending">
                        Customer
                    </span>
                `;
        }
    }


    /* ========================================================
       STATUS BADGE
    ======================================================== */

    function getStatusBadge(status) {

        const normalized =
            String(status || '').toLowerCase();

        if (
            normalized === 'active' ||
            normalized === '1' ||
            normalized === 'enabled'
        ) {
            return `
                <span class="status-badge active">
                    Active
                </span>
            `;
        }

        if (normalized === 'suspended') {
            return `
                <span class="status-badge cancelled">
                    Suspended
                </span>
            `;
        }

        return `
            <span class="status-badge cancelled">
                Inactive
            </span>
        `;
    }


    /* ========================================================
       RENDER USERS
    ======================================================== */

    function renderUsers() {

        let filtered = [...users];

        /* ----------------------------------------------------
           SEARCH
        ---------------------------------------------------- */

        const query =
            searchInput?.value
                ?.trim()
                .toLowerCase() || '';

        if (query) {

            filtered = filtered.filter(user => {

                const name =
                    String(user.name || '')
                        .toLowerCase();

                const email =
                    String(user.email || '')
                        .toLowerCase();

                const role =
                    String(user.role || '')
                        .toLowerCase();

                const phone =
                    String(user.phone || '')
                        .toLowerCase();

                return (
                    name.includes(query) ||
                    email.includes(query) ||
                    role.includes(query) ||
                    phone.includes(query)
                );
            });
        }


        /* ----------------------------------------------------
           ROLE FILTER
        ---------------------------------------------------- */

        const activeFilter =
            document.querySelector(
                '.filter-btn.active'
            );

        const selectedRole =
            activeFilter?.dataset?.role || 'all';

        if (selectedRole !== 'all') {

            filtered = filtered.filter(
                user =>
                    String(user.role) === selectedRole
            );
        }


        /* ----------------------------------------------------
           PAGINATION
        ---------------------------------------------------- */

        const total =
            filtered.length;

        const totalPages =
            Math.max(
                1,
                Math.ceil(total / itemsPerPage)
            );

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        const start =
            (currentPage - 1) *
            itemsPerPage;

        const paginated =
            filtered.slice(
                start,
                start + itemsPerPage
            );


        /* ----------------------------------------------------
           COUNTS
        ---------------------------------------------------- */

        const totalUsersEl =
            document.getElementById('totalUsers');

        if (totalUsersEl) {
            totalUsersEl.textContent =
                total;
        }


        const showingFromEl =
            document.getElementById('showingFrom');

        if (showingFromEl) {
            showingFromEl.textContent =
                total === 0
                    ? 0
                    : start + 1;
        }


        const showingToEl =
            document.getElementById('showingTo');

        if (showingToEl) {
            showingToEl.textContent =
                Math.min(
                    start + paginated.length,
                    total
                );
        }


        /* ----------------------------------------------------
           EMPTY STATE
        ---------------------------------------------------- */

        if (paginated.length === 0) {

            tableBody.innerHTML = `
                <tr>
                    <td colspan="9"
                        style="
                            text-align:center;
                            padding:45px;
                        "
                    >
                        <i
                            class="fas fa-users"
                            style="
                                font-size:35px;
                                opacity:.35;
                            "
                        ></i>

                        <br><br>

                        No users found.
                    </td>
                </tr>
            `;

            updatePagination(
                total,
                totalPages
            );

            return;
        }


        /* ----------------------------------------------------
           TABLE
        ---------------------------------------------------- */

        tableBody.innerHTML =
            paginated.map(user => {

                const id =
                    Number(user.id);

                const name =
                    escapeHtml(
                        user.name || 'Unknown User'
                    );

                const email =
                    escapeHtml(
                        user.email || '-'
                    );

                const phone =
                    escapeHtml(
                        user.phone || ''
                    );

                const role =
                    escapeHtml(
                        user.role || 'customer'
                    );

                const orders =
                    Number(user.orders || 0);

                const spent =
                    Number(user.spent || 0);

                const joined =
                    escapeHtml(
                        user.joined || '-'
                    );

                const active =
                    escapeHtml(
                        user.active || '-'
                    );

                const initial =
                    escapeHtml(
                        (user.name || 'U')
                            .charAt(0)
                            .toUpperCase()
                    );

                return `
                    <tr>

                        <td>
                            <input
                                type="checkbox"
                                name="selected_users[]"
                                value="${id}"
                            >
                        </td>

                        <td>

                            <div
                                style="
                                    display:flex;
                                    align-items:center;
                                    gap:12px;
                                "
                            >

                                <div
                                    style="
                                        width:36px;
                                        height:36px;
                                        border-radius:50%;
                                        background:#eaf7ed;
                                        color:#0f7b3a;
                                        display:flex;
                                        align-items:center;
                                        justify-content:center;
                                        font-weight:700;
                                    "
                                >
                                    ${initial}
                                </div>

                                <div>

                                    <div
                                        style="
                                            font-weight:600;
                                            color:var(--text-primary);
                                        "
                                    >
                                        ${name}
                                    </div>

                                    <div
                                        style="
                                            font-size:.75rem;
                                            color:var(--text-muted);
                                        "
                                    >
                                        ${email}
                                    </div>

                                    ${
                                        phone
                                            ? `
                                                <div
                                                    style="
                                                        font-size:.72rem;
                                                        color:var(--text-muted);
                                                    "
                                                >
                                                    ${phone}
                                                </div>
                                            `
                                            : ''
                                    }

                                </div>

                            </div>

                        </td>

                        <td>
                            ${getRoleBadge(role)}
                        </td>

                        <td>
                            ${getStatusBadge(user.status)}
                        </td>

                        <td>
                            ${orders}
                        </td>

                        <td>
                            ₹${spent.toFixed(2)}
                        </td>

                        <td>
                            ${joined}
                        </td>

                        <td>
                            ${active}
                        </td>

                        <td>

                            <button
                                class="btn btn-outline btn-sm edit-user-btn"
                                data-id="${id}"
                                style="
                                    padding:6px 12px !important;
                                    border-radius:4px !important;
                                "
                                ${
                                    !isSuperAdmin
                                        ? 'disabled title="Super Admin only"'
                                        : ''
                                }
                            >
                                <i class="fas fa-edit"></i>
                            </button>

                            <button
                                class="btn btn-danger btn-sm delete-user-btn"
                                data-id="${id}"
                                style="
                                    padding:6px 12px !important;
                                    border-radius:4px !important;
                                "
                                ${
                                    !isSuperAdmin
                                        ? 'disabled title="Super Admin only"'
                                        : ''
                                }
                            >
                                <i class="fas fa-trash"></i>
                            </button>

                        </td>

                    </tr>
                `;

            }).join('');


        setupActionEvents();

        updatePagination(
            total,
            totalPages
        );
    }


    /* ========================================================
       ACTION EVENTS
    ======================================================== */

    function setupActionEvents() {

        document
            .querySelectorAll('.delete-user-btn')
            .forEach(btn => {

                btn.addEventListener(
                    'click',
                    async function () {

                        if (!isSuperAdmin) {

                            alert(
                                'Maintenance Admin is read-only. Super Admin permission is required.'
                            );

                            return;
                        }

                        const id =
                            Number(
                                this.dataset.id
                            );

                        const user =
                            users.find(
                                u =>
                                    Number(u.id) === id
                            );

                        if (!user) {
                            return;
                        }

                        if (
                            !confirm(
                                `Are you sure you want to delete "${user.name}"?`
                            )
                        ) {
                            return;
                        }

                        await deleteUser(id);
                    }
                );
            });


        document
            .querySelectorAll('.edit-user-btn')
            .forEach(btn => {

                btn.addEventListener(
                    'click',
                    function () {

                        if (!isSuperAdmin) {

                            alert(
                                'Maintenance Admin is read-only. Super Admin permission is required.'
                            );

                            return;
                        }

                        const id =
                            Number(
                                this.dataset.id
                            );

                        /*
                         * Connect this to your edit-user modal/page.
                         */
                        window.location.href =
                            'user_edit.php?id=' +
                            encodeURIComponent(id);
                    }
                );
            });
    }


    /* ========================================================
       DELETE USER
    ======================================================== */

    async function deleteUser(id) {

        try {

            const response = await fetch(
                'api/users.php',
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type':
                            'application/json',

                        'Accept':
                            'application/json'
                    },

                    body: JSON.stringify({
                        action: 'delete',
                        id: id
                    })
                }
            );

            const data =
                await response.json();

            if (!response.ok || !data.success) {

                throw new Error(
                    data.message ||
                    'Unable to delete user.'
                );
            }

            await loadUsers();

        } catch (error) {

            console.error(
                'Delete user error:',
                error
            );

            alert(
                error.message ||
                'Unable to delete user.'
            );
        }
    }


    /* ========================================================
       PAGINATION
    ======================================================== */

    function updatePagination(
        total,
        totalPages
    ) {

        const pagination =
            document.getElementById(
                'usersPagination'
            );

        if (!pagination) {
            return;
        }

        pagination.innerHTML = '';

        if (totalPages <= 1) {
            return;
        }

        for (
            let page = 1;
            page <= totalPages;
            page++
        ) {

            const button =
                document.createElement('button');

            button.type = 'button';

            button.className =
                'btn btn-sm ' +
                (
                    page === currentPage
                        ? 'btn-primary'
                        : 'btn-outline'
                );

            button.textContent =
                page;

            button.addEventListener(
                'click',
                function () {

                    currentPage = page;

                    renderUsers();
                }
            );

            pagination.appendChild(
                button
            );
        }
    }


    /* ========================================================
       SEARCH
    ======================================================== */

    searchInput?.addEventListener(
        'input',
        function () {

            currentPage = 1;

            renderUsers();
        }
    );


    /* ========================================================
       ROLE FILTERS
    ======================================================== */

    document
        .querySelectorAll('.filter-btn')
        .forEach(btn => {

            btn.addEventListener(
                'click',
                function () {

                    document
                        .querySelectorAll(
                            '.filter-btn'
                        )
                        .forEach(item =>
                            item.classList.remove(
                                'active'
                            )
                        );

                    this.classList.add(
                        'active'
                    );

                    currentPage = 1;

                    renderUsers();
                }
            );
        });


    /* ========================================================
       SELECT ALL
    ======================================================== */

    if (selectAll) {

        selectAll.addEventListener(
            'change',
            function () {

                document
                    .querySelectorAll(
                        'input[name="selected_users[]"]'
                    )
                    .forEach(cb => {

                        cb.checked =
                            selectAll.checked;
                    });
            }
        );
    }


    /* ========================================================
       INITIAL LOAD
    ======================================================== */

    loadUsers();

});