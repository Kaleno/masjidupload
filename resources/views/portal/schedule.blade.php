<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="ui-section-title">Santri</p>
            <h1 class="font-display text-2xl font-semibold text-teal-950">Jadwal belajar</h1>
        </div>
    </x-slot>

    <div class="w-full max-w-3xl space-y-4"
         x-data="{ selected: null, days: @js($calendar), get day() { return this.selected === null ? null : this.days[this.selected] } }"
         @keydown.escape.window="selected = null">
        <div class="flex items-center justify-between gap-2">
            <a href="{{ route('portal.schedule', ['bulan' => $prev]) }}" class="btn-secondary min-h-10 px-3" aria-label="Bulan sebelumnya">
                <x-icon name="arrow-left" class="h-4 w-4" />
            </a>
            <div class="text-center">
                <p class="font-display text-xl font-semibold text-teal-950">{{ $monthLabel }}</p>
                @unless ($isCurrentMonth)
                    <a href="{{ route('portal.schedule') }}" class="text-xs font-semibold text-teal-700">Kembali ke bulan ini</a>
                @endunless
            </div>
            <a href="{{ route('portal.schedule', ['bulan' => $next]) }}" class="btn-secondary min-h-10 px-3" aria-label="Bulan berikutnya">
                <x-icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>

        <div class="ui-card p-3 sm:p-4">
            <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $i => $weekday)
                    <div class="py-1.5 {{ $i >= 5 ? 'text-rose-600' : '' }}">{{ $weekday }}</div>
                @endforeach
            </div>
            <div class="grid grid-cols-7 gap-1">
                @for ($i = 0; $i < $leadingBlanks; $i++)
                    <div></div>
                @endfor
                @foreach ($calendar as $index => $day)
                    <button type="button" @click="selected = {{ $index }}"
                            @class([
                                'relative flex aspect-square flex-col items-center justify-center rounded-xl border text-sm transition focus:outline-none focus:ring-2 focus:ring-teal-600',
                                'border-rose-200 bg-rose-50 font-semibold text-rose-600' => $day['off'],
                                'border-slate-200 bg-white text-teal-950 hover:border-teal-400' => ! $day['off'],
                                'ring-2 ring-teal-700' => $day['isToday'],
                            ])>
                        <span>{{ $day['day'] }}</span>
                        @if ($day['request'])
                            <span class="absolute bottom-1 h-1.5 w-1.5 rounded-full {{ $day['request']['tone'] === 'ok' ? 'bg-sky-500' : 'bg-amber-500' }}"></span>
                        @elseif (! $day['off'] && $day['partial'])
                            <span class="absolute bottom-1 h-1.5 w-1.5 rounded-full bg-gold-400"></span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-slate-600">
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-rose-200 bg-rose-50"></span> Libur</span>
            <span class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-gold-400"></span> Hanya sebagian pengajar</span>
            <span class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span> Izin disetujui</span>
            <span class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Izin menunggu</span>
            <span>Klik tanggal untuk lihat pengajar.</span>
        </div>

        <div x-show="selected !== null" x-dialog="selected !== null" @dialog-back="selected = null" x-cloak
             class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" style="display: none;"
             role="dialog" aria-modal="true" aria-labelledby="schedule-day-title">
            <div class="absolute inset-0 bg-teal-950/40" @click="selected = null"></div>
            <template x-if="selected !== null">
                <div class="relative max-h-[85vh] w-full max-w-md overflow-y-auto rounded-t-3xl bg-cream-50 p-5 shadow-lift sm:rounded-3xl">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p id="schedule-day-title" class="font-display text-lg font-semibold text-teal-950" x-text="day.label"></p>
                            <p class="text-sm font-semibold text-rose-600" x-show="day.off" x-text="'Libur · ' + day.holiday"></p>
                        </div>
                        <button type="button" class="btn-ghost min-h-9 px-3" @click="selected = null" aria-label="Tutup">✕</button>
                    </div>

                    <template x-if="day.request">
                        <p class="mt-3 rounded-2xl bg-sky-50 px-4 py-2.5 text-sm text-sky-900"
                           x-text="'Pengajuan ' + day.request.type.toLowerCase() + ' kamu: ' + day.request.status"></p>
                    </template>

                    <template x-if="! day.off">
                        <div class="mt-4 space-y-2">
                            <p class="ui-section-title">Pengajar yang masuk</p>
                            <p class="text-xs text-slate-500" x-show="day.note" x-text="day.note"></p>
                            <template x-for="teacher in day.teachers" :key="teacher.name">
                                <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="font-medium text-teal-950" x-text="teacher.name"></p>
                                        <p class="text-xs text-slate-500" x-text="teacher.role + (teacher.phone ? ' · ' + teacher.phone : '')"></p>
                                    </div>
                                    <template x-if="teacher.wa">
                                        <a :href="teacher.wa" target="_blank" rel="noopener" class="btn-primary min-h-9 shrink-0 px-3 text-xs">Hubungi</a>
                                    </template>
                                    <template x-if="! teacher.wa">
                                        <span class="shrink-0 text-xs text-slate-500">Kontak belum ada</span>
                                    </template>
                                </div>
                            </template>
                            <p class="text-sm text-slate-500" x-show="day.teachers.length === 0">Belum ada data pengajar.</p>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</x-app-layout>
