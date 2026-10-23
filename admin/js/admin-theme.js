/* ============================================================
   KSR GURU JI - ADMIN MODERN EFFECTS & INTERACTIONS
   ============================================================ */
document.addEventListener("DOMContentLoaded", function () {
  // ------------------------------------------------------------
  // 1. Sidebar Toggle Mechanics for Mobile
  // ------------------------------------------------------------
  const sidebar =
    document.getElementById("sidebar") || document.querySelector(".sidebar");
  const toggleBtn = document.getElementById("sidebarToggle");

  // Check if we need to create a floating mobile toggle button
  function setupMobileToggle() {
    if (!sidebar) return;

    let existingTrigger = document.querySelector(".mobile-menu-trigger");
    if (window.innerWidth <= 768) {
      if (!existingTrigger) {
        const trigger = document.createElement("button");
        trigger.className = "mobile-menu-trigger";
        trigger.innerHTML = '<i class="fas fa-bars"></i>';
        trigger.setAttribute("aria-label", "Toggle navigation menu");

        trigger.addEventListener("click", function (e) {
          e.stopPropagation();
          sidebar.classList.toggle("mobile-open");
          const isOpen = sidebar.classList.contains("mobile-open");
          trigger.innerHTML = isOpen
            ? '<i class="fas fa-times"></i>'
            : '<i class="fas fa-bars"></i>';
          trigger.style.background = isOpen ? "var(--danger, #df3838)" : "";
        });

        document.body.appendChild(trigger);
      }
    } else {
      if (existingTrigger) {
        existingTrigger.remove();
      }
    }
  }

  // Core toggle hook
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      sidebar.classList.toggle("mobile-open");
    });
  }

  // Close sidebar when clicking outside on mobile
  document.addEventListener("click", function (e) {
    if (sidebar && sidebar.classList.contains("mobile-open")) {
      if (
        !sidebar.contains(e.target) &&
        !e.target.closest(".mobile-menu-trigger") &&
        !e.target.closest("#sidebarToggle")
      ) {
        sidebar.classList.remove("mobile-open");
        const trigger = document.querySelector(".mobile-menu-trigger");
        if (trigger) {
          trigger.innerHTML = '<i class="fas fa-bars"></i>';
          trigger.style.background = "";
        }
      }
    }
  });

  // Run toggle setup
  setupMobileToggle();
  window.addEventListener("resize", setupMobileToggle);

  // ------------------------------------------------------------
  // 2. Responsive Table Wrappers
  // ------------------------------------------------------------
  // Dynamically wrap tables with overflow container if not already wrapped
  document.querySelectorAll("table").forEach((table) => {
    if (
      !table.parentElement.classList.contains("table-container") &&
      !table.closest(".permissions-table")
    ) {
      const wrapper = document.createElement("div");
      wrapper.className = "table-container";
      table.parentNode.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    }
  });

  // ------------------------------------------------------------
  // 3. Theme Toggle Setup
  // ------------------------------------------------------------
  const themeBtn = document.querySelector(".theme-toggle");
  const savedTheme = localStorage.getItem("admin-theme");

  // Apply layout theme
  if (savedTheme === "dark") {
    document.body.classList.add("dark");
    if (themeBtn) {
      themeBtn.innerHTML = '<i class="fas fa-sun"></i>';
    }
  }

  if (themeBtn) {
    themeBtn.addEventListener("click", function () {
      document.body.classList.toggle("dark");
      const isDark = document.body.classList.contains("dark");
      localStorage.setItem("admin-theme", isDark ? "dark" : "light");
      themeBtn.innerHTML = isDark
        ? '<i class="fas fa-sun"></i>'
        : '<i class="fas fa-moon"></i>';
    });
  }

  // ------------------------------------------------------------
  // 4. Modal Soft Transitions
  // ------------------------------------------------------------
  document.querySelectorAll(".modal").forEach((modal) => {
    // Close modal when clicking on background
    modal.addEventListener("click", function (e) {
      if (e.target === modal) {
        modal.classList.remove("active");
        if (typeof modal.style.display !== "undefined") {
          modal.style.display = "none";
        }
      }
    });

    // Hook internal cancellation triggers
    modal.querySelectorAll(".close-modal").forEach((closeBtn) => {
      closeBtn.addEventListener("click", function () {
        modal.classList.remove("active");
        if (typeof modal.style.display !== "undefined") {
          modal.style.display = "none";
        }
      });
    });
  });
});
