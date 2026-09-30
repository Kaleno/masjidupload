<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="ui-section-title">Santri</p>
            <h1 class="font-display text-2xl font-semibold text-teal-950">Pengajuan izin / sakit</h1>
        </div>
    </x-slot>

    <div class="grid w-full max-w-5xl items-start gap-5 lg:grid-cols-2">
        @if ($canSubmit)
            <form method="POST" action="{{ route('portal.absence-requests.store') }}" class="ui-card grid gap-4 p-5">
                @csrf
                <p class="rounded-2xl bg-cream-100 px-4 py-3 text-sm text-slate-700">
                    Ajukan paling lambat di hari yang sama, tidak bisa untuk tanggal yang sudah lewat.
                    Kalau disetujui, absensimu otomatis tercatat Izin/Sakit. Kalau ditolak, tercatat Alfa.
                </p>

                <fieldset class="grid grid-cols-2 gap-2">
                    <legend class="mb-1.5 text-sm font-medium text-slate-700">Jenis</legend>
                    @foreach ($types as $type)
                        <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            <input type="radio" name="type" value="{{ $type->value }}" class="text-teal-700" required
                                   @checked(old('type', 'izin') === $type->value)>
                            <span>{{ $type->label() }}</span>
                        </label>
                    @endforeach
                </fieldset>
                <x-input-error :messages="$errors->get('type')" />

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <x-input-label for="date_from" value="Dari tanggal" />
                        <x-text-input id="date_from" name="date_from" type="date" class="mt-1.5"
                                      :value="old('date_from', now()->toDateString())" :min="now()->toDateString()" required />
                        <x-input-error class="mt-2" :messages="$errors->get('date_from')" />
                    </div>
                    <div>
                        <x-input-label for="date_to" value="Sampai tanggal" />
                        <x-text-input id="date_to" name="date_to" type="date" class="mt-1.5"
                                      :value="old('date_to', now()->toDateString())" :min="now()->toDateString()" required />
                        <x-input-error class="mt-2" :messages="$errors->get('date_to')" />
                    </div>
                </div>
                <p class="-mt-2 text-xs text-slate-500">Maksimal {{ $maxDays }} hari per pengajuan. Sabtu, Minggu, dan tanggal libur tidak dihitung.</p>

                <div>
                    <x-input-label for="reason" value="Alasan / catatan" />
                    <textarea id="reason" name="reason" rows="3" class="ui-input mt-1.5" required maxlength="500"
                              placeholder="Misal: demam sejak semalam, sudah ke dokter">{{ old('reason') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('reason')" />
                </div>
                <x-primary-button>Kirim pengajuan</x-primary-button>
            </form>
        @else
            <x-empty>Pengajuan izin hanya untuk santri yang masih aktif belajar.</x-empty>
        @endif

        <section class="space-y-2">
            <h2 class="ui-section-title">Status pengajuan</h2>
            <div class="ui-scroll-list space-y-2">
            @forelse ($requests as $item)
                <div class="ui-card space-y-2 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-teal-950">{{ $item->type->label() }} · {{ $item->periodLabel() }}</p>
                            <p class="mt-0.5 text-sm text-slate-600">{{ $item->reason }}</p>
                        </div>
                        <x-badge :tone="$item->status->badgeTone()" class="shrink-0">{{ $item->status->label() }}</x-badge>
                    </div>
                    <p class="text-xs text-slate-500">
                        Diajukan {{ $item->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                        @if ($item->reviewed_at)
                            · Direspons {{ $item->reviewer?->name }} {{ $item->reviewed_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                        @endif
                    </p>
                    @if ($item->review_note)
                        <p class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">Catatan: {{ $item->review_note }}</p>
                    @endif
                    @if ($item->isPending())
                        <form method="POST" action="{{ route('portal.absence-requests.cancel', $item) }}"
                              onsubmit="return confirm('Batalkan pengajuan ini?')">
                            @csrf
                            @method('PATCH')
                            <button class="text-sm font-semibold text-rose-700">Batalkan</button>
                        </form>
                    @endif
                </div>
            @empty
                <x-empty>Belum ada pengajuan.</x-empty>
            @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
