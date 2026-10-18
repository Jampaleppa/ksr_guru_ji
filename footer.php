<?php
/**
 * includes/footer.php - Admin Panel Footer
 * 
 * Closes the main content area and includes common JavaScript.
 * Accepts optional $extraJs for page-specific scripts.
 */
?>
        <!-- End of page content -->
    </main>

    <!-- ===== TOAST NOTIFICATION ===== -->
    <div class="toast" id="toast"></div>

    <!-- ===== GLOBAL JAVASCRIPT ===== -->
    <script>
        // ================================================================
        // TOAST
        // ================================================================
        function toast(message, type = 'info') {
            const el = document.getElementById('toast');
            el.textContent = message;
            el.className = 'toast show ' + type;
            clearTimeout(el._timeout);
            el._timeout = setTimeout(() => { el.classList.remove('show'); }, 2500);
        }
        window.toast = toast;

        // ================================================================
        // SIDEBAR TOGGLE (mobile)
        // ================================================================
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            // Check if we have a toggle button (e.g., in the header)
            const toggleBtn = document.getElementById('sidebarToggle');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.toggle('mobile-open');
                    } else {
                        sidebar.classList.toggle('collapsed');
                    }
                });
            }

            // Close sidebar on outside click (mobile)
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768 && sidebar.classList.contains('mobile-open')) {
                    if (!sidebar.contains(e.target) && !e.target.closest('#sidebarToggle')) {
                        sidebar.classList.remove('mobile-open');
                    }
                }
            });
        });

        // ================================================================
        // THEME TOGGLE (dark/light)
        // ================================================================
        document.getElementById('themeToggle')?.addEventListener('click', function() {
            document.body.classList.toggle('dark');
            const icon = this.querySelector('i');
            if (document.body.classList.contains('dark')) {
                icon.className = 'fas fa-sun';
                // You can save preference in localStorage
                localStorage.setItem('adminTheme', 'dark');
            } else {
                icon.className = 'fas fa-moon';
                localStorage.setItem('adminTheme', 'light');
            }
        });

        // Load saved theme
        document.addEventListener('DOMContentLoaded', function() {
            const saved = localStorage.getItem('adminTheme');
            if (saved === 'dark') {
                document.body.classList.add('dark');
                document.querySelector('#themeToggle i')?.classList.replace('fa-moon', 'fa-sun');
            }
        });

        // ================================================================
        // GLOBAL SEARCH (placeholder)
        // ================================================================
        document.getElementById('globalSearch')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                toast('Searching for: ' + this.value, 'info');
            }
        });

        // ================================================================
        // MODAL HELPERS (for open/close)
        // ================================================================
        function openModal(id) { document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }
        window.openModal = openModal;
        window.closeModal = closeModal;

        // ================================================================
        // ADD DARK MODE CSS VARIABLES (dynamic)
        // ================================================================
        const style = document.createElement('style');
        style.textContent = `
            body.dark {
                --sidebar-bg: #6e83dfff;
                --main-bg: #0f172a;
                --card-bg: #1e293b;
                --text-primary: #f1f5f9;
                --text-secondary: #cbd5e1;
                --border-light: #334155;
                --shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
            }
        `;
        document.head.appendChild(style);
    </script>

    <!-- Page-specific JavaScript -->
    <?= $extraJs ?? '' ?>
</body>
</html>