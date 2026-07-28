class AjaxTable {
    constructor(root) {
        this.root = root;
        this.form = root.querySelector('[data-ajax-table-form]');
        this.target = root.querySelector('[data-ajax-table-target]');
        this.loading = root.querySelector('[data-ajax-table-loading]');
        this.error = root.querySelector('[data-ajax-table-error]');
        this.controller = null;
        this.debounceTimer = null;

        if (!this.form || !this.target) {
            return;
        }

        this.bind();
    }

    bind() {
        this.form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.fetchUrl(this.form.action + '?' + new URLSearchParams(new FormData(this.form)).toString(), true);
        });

        this.form.querySelectorAll('input, select').forEach((field) => {
            const eventName = field.tagName === 'SELECT' ? 'change' : 'input';
            field.addEventListener(eventName, () => {
                window.clearTimeout(this.debounceTimer);
                this.debounceTimer = window.setTimeout(() => {
                    this.fetchUrl(this.form.action + '?' + new URLSearchParams(new FormData(this.form)).toString(), true);
                }, 300);
            });
        });

        this.root.addEventListener('click', (event) => {
            const link = event.target.closest('[data-ajax-table-target] .pagination a, [data-ajax-table-target] nav a');
            if (!link) {
                return;
            }

            event.preventDefault();
            this.fetchUrl(link.href, true);
        });
    }

    async fetchUrl(url, pushState = false) {
        this.controller?.abort();
        this.controller = new AbortController();
        this.setLoading(true);
        this.setError('');

        try {
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                signal: this.controller.signal,
            });

            if (!response.ok) {
                throw new Error('The table could not be updated. Please try again.');
            }

            const payload = await response.json();
            this.target.innerHTML = payload.html || '';

            if (pushState) {
                window.history.pushState({}, '', payload.url || url);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.setError(error.message || 'The table could not be updated. Please try again.');
            }
        } finally {
            this.setLoading(false);
        }
    }

    setLoading(isLoading) {
        if (!this.loading) {
            return;
        }

        this.loading.hidden = !isLoading;
    }

    setError(message) {
        if (!this.error) {
            return;
        }

        this.error.textContent = message;
        this.error.hidden = !message;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ajax-table]').forEach((root) => new AjaxTable(root));
});
