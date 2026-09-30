@props([
    'members',
    'selected' => null,
    'pendingIds' => [],
    'pendingLabel' => 'Belum setor hari ini',
    'name' => 'santri_id',
])

@php
    $pendingLookup = array_flip(array_map('intval', $pendingIds));
    $items = collect($members)->map(fn ($member) => [
        'id' => (int) $member->id,
        'name' => $member->user->name,
        'nis' => (string) $member->nis,
        'track' => $member->track?->label(),
        'pending' => isset($pendingLookup[(int) $member->id]),
    ])->values();
    $current = $items->firstWhere('id', (int) $selected);
@endphp

<div
    x-data="{
        open: false,
        query: {{ \Illuminate\Support\Js::from($current['name'] ?? '') }},
        selectedId: {{ \Illuminate\Support\Js::from($current['id'] ?? '') }},
        items: {{ \Illuminate\Support\Js::from($items) }},
        get pending() {
            return this.items.filter((item) => item.pending);
        },
        get filtered() {
            const needle = String(this.query || '').toLowerCase().trim();
            const selected = this.items.find((item) => item.id === this.selectedId);
            if (needle === '' || (selected && selected.name === this.query)) {
                return this.items;
            }
            return this.items.filter((item) => item.name.toLowerCase().includes(needle) || item.nis.includes(needle));
        },
        pick(item) {
            this.selectedId = item.id;
            this.query = item.name;
            this.open = false;
            this.$refs.value.value = item.id;
            if (typeof applyContinueProgress === 'function') {
                applyContinueProgress();
            }
        },
    }"
    class="relative"
    @click.outside="open = false"
    @keydown.escape="open = false"
    data-santri-picker
>
    <input type="hidden" id="{{ $name }}" name="{{ $name }}" x-ref="value" value="{{ $current['id'] ?? '' }}">

    @if ($items->count() > 1)
        <div class="mb-3" data-pending-santri>
            @if (count($pendingIds) > 0)
                <p class="mb-2 text-xs font-semibold text-slate-600">{{ $pendingLabel }} ({{ count($pendingIds) }})</p>
                <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                    <template x-for="item in pending" :key="item.id">
                        <button
                            type="button"
                            class="ui-tap shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                            :class="selectedId === item.id ? 'border-teal-700 bg-teal-800 text-white' : 'border-amber-200 bg-amber-50 text-amber-900'"
                            :aria-pressed="(selectedId === item.id).toString()"
                            @click="pick(item)"
                            x-text="item.name"
                        ></button>
                    </template>
                </div>
            @else
                <p class="rounded-xl bg-teal-50 px-3 py-2 text-xs font-medium text-teal-900">Semua santri di daftar ini sudah setor. Pilih lagi kalau mau tambah setoran.</p>
            @endif
        </div>
    @endif

    <div class="relative">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
        <input
            id="{{ $name }}_search"
            type="search"
            x-model="query"
            @focus="open = true"
            @input="open = true"
            class="ui-input pl-10"
            placeholder="Cari nama atau NIS santri"
            autocomplete="off"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="{{ $name }}_options"
            :aria-expanded="open.toString()"
        >
    </div>

    <div
        id="{{ $name }}_options"
        x-show="open"
        x-cloak
        role="listbox"
        class="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lift"
    >
        <template x-for="item in filtered" :key="item.id">
            <button
                type="button"
                role="option"
                :aria-selected="(selectedId === item.id).toString()"
                class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm hover:bg-teal-50"
                :class="selectedId === item.id ? 'bg-teal-50' : ''"
                @click="pick(item)"
            >
                <span class="min-w-0">
                    <span class="block truncate font-medium text-slate-800" x-text="item.name"></span>
                    <span class="block text-xs text-slate-500" x-text="'NIS ' + item.nis + (item.track ? ' · ' + item.track : '')"></span>
                </span>
                <span
                    class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                    :class="item.pending ? 'bg-amber-50 text-amber-800' : 'bg-teal-50 text-teal-800'"
                    x-text="item.pending ? 'Belum setor' : 'Sudah setor'"
                ></span>
            </button>
        </template>
        <p class="px-3 py-2 text-sm text-slate-500" x-show="filtered.length === 0">Santri tidak ditemukan.</p>
    </div>
</div>
