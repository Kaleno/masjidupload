<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="ui-section-title">Operasional</p>
            <h1 class="font-display text-2xl font-semibold text-teal-950">Pengajuan izin santri</h1>
        </div>
    </x-slot>

    <div class="w-full max-w-4xl space-y-5">
        <p class="text-sm text-slate-500">
            Disetujui: absensi di tanggal tersebut otomatis jadi Izin/Sakit beserta catatan santri. Ditolak: otomatis jadi Alfa.
            Pengajar tetap bisa mengubah keterangan di halaman absensi.
        </p>

        <section class="space-y-2">
            <h2 class="ui-section-title">Menunggu respons ({{ $pending->count() }})</h2>
            <div class="ui-scroll-list space-y-2">
            @forelse ($pending as $item)
                <div class="ui-card space-y-3 p-4" x-data="{ note: '' }">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-teal-950">{{ $item->santri->user->name ?? '—' }}</p>
                            <p class="text-sm text-slate-600">{{ $item->type->label() }} · {{ $item->periodLabel() }}</p>
                            <p class="mt-1 text-sm text-slate-700">{{ $item->reason }}</p>
                            <p class="mt-1 text-xs text-slate-500">Diajukan {{ $item->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
                        </div>
                        <x-badge :tone="$item->status->badgeTone()" class="shrink-0">{{ $item->status->label() }}</x-badge>
                    </div>
                    <x-text-input x-model="note" placeholder="Catatan untuk santri (opsional)" maxlength="255" />
                    <div class="grid grid-cols-2 gap-2">
                        <form method="POST" action="{{ route('ops.absence-requests.reject', $item) }}"
                              data-turbo-confirm="Tolak pengajuan ini? Absensi tanggal tersebut akan jadi Alfa." data-confirm-label="Tolak" data-confirm-tone="danger">
                            @csrf
                            <input type="hidden" name="review_note" :value="note">
                            <button class="btn-secondary btn-block text-rose-700">Tolak</button>
                        </form>
                        <form method="POST" action="{{ route('ops.absence-requests.approve', $item) }}">
                            @csrf
                            <input type="hidden" name="review_note" :value="note">
                            <button class="btn-primary btn-block">Setujui</button>
                        </form>
                    </div>
                </div>
            @empty
                <x-empty>Tidak ada pengajuan yang menunggu.</x-empty>
            @endforelse
            </div>
        </section>

        <section class="space-y-2">
            <h2 class="ui-section-title">Riwayat</h2>
            <div class="ui-scroll-list space-y-2">
            @forelse ($history as $item)
                <div class="ui-card flex items-start justify-between gap-3 p-4">
                    <div class="min-w-0">
                        <p class="font-medium text-teal-950">{{ $item->santri->user->name ?? '—' }}</p>
                        <p class="text-sm text-slate-600">{{ $item->type->label() }} · {{ $item->periodLabel() }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ $item->reason }}
                            @if ($item->reviewer)
                                · oleh {{ $item->reviewer->name }}
                            @endif
                        </p>
                    </div>
                    <x-badge :tone="$item->status->badgeTone()" class="shrink-0">{{ $item->status->label() }}</x-badge>
                </div>
            @empty
                <x-empty>Belum ada riwayat.</x-empty>
            @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
