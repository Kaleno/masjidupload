<div class="ui-card flex items-start justify-between gap-3 p-4">
    <div class="min-w-0">
        <p class="font-medium text-teal-950">{{ \App\Support\DateLabel::long($schedule->date) }}</p>
        <p class="mt-0.5 text-sm text-slate-600">
            @if ($schedule->isAllTeachers())
                Semua pengajar
            @else
                {{ $schedule->teachers->pluck('name')->implode(', ') ?: '—' }}
            @endif
        </p>
        @if ($schedule->note)
            <p class="mt-1 text-xs text-slate-500">{{ $schedule->note }}</p>
        @endif
    </div>
    <form method="POST" action="{{ route('ketua.teacher-schedules.destroy', $schedule) }}" class="shrink-0"
          onsubmit="return confirm('Hapus jadwal ini? Tanggal tersebut kembali ke semua pengajar masuk.')">
        @csrf
        @method('DELETE')
        <button class="text-sm font-semibold text-rose-700">Hapus</button>
    </form>
</div>
