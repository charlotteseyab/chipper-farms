/**
 * Responsive safety net for data-heavy authenticated pages.
 *
 * A number of legacy screens render a desktop table inside an overflow-hidden
 * card. Wrapping the table itself keeps the rounded card and makes the data
 * reachable on narrow screens. The observer also covers tables inserted by
 * Livewire/AJAX after the initial page load.
 */
const enhanceTable = (table) => {
    if (!(table instanceof HTMLTableElement) || table.dataset.responsiveReady === 'true') {
        return;
    }

    table.dataset.responsiveReady = 'true';

    const existingScroller = table.closest('[data-responsive-table], .overflow-x-auto, .overflow-auto');
    if (existingScroller) {
        existingScroller.setAttribute('data-responsive-table', '');
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.setAttribute('data-responsive-table', '');
    wrapper.setAttribute('role', 'region');
    wrapper.setAttribute('aria-label', table.getAttribute('aria-label') || 'Scrollable data table');
    wrapper.tabIndex = 0;

    const columnCount = table.tHead?.rows?.[0]?.cells?.length
        || table.rows?.[0]?.cells?.length
        || 0;

    if (columnCount >= 8) {
        table.classList.add('responsive-table--wide');
    } else if (columnCount >= 5) {
        table.classList.add('responsive-table--medium');
    }

    table.parentNode?.insertBefore(wrapper, table);
    wrapper.appendChild(table);
};

const enhanceResponsiveContent = (root = document) => {
    if (root instanceof HTMLTableElement) {
        enhanceTable(root);
    }

    root.querySelectorAll?.('main table').forEach(enhanceTable);
};

const bootResponsiveUi = () => {
    enhanceResponsiveContent();

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof Element) {
                    enhanceResponsiveContent(node);
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootResponsiveUi, { once: true });
} else {
    bootResponsiveUi();
}
