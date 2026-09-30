<div
    x-data
    x-show="$store.confirmSheet.open"
    x-dialog="$store.confirmSheet.open"
    @dialog-back="$store.confirmSheet.answer(false)"
    @keydown.escape.window="$store.confirmSheet.open && $store.confirmSheet.answer(false)"
    x-cloak
    class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center sm:p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="confirm-sheet-title"
    aria-describedby="confirm-sheet-message"
    data-confirm-sheet
>
    <div
        class="absolute inset-0 bg-teal-950/50 backdrop-blur-[2px]"
        x-show="$store.confirmSheet.open"
        x-transition.opacity.duration.150ms
        @click="$store.confirmSheet.answer(false)"
    ></div>

    <div
        class="relative w-full max-w-md rounded-t-3xl bg-cream-50 px-5 pt-3 shadow-lift sm:rounded-3xl sm:pt-5"
        style="padding-bottom: calc(1.25rem + env(safe-area-inset-bottom))"
        x-show="$store.confirmSheet.open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0"
        x-transition:enter-end="translate-y-0 sm:opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-y-0 sm:opacity-100"
        x-transition:leave-end="translate-y-full sm:translate-y-4 sm:opacity-0"
    >
        <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-slate-300 sm:hidden" aria-hidden="true"></div>
        <div class="flex items-start gap-3">
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl"
                :class="$store.confirmSheet.danger ? 'bg-rose-50 text-rose-700' : 'bg-teal-50 text-teal-800'"
            >
                <x-icon name="alert" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <h2 id="confirm-sheet-title" class="font-display text-lg font-semibold text-teal-950">Yakin?</h2>
                <p id="confirm-sheet-message" class="mt-1 text-sm leading-relaxed text-slate-600" x-text="$store.confirmSheet.message"></p>
            </div>
        </div>
        <div class="mt-5 grid grid-cols-2 gap-2">
            <button type="button" class="btn-secondary" @click="$store.confirmSheet.answer(false)" data-dialog-focus>Batal</button>
            <button
                type="button"
                :class="$store.confirmSheet.danger ? 'btn-danger' : 'btn-primary'"
                x-text="$store.confirmSheet.label"
                @click="$store.confirmSheet.answer(true)"
            ></button>
        </div>
    </div>
</div>
