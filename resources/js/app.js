// Script para el botón de cambio de tema claro/oscuro
window.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('theme-toggle');
    if (!themeToggle) return;
    themeToggle.addEventListener('click', () => {
        const html = document.documentElement;
        const isDark = html.classList.contains('dark');
        if (isDark) {
            html.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        } else {
            html.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        }
        // Forzar actualización de iconos
        document.getElementById('icon-sun')?.classList.toggle('hidden', html.classList.contains('dark'));
        document.getElementById('icon-moon')?.classList.toggle('hidden', !html.classList.contains('dark'));
    });
    // Al cargar, aplicar preferencia guardada
    const saved = localStorage.getItem('theme');
    if (saved === 'dark') {
        document.documentElement.classList.add('dark');
    } else if (saved === 'light') {
        document.documentElement.classList.remove('dark');
    }
    // Sincronizar iconos
    document.getElementById('icon-sun')?.classList.toggle('hidden', document.documentElement.classList.contains('dark'));
    document.getElementById('icon-moon')?.classList.toggle('hidden', !document.documentElement.classList.contains('dark'));
});

// Establecer tema oscuro por defecto en FluxUI
document.addEventListener("DOMContentLoaded", () => {
    if (window.Flux) {
        window.Flux.appearance = "dark";
    }
});
