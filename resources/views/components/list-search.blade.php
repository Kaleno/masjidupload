@props(['placeholder' => 'Cari nama atau NIS…'])

<div x-show="total > 0" x-cloak {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    <label class="relative block">
        <span class="sr-only">{{ $placeholder }}</span>
        <x-icon name="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
        <input type="search" x-model.debounce.150ms="query" @keydown.enter.prevent placeholder="{{ $placeholder }}" autocomplete="off" class="ui-input pl-10">
    </label>
    <p x-show="query.trim() !== ''"
       class="px-1 text-xs text-slate-500"
       x-text="shown > 0 ? `${shown} dari ${total} cocok` : 'Tidak ada yang cocok dengan pencarian ini.'"></p>
</div>
