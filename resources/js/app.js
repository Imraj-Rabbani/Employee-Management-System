/*
 * Sidebar: drag its right edge to resize, or close it completely (desktop only; phones keep Flux's slide-in menu).
 *
 * State lives on <html> (survives wire:navigate page changes) and in localStorage (survives reloads):
 *   --sidebar-w        CSS variable with the width, used in app.css
 *   .sidebar-closed    hides the sidebar
 * partials/head.blade.php applies the saved state before the page is painted, so it never flickers.
 */
const MIN = 200;
const MAX = 480;
const DEFAULT = 256;
const CLOSE_BELOW = 140; // dragging narrower than this closes the sidebar

const root = document.documentElement;

function save(key, value) {
    try {
        localStorage.setItem(key, value);
    } catch (e) {
        // Private window / blocked storage: the sidebar still works, it just isn't remembered.
    }
}

function setWidth(px) {
    const width = Math.min(MAX, Math.max(MIN, Math.round(px)));
    root.style.setProperty('--sidebar-w', width + 'px');
    save('ems.sidebar.width', width);
}

function setClosed(closed) {
    root.classList.toggle('sidebar-closed', closed);
    save('ems.sidebar.closed', closed ? '1' : '0');
}

window.emsSidebar = {
    toggle() {
        setClosed(!root.classList.contains('sidebar-closed'));
    },

    reset() {
        setWidth(DEFAULT);
        setClosed(false);
    },

    // onpointerdown of the drag handle (layouts/app/sidebar.blade.php).
    drag(event) {
        event.preventDefault();
        const handle = event.currentTarget;
        handle.setPointerCapture(event.pointerId);
        root.classList.add('sidebar-resizing');

        const move = (e) => {
            if (e.clientX < CLOSE_BELOW) {
                root.classList.add('sidebar-closed');
            } else {
                root.classList.remove('sidebar-closed');
                setWidth(e.clientX);
            }
        };

        const stop = () => {
            root.classList.remove('sidebar-resizing');
            setClosed(root.classList.contains('sidebar-closed'));
            handle.removeEventListener('pointermove', move);
            handle.removeEventListener('pointerup', stop);
            handle.removeEventListener('pointercancel', stop);
        };

        handle.addEventListener('pointermove', move);
        handle.addEventListener('pointerup', stop);
        handle.addEventListener('pointercancel', stop);
    },

    // Arrow keys on the focused handle, for keyboard users.
    key(event) {
        const current = parseInt(getComputedStyle(root).getPropertyValue('--sidebar-w')) || DEFAULT;
        if (event.key === 'ArrowLeft') setWidth(current - 16);
        else if (event.key === 'ArrowRight') setWidth(current + 16);
        else return;
        event.preventDefault();
    },
};

/*
 * The sidebar is kept between pages (@persist in layouts/app/sidebar.blade.php), so after each page change we
 * highlight the current item ourselves. data-nav-paths = the URL paths the item stands for; a path ending in "/"
 * also covers everything below it (e.g. "/hrm/employees/" = every employee's profile).
 */
function highlightSidebar() {
    const here = window.location.pathname.replace(/\/+$/, '') || '/';

    document.querySelectorAll('[data-flux-sidebar] [data-nav-paths]').forEach((el) => {
        let paths = [];
        try {
            paths = JSON.parse(el.dataset.navPaths);
        } catch (e) {}

        const current = paths.some((p) => (p.endsWith('/') && p !== '/' ? here.startsWith(p) : here === (p.replace(/\/+$/, '') || '/')));
        el.toggleAttribute('data-current', current);
        current ? el.setAttribute('aria-current', 'page') : el.removeAttribute('aria-current');
    });
}

document.addEventListener('livewire:navigated', () => {
    highlightSidebar();

    // Phones: the menu slides over the page, so close it once the new page is there.
    const sidebar = document.querySelector('ui-sidebar[data-flux-sidebar-on-mobile]:not([data-flux-sidebar-collapsed-mobile])');
    const close = sidebar?.querySelector('ui-sidebar-toggle');
    if (close) (close.querySelector('button, ui-button') || close).click();
});
