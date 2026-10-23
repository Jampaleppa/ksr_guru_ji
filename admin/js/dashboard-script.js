/* KSR GURU JI - Admin Dashboard Shared Scripts Stub */
// Reserved for future layout extensions
/* KSR GURU JI - Admin Dashboard Shared Scripts Stub */
// Reserved for future layout extensions

/* ============================================================
   Shared admin behaviours (used by users.php and dashboard.php)
   ============================================================ */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // 1. Add data-label attributes to table cells so the mobile
        //    card CSS (dashboard-script.css) can show column names.
        document.querySelectorAll('.table-container table').forEach(function (table) {
            const headers = [...table.querySelectorAll('thead th')].map(
                (th) => th.textContent.trim(),
            );
            table.querySelectorAll('tbody tr').forEach(function (row) {
                [...row.children].forEach(function (cell, i) {
                    if (headers[i] && !cell.hasAttribute('data-label')) {
                        cell.setAttribute('data-label', headers[i]);
                    }
                });
            });
        });

        // 2. Keep modals within the viewport on phones.
        document.querySelectorAll('.modal').forEach(function (modal) {
            modal.querySelectorAll('[data-dismiss], .btn-cancel').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    modal.classList.remove('active');
                    modal.style.display = 'none';
                });
            });
        });

        // 3. Generic confirm guards for destructive buttons.
        document.querySelectorAll('[data-confirm]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (!window.confirm(el.getAttribute('data-confirm'))) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        });
    });
})();
