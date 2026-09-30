@php
    $toastMessage = session('status');
    $silentStatuses = ['profile-updated', 'password-updated', 'verification-link-sent'];
@endphp

@if ($toastMessage && ! in_array($toastMessage, $silentStatuses, true))
    <div
        x-data="{ show: false }"
        x-init="$nextTick(() => show = true); setTimeout(() => show = false, {{ max(3500, mb_strlen($toastMessage) * 60) }})"
        x-show="show"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-3 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="ui-toast"
        role="status"
        aria-live="polite"
        data-toast
    >
        <button type="button" class="ui-toast-card" @click="show = false">
            <x-icon name="check-circle" class="h-5 w-5 shrink-0 text-gold-300" />
            <span class="text-left">{{ $toastMessage }}</span>
        </button>
    </div>
@endif
