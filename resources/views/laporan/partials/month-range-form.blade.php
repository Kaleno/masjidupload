<form method="GET" action="{{ $action }}" class="ui-card space-y-3 p-5">
    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <x-input-label for="from_month" value="Dari bulan" />
            <div class="mt-1.5 flex gap-2">
                <select id="from_month" name="from_month" class="ui-select flex-1">
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected($range->from->month === $m)>{{ \Carbon\Carbon::create(null, $m, 1)->translatedFormat('F') }}</option>
                    @endfor
                </select>
                <x-text-input type="number" name="from_year" class="w-24" :value="$range->from->year" min="2020" max="2100" />
            </div>
        </div>
        <div>
            <x-input-label for="to_month" value="Sampai bulan" />
            <div class="mt-1.5 flex gap-2">
                <select id="to_month" name="to_month" class="ui-select flex-1">
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected($range->to->month === $m)>{{ \Carbon\Carbon::create(null, $m, 1)->translatedFormat('F') }}</option>
                    @endfor
                </select>
                <x-text-input type="number" name="to_year" class="w-24" :value="$range->to->year" min="2020" max="2100" />
            </div>
        </div>
    </div>
    <p class="text-xs text-slate-500">Maksimal {{ \App\Support\MonthRange::MaxMonths }} bulan sekali tampil.</p>
    <div class="grid gap-2 sm:grid-cols-2">
        <button type="submit" class="btn-secondary">Tampilkan</button>
        <a href="{{ route($downloadRoute, $range->query()) }}" class="btn-primary" data-turbo="false">
            <x-icon name="arrow-right" class="h-4 w-4 rotate-90" />
            Download Excel
        </a>
    </div>
</form>
