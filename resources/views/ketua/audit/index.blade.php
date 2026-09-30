<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="ui-section-title">Audit</p>
            <h1 class="font-display text-2xl font-semibold text-teal-950">Log audit</h1>
        </div>
    </x-slot>

    <div class="w-full max-w-3xl space-y-3">
        <p class="text-sm text-slate-500">Catatan aksi operasional beserta waktu pencatatannya. Data Ketua DKM lain tidak tampil di sini.</p>

        @if ($logs->isEmpty())
            <x-empty>Belum ada catatan audit.</x-empty>
        @else
            <div class="ui-scroll-list space-y-2">
                @foreach ($logs as $log)
                    <div class="ui-card p-4">
                        <p class="text-sm leading-relaxed text-teal-950">{{ $log->remark }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $log->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
