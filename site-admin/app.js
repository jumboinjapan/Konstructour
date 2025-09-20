// Site Admin bootstrap

document.addEventListener('DOMContentLoaded', () => {
  if (window.adminAuth && window.adminAuth.validateSession) {
    const ok = window.adminAuth.validateSession();
    if (!ok) return;
  }

  // Simple placeholder navigation (будет расширяться)
  document.querySelectorAll('a.glass-panel').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      alert('Раздел в разработке. Будет подключен к API согласно api_spec_constructour_2025.md');
    });
  });
});


