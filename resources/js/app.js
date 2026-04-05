import './bootstrap';

(() => {
    if (typeof window === 'undefined' || window.__nemaLayoutBooted) {
        return;
    }

    window.__nemaLayoutBooted = true;

    const boot = () => {
        const shell = document.querySelector('[data-layout-shell]');
        const sidebar = document.getElementById('app-sidebar');
        const toggle = document.querySelector('[data-sidebar-toggle]');
        const backdrop = document.querySelector('[data-sidebar-backdrop]');

        if (!shell || !sidebar || !toggle || !backdrop) {
            return;
        }

        const mobileQuery = window.matchMedia('(max-width: 980px)');
        const setOpen = (open) => {
            if (!mobileQuery.matches) {
                shell.dataset.sidebarOpen = 'false';
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'Ouvrir le menu principal');
                document.body.classList.remove('js-ready');
                backdrop.hidden = true;
                return;
            }

            document.body.classList.add('js-ready');
            shell.dataset.sidebarOpen = open ? 'true' : 'false';
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Fermer le menu principal' : 'Ouvrir le menu principal');
            backdrop.hidden = !open;
        };

        const toggleSidebar = () => setOpen(shell.dataset.sidebarOpen !== 'true');
        const closeSidebar = () => setOpen(false);

        toggle.addEventListener('click', toggleSidebar);
        backdrop.addEventListener('click', closeSidebar);
        sidebar.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                if (mobileQuery.matches) {
                    closeSidebar();
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeSidebar();
            }
        });

        const handleViewportChange = () => {
            if (mobileQuery.matches) {
                document.body.classList.add('js-ready');
            } else {
                document.body.classList.remove('js-ready');
            }

            closeSidebar();
        };

        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', handleViewportChange);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(handleViewportChange);
        }

        handleViewportChange();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();