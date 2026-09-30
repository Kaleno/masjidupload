import { forgetDialogs, hasOpenDialogs, openDialogLayer } from './dialog-history';
import * as Turbo from '@hotwired/turbo';
import Alpine from 'alpinejs';

Turbo.config.drive.progressBarDelay = 150;

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

document.addEventListener('turbo:click', ({ target }) => {
    if (hasOpenDialogs()) {
        target.setAttribute('data-turbo-action', 'replace');
    }
});

document.addEventListener('turbo:visit', () => {
    forgetDialogs();

    if (Alpine.store('confirmSheet').open) {
        Alpine.store('confirmSheet').answer(false);
    }
});

const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function visibleFocusables(container) {
    return [...container.querySelectorAll(focusableSelector)].filter((element) => element.offsetParent !== null);
}

Alpine.directive('dialog', (el, { expression }, { effect, evaluateLater, cleanup }) => {
    const readIsOpen = evaluateLater(expression);
    let isShown = false;
    let layer = null;
    let returnFocusTo = null;

    const trapTab = (event) => {
        if (event.key !== 'Tab') {
            return;
        }

        const focusables = visibleFocusables(el);

        if (focusables.length === 0) {
            return;
        }

        const first = focusables[0];
        const last = focusables[focusables.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (! event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    const open = () => {
        returnFocusTo = document.activeElement;
        layer = openDialogLayer(() => {
            layer = null;
            el.dispatchEvent(new CustomEvent('dialog-back'));
        });
        el.addEventListener('keydown', trapTab);

        setTimeout(() => {
            const target = el.querySelector('[data-dialog-focus]') ?? visibleFocusables(el)[0];
            target?.focus({ preventScroll: true });
        }, 60);
    };

    const close = () => {
        el.removeEventListener('keydown', trapTab);

        layer?.close();
        layer = null;

        if (returnFocusTo && document.contains(returnFocusTo)) {
            returnFocusTo.focus({ preventScroll: true });
        }

        returnFocusTo = null;
    };

    effect(() => readIsOpen((isOpen) => {
        if (isOpen && ! isShown) {
            isShown = true;
            open();
        } else if (! isOpen && isShown) {
            isShown = false;
            close();
        }
    }));

    cleanup(() => {
        layer?.forget();
    });
});

Alpine.store('confirmSheet', {
    open: false,
    message: '',
    label: 'Ya, lanjutkan',
    danger: false,
    resolve: null,

    ask(message, { label, danger }) {
        this.resolve?.(false);

        return new Promise((resolve) => {
            this.message = message;
            this.label = label || 'Ya, lanjutkan';
            this.danger = danger;
            this.resolve = resolve;
            this.open = true;
        });
    },

    answer(isConfirmed) {
        const resolve = this.resolve;

        this.resolve = null;
        this.open = false;
        resolve?.(isConfirmed);
    },
});

Turbo.config.forms.confirm = (message, form, submitter) => {
    if (! document.querySelector('[data-confirm-sheet]')) {
        return Promise.resolve(window.confirm(message));
    }

    const source = submitter?.dataset.confirmLabel ? submitter : form;

    return Alpine.store('confirmSheet').ask(message, {
        label: source.dataset.confirmLabel,
        danger: source.dataset.confirmTone === 'danger',
    });
};

let deferredInstallPrompt = null;

Alpine.store('install', {
    canPrompt: false,
    installed: false,

    async prompt() {
        if (! deferredInstallPrompt) {
            return false;
        }

        deferredInstallPrompt.prompt();
        const { outcome } = await deferredInstallPrompt.userChoice;

        deferredInstallPrompt = null;
        this.canPrompt = false;

        return outcome === 'accepted';
    },
});

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    Alpine.store('install').canPrompt = true;
});

window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    Alpine.store('install').canPrompt = false;
    Alpine.store('install').installed = true;
});

const installCardDismissedKey = 'install-card-dismissed';

Alpine.data('installCard', () => ({
    visible: false,
    isIos: false,

    init() {
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

        if (isStandalone || localStorage.getItem(installCardDismissedKey)) {
            return;
        }

        this.isIos = /iphone|ipad|ipod/i.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

        const refresh = () => {
            this.visible = ! this.$store.install.installed && (this.isIos || this.$store.install.canPrompt);
        };

        refresh();
        this.$watch('$store.install.canPrompt', refresh);
        this.$watch('$store.install.installed', refresh);
    },

    async install() {
        if (await this.$store.install.prompt()) {
            this.visible = false;
        }
    },

    dismiss() {
        localStorage.setItem(installCardDismissedKey, '1');
        this.visible = false;
    },
}));

function setNavState(element, isActive) {
    const classList = (name) => (element.dataset[name] ?? '').split(' ').filter(Boolean);

    element.classList.remove(...classList(isActive ? 'navIdle' : 'navActive'));
    element.classList.add(...classList(isActive ? 'navActive' : 'navIdle'));

    if (element.tagName === 'A') {
        element.toggleAttribute('aria-current', isActive);

        if (isActive) {
            element.setAttribute('aria-current', 'page');
        }
    }
}

document.addEventListener('turbo:click', ({ target }) => {
    const key = target.closest('[data-nav-key]')?.dataset.navKey;

    if (! key) {
        return;
    }

    const isTabKey = [...document.querySelectorAll('[data-nav-tab]')].some((tab) => tab.dataset.navKey === key);

    document.querySelectorAll('[data-nav-key]').forEach((link) => setNavState(link, link.dataset.navKey === key));
    document.querySelectorAll('[data-nav-more]').forEach((button) => setNavState(button, ! isTabKey));
});

const textButtonPattern = /(^|\s)btn(-|\s|$)/;

document.addEventListener('turbo:submit-start', ({ target: form, detail }) => {
    const submitter = detail.formSubmission.submitter ?? form.querySelector('[type="submit"], button:not([type])');

    form.setAttribute('aria-busy', 'true');

    if (! submitter) {
        return;
    }

    submitter.classList.add('is-submitting');

    if (submitter.tagName !== 'BUTTON' || ! textButtonPattern.test(submitter.className)) {
        return;
    }

    const isGet = (detail.formSubmission.method ?? form.method ?? '').toString().toLowerCase() === 'get';
    const label = submitter.dataset.submittingLabel ?? (isGet ? 'Memuat…' : 'Menyimpan…');

    submitter.dataset.idleHtml = submitter.innerHTML;
    submitter.style.minWidth = `${submitter.offsetWidth}px`;
    submitter.innerHTML = `<span class="ui-spinner" aria-hidden="true"></span><span>${label}</span>`;
});

document.addEventListener('turbo:submit-end', ({ target: form, detail }) => {
    const submitter = detail.formSubmission.submitter ?? form.querySelector('.is-submitting');

    if (detail.success) {
        return;
    }

    form.removeAttribute('aria-busy');

    if (! submitter) {
        return;
    }

    submitter.classList.remove('is-submitting');

    if (submitter.dataset.idleHtml !== undefined) {
        submitter.innerHTML = submitter.dataset.idleHtml;
        submitter.style.minWidth = '';
        delete submitter.dataset.idleHtml;
    }
});

Alpine.start();

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
