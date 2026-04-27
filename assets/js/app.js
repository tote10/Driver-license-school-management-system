// Core Javascript
document.addEventListener("DOMContentLoaded", () => {
  // Mobile sidebar toggle
  const mobileToggle = document.getElementById("mobile-sidebar-toggle");
  const sidebar = document.getElementById("app-sidebar");

  if (mobileToggle && sidebar) {
    mobileToggle.addEventListener("click", () => {
      sidebar.classList.toggle("active");
    });
  }

  // Global utility to close sidebar when clicking outside on mobile
  document.addEventListener("click", (event) => {
    if (
      window.innerWidth <= 992 &&
      sidebar &&
      sidebar.classList.contains("active")
    ) {
      if (
        !sidebar.contains(event.target) &&
        !mobileToggle.contains(event.target)
      ) {
        sidebar.classList.remove("active");
      }
    }
  });

  // Demo only form submission handlers
  const loginForm = document.getElementById("demo-login-form");
  if (loginForm) {
    loginForm.addEventListener("submit", (e) => {
      e.preventDefault();
      // Since role selection is removed, we mock logging into Manager as default prototype
      window.location.href = "manager_dashboard.html";
    });
  }
  // Toast Notification System for prototype buttons
  const buttons = document.querySelectorAll(
    '.btn:not([type="submit"]):not([disabled]):not(.text-danger), .sidebar-item:not(.active)',
  );

  if (buttons.length > 0) {
    const toastContainer = document.createElement("div");
    toastContainer.className = "toast-container";
    document.body.appendChild(toastContainer);

    buttons.forEach((btn) => {
      btn.addEventListener("click", (e) => {
        // Only prevent default if it's a dummy link or a standard button
        if (
          (btn.tagName === "A" && btn.getAttribute("href") === "#") ||
          btn.tagName === "BUTTON"
        ) {
          e.preventDefault();

          const toast = document.createElement("div");
          toast.className = "toast show";
          toast.innerText = `${btn.innerText.trim()} initiated (Prototype Mode)`;

          toastContainer.appendChild(toast);

          setTimeout(() => {
            toast.style.opacity = "0";
            toast.style.transform = "translateY(10px)";
            setTimeout(() => toast.remove(), 300);
          }, 2500);
        }
      });
    });
  }
});
