/**
 * Core Application Client JavaScript
 * AI-Based PDF Notes Summarizer and Question Generator
 * 
 * Supports:
 * - Dynamic Theme Switching (Light Mode <-> Dark Mode) with localStorage persistence
 * - Desktop & Mobile Theme Button Synchronization
 * - Study Tools Dropdown Toggle, Click-Outside & Keyboard Accessibility
 * - User Profile Dropdown Toggle & Click-Outside Dismissal
 * - Mobile Hamburger Navigation Drawer & Backdrop Toggle
 * - Desktop & Mobile Search Input Redirection to my-pdfs.php?search=
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

  // Desktop theme toggle controls
  const icon = document.getElementById('themeToggleIcon');
  const text = document.getElementById('themeToggleText');
  const btn = document.getElementById('themeToggleBtn');

  // Mobile theme toggle controls
  const mobIcon = document.getElementById('mobileThemeToggleIcon');
  const mobText = document.getElementById('mobileThemeToggleText');
  const mobBtn = document.getElementById('mobileThemeToggleBtn');

  const isDark = (theme === 'dark');
  const titleText = isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode';

  if (icon) {
    icon.textContent = (theme === 'dark') ? '🌙' : '☀️';
  }
  if (text) {
    text.textContent = (theme === 'dark') ? 'Dark' : 'Light';
  }
  if (btn) {
    btn.setAttribute('data-theme', theme);
    btn.setAttribute('title', titleText);
  }

  if (mobIcon) {
    mobIcon.textContent = (theme === 'dark') ? '🌙' : '☀️';
  }
  if (mobText) {
    mobText.textContent = (theme === 'dark') ? 'Dark' : 'Light';
  }
  if (mobBtn) {
    mobBtn.setAttribute('data-theme', theme);
    mobBtn.setAttribute('title', titleText);
  }

  // Dispatch custom event for any listening components
  window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme } }));
}

function initThemeSwitcher() {
  const currentTheme = document.documentElement.getAttribute('data-theme') || getStoredTheme();
  applyTheme(currentTheme);

  const toggleTheme = (e) => {
    if (e) e.preventDefault();
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = (current === 'dark') ? 'light' : 'dark';
    applyTheme(next);
  };

  const btn = document.getElementById('themeToggleBtn');
  if (btn) {
    btn.addEventListener('click', toggleTheme);
  }

  const mobBtn = document.getElementById('mobileThemeToggleBtn');
  if (mobBtn) {
    mobBtn.addEventListener('click', toggleTheme);
  }

  // Listen to OS theme changes if user hasn't set manual preference
  if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
      if (!localStorage.getItem('theme')) {
        applyTheme(e.matches ? 'dark' : 'light');
      }
    });
  }
}

// Dropdowns & Mobile Navigation Initialization
function initNavigation() {
  // 1. Study Tools Dropdown
  const studyToolsBtn = document.getElementById('studyToolsBtn');
  const studyToolsDropdown = document.getElementById('studyToolsDropdown');
  const studyToolsMenu = document.getElementById('studyToolsMenu');

  // 2. User Profile Dropdown
  const userProfileBtn = document.getElementById('userProfileBtn');
  const userProfileDropdown = document.getElementById('userProfileDropdown');

  // Toggle Study Tools
  if (studyToolsBtn && studyToolsDropdown) {
    studyToolsBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = studyToolsDropdown.classList.contains('open');
      // Close profile dropdown if open
      if (userProfileDropdown) userProfileDropdown.classList.remove('open');
      if (userProfileBtn) userProfileBtn.setAttribute('aria-expanded', 'false');

      if (isOpen) {
        studyToolsDropdown.classList.remove('open');
        studyToolsBtn.setAttribute('aria-expanded', 'false');
      } else {
        studyToolsDropdown.classList.add('open');
        studyToolsBtn.setAttribute('aria-expanded', 'true');
      }
    });

    // Keyboard navigation within dropdown
    studyToolsBtn.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
        if (!studyToolsDropdown.classList.contains('open')) {
          e.preventDefault();
          studyToolsDropdown.classList.add('open');
          studyToolsBtn.setAttribute('aria-expanded', 'true');
          const firstLink = studyToolsMenu ? studyToolsMenu.querySelector('a') : null;
          if (firstLink) firstLink.focus();
        }
      }
    });

    if (studyToolsMenu) {
      studyToolsMenu.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          studyToolsDropdown.classList.remove('open');
          studyToolsBtn.setAttribute('aria-expanded', 'false');
          studyToolsBtn.focus();
        }
      });
    }
  }

  // Toggle User Profile
  if (userProfileBtn && userProfileDropdown) {
    userProfileBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = userProfileDropdown.classList.contains('open');
      // Close study tools dropdown if open
      if (studyToolsDropdown) studyToolsDropdown.classList.remove('open');
      if (studyToolsBtn) studyToolsBtn.setAttribute('aria-expanded', 'false');

      if (isOpen) {
        userProfileDropdown.classList.remove('open');
        userProfileBtn.setAttribute('aria-expanded', 'false');
      } else {
        userProfileDropdown.classList.add('open');
        userProfileBtn.setAttribute('aria-expanded', 'true');
      }
    });

    userProfileBtn.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        userProfileDropdown.classList.remove('open');
        userProfileBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // Global click outside to dismiss open dropdowns
  document.addEventListener('click', (e) => {
    if (studyToolsDropdown && !studyToolsDropdown.contains(e.target)) {
      studyToolsDropdown.classList.remove('open');
      if (studyToolsBtn) studyToolsBtn.setAttribute('aria-expanded', 'false');
    }
    if (userProfileDropdown && !userProfileDropdown.contains(e.target)) {
      userProfileDropdown.classList.remove('open');
      if (userProfileBtn) userProfileBtn.setAttribute('aria-expanded', 'false');
    }
  });

  // Global Escape key handler
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (studyToolsDropdown && studyToolsDropdown.classList.contains('open')) {
        studyToolsDropdown.classList.remove('open');
        if (studyToolsBtn) studyToolsBtn.setAttribute('aria-expanded', 'false');
      }
      if (userProfileDropdown && userProfileDropdown.classList.contains('open')) {
        userProfileDropdown.classList.remove('open');
        if (userProfileBtn) userProfileBtn.setAttribute('aria-expanded', 'false');
      }
      closeMobileDrawer();
    }
  });

  // 3. Mobile Navigation Drawer & Hamburger
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const mobileNavDrawer = document.getElementById('mobileNavDrawer');
  const mobileNavBackdrop = document.getElementById('mobileNavBackdrop');

  function openMobileDrawer() {
    if (!mobileNavDrawer || !mobileMenuBtn) return;
    mobileNavDrawer.classList.add('open');
    if (mobileNavBackdrop) mobileNavBackdrop.classList.add('open');
    mobileMenuBtn.classList.add('open');
    mobileMenuBtn.setAttribute('aria-expanded', 'true');
    mobileNavDrawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeMobileDrawer() {
    if (!mobileNavDrawer || !mobileMenuBtn) return;
    mobileNavDrawer.classList.remove('open');
    if (mobileNavBackdrop) mobileNavBackdrop.classList.remove('open');
    mobileMenuBtn.classList.remove('open');
    mobileMenuBtn.setAttribute('aria-expanded', 'false');
    mobileNavDrawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  if (mobileMenuBtn) {
    mobileMenuBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = mobileNavDrawer && mobileNavDrawer.classList.contains('open');
      if (isOpen) {
        closeMobileDrawer();
      } else {
        openMobileDrawer();
      }
    });
  }

  if (mobileNavBackdrop) {
    mobileNavBackdrop.addEventListener('click', closeMobileDrawer);
  }

  // Close mobile drawer when clicking navigation links inside it
  if (mobileNavDrawer) {
    const navLinks = mobileNavDrawer.querySelectorAll('a');
    navLinks.forEach((link) => {
      link.addEventListener('click', closeMobileDrawer);
    });
  }

  // 4. Search Redirection (Desktop & Mobile)
  const bindSearch = (inputId) => {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        const query = input.value.trim();
        if (query.length > 0) {
          window.location.href = 'my-pdfs.php?search=' + encodeURIComponent(query);
        }
      }
    });
  };

  bindSearch('headerSearchInput');
  bindSearch('mobileSearchInput');

  // 5. Auto-dismiss Flash Alerts after 5 seconds
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
}

document.addEventListener('DOMContentLoaded', () => {
  initThemeSwitcher();
  initNavigation();
});
