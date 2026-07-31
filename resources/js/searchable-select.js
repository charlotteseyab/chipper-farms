class SearchableSelect {
    constructor(select) {
        this.select = select;
        this.isOpen = false;

        this.root = document.createElement('div');
        this.root.className = 'searchable-select';

        this.trigger = document.createElement('button');
        this.trigger.type = 'button';
        this.trigger.className = 'searchable-select__trigger';
        this.trigger.setAttribute('aria-haspopup', 'listbox');
        this.trigger.setAttribute('aria-expanded', 'false');

        this.menu = document.createElement('div');
        this.menu.className = 'searchable-select__menu';

        this.search = document.createElement('input');
        this.search.type = 'search';
        this.search.className = 'searchable-select__search';
        this.search.autocomplete = 'off';
        this.search.setAttribute('aria-label', 'Search options');
        this.search.setAttribute('role', 'searchbox');

        this.optionsContainer = document.createElement('div');
        this.optionsContainer.className = 'searchable-select__options';
        this.optionsContainer.setAttribute('role', 'listbox');

        this.menu.append(this.search, this.optionsContainer);
        this.root.append(this.trigger, this.menu);
        select.insertAdjacentElement('afterend', this.root);
        select.classList.add('searchable-select__native');

        this.trigger.addEventListener('click', () => this.isOpen ? this.close() : this.open());
        this.trigger.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                this.open();
            }
        });
        this.search.addEventListener('input', () => this.render(this.search.value));
        this.search.addEventListener('keydown', (event) => this.onSearchKeydown(event));
        this.select.addEventListener('change', () => this.sync());

        this.observer = new MutationObserver(() => this.refresh());
        this.observer.observe(select, { childList: true, subtree: true, attributes: true });
        this.refresh();
    }

    get options() {
        return Array.from(this.select.options).filter((option) => !option.hidden);
    }

    refresh() {
        this.trigger.disabled = this.select.disabled;
        this.root.classList.toggle('is-disabled', this.select.disabled);
        this.search.placeholder = this.select.dataset.searchPlaceholder || 'Search options...';
        this.sync();
        if (this.isOpen) this.render(this.search.value);
    }

    sync() {
        const selected = this.select.options[this.select.selectedIndex];
        this.trigger.textContent = selected?.textContent.trim() || 'Select an option';
        this.trigger.classList.toggle('is-placeholder', !selected?.value);
    }

    open() {
        if (this.select.disabled || this.isOpen) return;
        this.isOpen = true;
        this.root.classList.add('is-open');
        this.trigger.setAttribute('aria-expanded', 'true');
        this.search.value = '';
        this.render();
        requestAnimationFrame(() => this.search.focus());
    }

    close({ focusTrigger = false } = {}) {
        if (!this.isOpen) return;
        this.isOpen = false;
        this.root.classList.remove('is-open');
        this.trigger.setAttribute('aria-expanded', 'false');
        this.search.value = '';
        if (focusTrigger) this.trigger.focus();
    }

    render(query = '') {
        const term = query.trim().toLocaleLowerCase();
        const matches = this.options.filter((option) =>
            option.textContent.trim().toLocaleLowerCase().includes(term)
        );

        this.optionsContainer.replaceChildren();
        matches.forEach((option) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'searchable-select__option';
            item.textContent = option.textContent.trim();
            item.disabled = option.disabled;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
            if (option.selected) item.classList.add('is-selected');
            item.addEventListener('click', () => this.choose(option));
            item.addEventListener('keydown', (event) => this.onOptionKeydown(event, item));
            this.optionsContainer.appendChild(item);
        });

        if (!matches.length) {
            const empty = document.createElement('div');
            empty.className = 'searchable-select__empty';
            empty.textContent = 'No matching options';
            this.optionsContainer.appendChild(empty);
        }
    }

    choose(option) {
        if (option.disabled) return;
        this.select.value = option.value;
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
        this.close({ focusTrigger: true });
    }

    onSearchKeydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            this.close({ focusTrigger: true });
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            this.enabledItems()[0]?.focus();
        } else if (event.key === 'Enter') {
            const items = this.enabledItems();
            if (items.length === 1) {
                event.preventDefault();
                items[0].click();
            }
        }
    }

    onOptionKeydown(event, item) {
        const items = this.enabledItems();
        const index = items.indexOf(item);
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            (items[index + 1] || items[0])?.focus();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            (items[index - 1] || this.search)?.focus();
        } else if (event.key === 'Escape') {
            event.preventDefault();
            this.close({ focusTrigger: true });
        }
    }

    enabledItems() {
        return Array.from(this.optionsContainer.querySelectorAll('.searchable-select__option:not(:disabled)'));
    }
}

const instances = [];
const searchableSelector = 'select:not([multiple]):not([data-native-select]):not([data-searchable-ready])';

function initialiseSearchableSelects(root = document) {
    const selects = [];

    if (root instanceof HTMLSelectElement && root.matches(searchableSelector)) {
        selects.push(root);
    }

    root.querySelectorAll?.(searchableSelector).forEach((select) => selects.push(select));

    selects.forEach((select) => {
        select.dataset.searchableReady = 'true';
        instances.push(new SearchableSelect(select));
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initialiseSearchableSelects();

    const addedSelectObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof Element) initialiseSearchableSelects(node);
            });
        });
    });

    addedSelectObserver.observe(document.body, { childList: true, subtree: true });
});
document.addEventListener('click', (event) => {
    instances.forEach((instance) => {
        if (!instance.root.contains(event.target)) instance.close();
    });
});
document.addEventListener('change', () => queueMicrotask(() => instances.forEach((instance) => instance.sync())));

window.initialiseSearchableSelects = initialiseSearchableSelects;
