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
});
