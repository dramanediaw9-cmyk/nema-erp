import './bootstrap';

(() => {
    if (typeof window === 'undefined' || window.__nemaLayoutBooted) {
        return;
    }

    window.__nemaLayoutBooted = true;

    const bootSidebar = () => {
        const shell = document.querySelector('[data-layout-shell]');
        const sidebar = document.getElementById('app-sidebar');
        const mobileToggle = document.querySelector('[data-sidebar-toggle]');
        const collapseToggles = Array.from(document.querySelectorAll('[data-sidebar-collapse-toggle]'));
        const focusToggle = document.querySelector('[data-focus-toggle]');
        const backdrop = document.querySelector('[data-sidebar-backdrop]');

        if (!shell || !sidebar || !mobileToggle || !backdrop) {
            return;
        }

        const mobileQuery = window.matchMedia('(max-width: 980px)');
        const sidebarStorageKey = 'nema.erp.sidebar.state';
        const focusStorageKey = 'nema.erp.layout.focus';
        const layoutMode = shell.dataset.layoutMode || 'normal';
        const focusAvailable = shell.dataset.focusAvailable === 'true';
        const storedSidebarState = (() => {
            try {
                const value = window.localStorage.getItem(sidebarStorageKey);
                return ['expanded', 'collapsed', 'hidden'].includes(value) ? value : null;
            } catch (error) {
                return null;
            }
        })();

        let sidebarState = storedSidebarState || (layoutMode === 'normal' ? 'expanded' : 'collapsed');
        let focusActive = shell.dataset.focusActive === 'true';

        try {
            const storedFocus = window.localStorage.getItem(focusStorageKey);
            if (focusAvailable && storedFocus !== null && layoutMode !== 'focus') {
                focusActive = storedFocus === 'true';
            }
        } catch (error) {
            focusActive = shell.dataset.focusActive === 'true';
        }

        if (focusActive && sidebarState === 'expanded') {
            sidebarState = 'collapsed';
        } else if (!focusActive && sidebarState === 'hidden') {
            sidebarState = 'collapsed';
        }

        const saveSidebarState = () => {
            try {
                window.localStorage.setItem(sidebarStorageKey, sidebarState);
            } catch (error) {
                // localStorage can be unavailable in private or restricted contexts.
            }
        };

        const saveFocusState = () => {
            try {
                window.localStorage.setItem(focusStorageKey, focusActive ? 'true' : 'false');
            } catch (error) {
                // localStorage can be unavailable in private or restricted contexts.
            }
        };

        const updateControls = () => {
            const isMobile = mobileQuery.matches;
            const visibleSidebarState = isMobile ? 'expanded' : sidebarState;

            shell.dataset.sidebarState = visibleSidebarState;
            shell.dataset.focusActive = focusActive && focusAvailable ? 'true' : 'false';

            collapseToggles.forEach((button) => {
                const opensHiddenSidebar = focusActive && sidebarState === 'hidden';
                button.setAttribute('aria-expanded', visibleSidebarState === 'expanded' ? 'true' : 'false');
                button.setAttribute(
                    'aria-label',
                    isMobile || opensHiddenSidebar
                        ? 'Ouvrir le menu lateral'
                        : visibleSidebarState === 'expanded'
                          ? 'Reduire le menu lateral'
                          : 'Ouvrir le menu lateral',
                );
                button.title =
                    isMobile || opensHiddenSidebar
                        ? 'Menu'
                        : visibleSidebarState === 'expanded'
                          ? 'Reduire le menu'
                          : 'Ouvrir le menu';
            });

            if (focusToggle) {
                focusToggle.dataset.focusActive = focusActive && focusAvailable ? 'true' : 'false';
                focusToggle.setAttribute('aria-pressed', focusActive && focusAvailable ? 'true' : 'false');
                focusToggle.setAttribute(
                    'aria-label',
                    focusActive && focusAvailable ? 'Desactiver le mode Travail' : 'Activer le mode Travail',
                );
                focusToggle.title = focusActive && focusAvailable ? 'Quitter le mode Travail' : 'Mode Travail';
            }
        };

        const setOpen = (open) => {
            if (!mobileQuery.matches) {
                shell.dataset.sidebarOpen = 'false';
                mobileToggle.setAttribute('aria-expanded', 'false');
                mobileToggle.setAttribute('aria-label', 'Ouvrir le menu principal');
                document.body.classList.remove('js-ready');
                document.body.classList.remove('sidebar-overlay-open');
                backdrop.hidden = true;
                updateControls();
                return;
            }

            document.body.classList.add('js-ready');
            document.body.classList.toggle('sidebar-overlay-open', open);
            shell.dataset.sidebarOpen = open ? 'true' : 'false';
            mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            mobileToggle.setAttribute('aria-label', open ? 'Fermer le menu principal' : 'Ouvrir le menu principal');
            backdrop.hidden = !open;
            updateControls();
        };

        const toggleSidebar = () => setOpen(shell.dataset.sidebarOpen !== 'true');
        const closeSidebar = () => setOpen(false);
        const cycleDesktopSidebar = () => {
            if (mobileQuery.matches) {
                toggleSidebar();
                return;
            }

            if (focusActive && sidebarState === 'collapsed') {
                sidebarState = 'hidden';
            } else if (sidebarState === 'hidden') {
                sidebarState = 'expanded';
            } else if (sidebarState === 'expanded') {
                sidebarState = 'collapsed';
            } else {
                sidebarState = 'expanded';
            }

            saveSidebarState();
            closeSidebar();
            updateControls();
        };

        mobileToggle.addEventListener('click', toggleSidebar);
        collapseToggles.forEach((button) => button.addEventListener('click', cycleDesktopSidebar));
        if (focusToggle) {
            focusToggle.addEventListener('click', () => {
                focusActive = !focusActive;
                if (focusActive && sidebarState === 'expanded') {
                    sidebarState = 'collapsed';
                    saveSidebarState();
                }

                saveFocusState();
                updateControls();
            });
        }
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

    const bootDashboardCollapsibles = () => {
        const items = Array.from(document.querySelectorAll('[data-dashboard-collapsible]'));

        if (items.length === 0) {
            return;
        }

        const mobileQuery = window.matchMedia('(max-width: 760px)');

        const setCollapsed = (item, collapsed) => {
            const button = item.querySelector('[data-dashboard-collapsible-toggle]');
            const body = item.querySelector('[data-dashboard-collapsible-body]');

            if (!button || !body) {
                return;
            }

            item.dataset.dashboardCollapsed = collapsed ? 'true' : 'false';
            button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            body.hidden = collapsed;
        };

        items.forEach((item) => {
            const button = item.querySelector('[data-dashboard-collapsible-toggle]');
            const body = item.querySelector('[data-dashboard-collapsible-body]');

            if (!button || !body) {
                return;
            }

            const defaultOpen = item.dataset.dashboardDefaultOpen === 'true';

            const sync = (reset = false) => {
                if (!mobileQuery.matches) {
                    setCollapsed(item, false);
                    return;
                }

                if (reset || item.dataset.dashboardTouched !== 'true') {
                    setCollapsed(item, !defaultOpen);
                    return;
                }

                setCollapsed(item, item.dataset.dashboardCollapsed === 'true');
            };

            button.addEventListener('click', () => {
                if (!mobileQuery.matches) {
                    return;
                }

                item.dataset.dashboardTouched = 'true';
                setCollapsed(item, item.dataset.dashboardCollapsed !== 'true');
            });

            sync(true);

            const handleViewportChange = () => {
                if (!mobileQuery.matches) {
                    item.dataset.dashboardTouched = 'false';
                    setCollapsed(item, false);
                    return;
                }

                sync(true);
            };

            if (typeof mobileQuery.addEventListener === 'function') {
                mobileQuery.addEventListener('change', handleViewportChange);
            } else if (typeof mobileQuery.addListener === 'function') {
                mobileQuery.addListener(handleViewportChange);
            }
        });
    };

    const boot = () => {
        bootSidebar();
        bootDashboardCollapsibles();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
