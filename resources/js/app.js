

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('searchList', () => ({
    query: '',
    shown: 0,
    total: 0,
    init() {
        this.filter();
        this.$watch('query', () => this.filter());
    },
    filter() {
        const needle = this.query.trim().toLowerCase();
        const shown = new Set();
        const total = new Set();

        this.$root.querySelectorAll('[data-search]').forEach((item) => {
            const haystack = item.dataset.search;
            const key = item.dataset.searchKey ?? haystack;
            const isMatch = needle === '' || haystack.includes(needle);

            item.style.display = isMatch ? '' : 'none';
            total.add(key);
            if (isMatch) {
                shown.add(key);
            }
        });

        this.shown = shown.size;
        this.total = total.size;
    },
}));

Alpine.start();

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
