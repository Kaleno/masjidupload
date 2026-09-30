<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="ui-section-title">Penjadwalan</p>
            <h1 class="font-display text-2xl font-semibold text-teal-950">Jadwal pengajar</h1>
        </div>
    </x-slot>

    <div class="grid w-full max-w-5xl items-start gap-5 lg:grid-cols-2">
        <form method="POST" action="{{ route('ketua.teacher-schedules.store') }}" class="ui-card grid gap-4 p-5"
              x-data="{ mode: @js(old('mode', \App\Models\TeacherSchedule::ModeSelected)) }">
            @csrf
            <p class="rounded-2xl bg-cream-100 px-4 py-3 text-sm text-slate-700">
                Tanggal yang tidak diatur otomatis dianggap <span class="font-semibold text-teal-950">semua pengajar masuk</span>.
                Atur di sini hanya kalau pada tanggal tertentu yang hadir cuma sebagian pengajar.
            </p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <x-input-label for="date_from" value="Dari tanggal" />
                    <x-text-input id="date_from" name="date_from" type="date" class="mt-1.5" :value="old('date_from')" required />
                    <x-input-error class="mt-2" :messages="$errors->get('date_from')" />
                </div>
                <div>
                    <x-input-label for="date_to" value="Sampai tanggal" />
                    <x-text-input id="date_to" name="date_to" type="date" class="mt-1.5" :value="old('date_to')" required />
                    <x-input-error class="mt-2" :messages="$errors->get('date_to')" />
                </div>
            </div>

            <fieldset class="grid gap-2">
                <legend class="mb-1.5 text-sm font-medium text-slate-700">Pengajar yang hadir</legend>
                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                    <input type="radio" name="mode" value="{{ \App\Models\TeacherSchedule::ModeAll }}" x-model="mode" class="text-teal-700">
                    <span>Semua pengajar</span>
                </label>
                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                    <input type="radio" name="mode" value="{{ \App\Models\TeacherSchedule::ModeSelected }}" x-model="mode" class="text-teal-700">
                    <span>Hanya pengajar tertentu</span>
                </label>
                <x-input-error :messages="$errors->get('mode')" />
            </fieldset>

            <div x-show="mode === '{{ \App\Models\TeacherSchedule::ModeSelected }}'" x-cloak class="grid gap-2">
                @forelse ($teachers as $teacher)
                    <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                        <input type="checkbox" name="teacher_ids[]" value="{{ $teacher->id }}" class="rounded text-teal-700"
                               @checked(in_array($teacher->id, array_map('intval', old('teacher_ids', [])), true))>
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium text-teal-950">{{ $teacher->name }}</span>
                            <span class="block text-xs text-slate-500">{{ \App\Support\Role::label($teacher->getRoleNames()->first() ?? '') }}</span>
                        </span>
                    </label>
                @empty
                    <x-empty>Belum ada pengajar aktif.</x-empty>
                @endforelse
                <x-input-error :messages="$errors->get('teacher_ids')" />
            </div>

            <div>
                <x-input-label for="note" value="Catatan (opsional)" />
                <x-text-input id="note" name="note" class="mt-1.5" :value="old('note')" placeholder="Misal: Ustaz Ahmad sedang dinas luar" />
                <x-input-error class="mt-2" :messages="$errors->get('note')" />
            </div>
            <p class="text-xs text-slate-500">Sabtu, Minggu, dan tanggal libur otomatis dilewati. Tanggal yang sudah punya jadwal akan diperbarui.</p>
            <x-primary-button>Simpan jadwal</x-primary-button>
        </form>

        <div class="space-y-4">
            <section class="space-y-2">
                <h2 class="ui-section-title">Jadwal mendatang</h2>
                <div class="ui-scroll-list space-y-2">
                    @forelse ($upcoming as $schedule)
                        @include('ketua.teacher-schedules.item', ['schedule' => $schedule])
                    @empty
                        <x-empty>
                            Belum ada jadwal khusus. Semua pengajar dianggap masuk.
                            <x-slot:action>
                                <label for="date_from" class="btn-secondary cursor-pointer">
                                    <x-icon name="plus" class="h-4 w-4" />
                                    Atur jadwal pengajar
                                </label>
                            </x-slot:action>
                        </x-empty>
                    @endforelse
                </div>
            </section>

            @if ($past->isNotEmpty())
                <section class="space-y-2">
                    <h2 class="ui-section-title">Sudah lewat</h2>
                    <div class="ui-scroll-list space-y-2">
                        @foreach ($past as $schedule)
                            @include('ketua.teacher-schedules.item', ['schedule' => $schedule])
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
