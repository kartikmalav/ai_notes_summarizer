/**
 * Core Application Client JavaScript
 * AI-Based PDF Notes Summarizer and Question Generator
 * 
 * Supports:
 * - Dynamic Theme Switching (Light Mode <-> Dark Mode)
 * - LocalStorage Theme Persistence & Anti-flash Sync
 * - Header Search Redirection
 * - Mobile Sidebar Navigation Toggle
 * - Flash Alert Auto-dismissal
 */

// Theme Management Functions
function getStoredTheme() {
  const saved = localStorage.getItem('theme');
  if (saved === 'dark' || saved === 'light') {
    return saved;
  }
  return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  localStorage.setItem('theme', theme);

  const icon = document.getElementById('themeToggleIcon');
  const text = document.getElementById('themeToggleText');
  const btn = document.getElementById('themeToggleBtn');

  if (icon) {
    icon.textContent = (theme === 'dark') ? '🌙' : '☀️';
  }
  if (text) {
    text.textContent = (theme === 'dark') ? 'Dark' : 'Light';
  }
  if (btn) {
    btn.setAttribute('data-theme', theme);
    btn.setAttribute('title', (theme === 'dark') ? 'Switch to Light Mode' : 'Switch to Dark Mode');
  }

  // Dispatch custom event for widgets/charts
  window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme } }));
}

function initThemeSwitcher() {
  const btn = document.getElementById('themeToggleBtn');
  const currentTheme = document.documentElement.getAttribute('data-theme') || getStoredTheme();
  
  // Ensure DOM is in sync
  applyTheme(currentTheme);

  if (btn) {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const current = document.documentElement.getAttribute('data-theme') || 'light';
      const next = (current === 'dark') ? 'light' : 'dark';
      applyTheme(next);
    });
  }

  // Listen to OS theme changes if user hasn't manually set a preference
  if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
      if (!localStorage.getItem('theme')) {
        applyTheme(e.matches ? 'dark' : 'light');
      }
    });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  // Initialize Theme Switcher
  initThemeSwitcher();

  // Mobile Sidebar Toggle Handler
  const toggleBtn = document.getElementById('toggleSidebarBtn');
  const sidebar = document.getElementById('sidebar');

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('show');
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
      if (window.innerWidth <= 992) {
        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target) && sidebar.classList.contains('show')) {
          sidebar.classList.remove('show');
        }
      }
    });
  }

  // Header Search Input (Search Documents)
  const searchInput = document.getElementById('headerSearchInput');
  if (searchInput) {
    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        const query = searchInput.value.trim();
        if (query.length > 0) {
          window.location.href = 'my-pdfs.php?search=' + encodeURIComponent(query);
        }
      }
    });
  }

  // Auto-dismiss Flash Alerts after 5 seconds
  const flashAlert = document.getElementById('flashAlert');
  if (flashAlert) {
    setTimeout(() => {
      flashAlert.style.opacity = '0';
      flashAlert.style.transition = 'opacity 0.5s ease';
      setTimeout(() => {
        if (flashAlert.parentNode) {
          flashAlert.remove();
        }
      }, 500);
    }, 5000);
  }
});
